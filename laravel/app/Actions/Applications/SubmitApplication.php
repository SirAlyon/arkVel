<?php

declare(strict_types=1);

namespace App\Actions\Applications;

use App\Exceptions\DuplicateApplication;
use App\Exceptions\IncompleteAdopterProfile;
use App\Models\AdoptionApplication;
use App\Models\Animal;
use App\Models\User;
use App\Notifications\NewApplicationReceived;
use App\States\Application\Submitted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Invia una candidatura di adozione: un adottante si candida per un animale.
 *
 * Single responsibility: è l'unico punto in cui una candidatura nasce. Le
 * guardie di dominio (profilo/consenso completo, unicità animale/utente) sono
 * verificate PRIMA di scrivere, così un rifiuto non lascia righe orfane; lo
 * unique DB (animal_id, user_id) resta il backstop. La persistenza, la
 * tracciatura (activity log) e la notifica all'ente sono orchestrate qui, non
 * nel model.
 *
 * Notifica (slice 8): dopo la creazione, i membri VERIFICATI dell'ente
 * (owner + staff, {@see Organization::verifiedStaff()}) ricevono
 * {@see NewApplicationReceived} (mail + database). L'invio avviene DENTRO la
 * transazione: se la scrittura fallisce non parte alcuna notifica, e viceversa
 * un errore d'invio annulla anche la candidatura (coerenza atomica). Nessuna
 * notifica se una guardia fallisce (nessuna riga creata).
 */
final class SubmitApplication
{
    /**
     * @throws IncompleteAdopterProfile quando il profilo adottante è incompleto
     *                                  o manca il consenso GDPR
     * @throws DuplicateApplication quando esiste già una candidatura
     *                              dell'adottante per lo stesso animale
     */
    public function handle(User $adopter, Animal $animal): AdoptionApplication
    {
        // Guardia GDPR/questionario: nessuna riga viene creata se il profilo non
        // è completo (consenso incluso).
        if (! $adopter->hasCompleteAdopterProfile()) {
            throw IncompleteAdopterProfile::for($adopter);
        }

        return DB::transaction(function () use ($adopter, $animal): AdoptionApplication {
            // Guardia di unicità applicativa: controlliamo prima dell'insert, così
            // solleviamo un'eccezione di dominio parlante invece di un
            // QueryException dallo unique DB (che resta comunque il backstop).
            $alreadyApplied = AdoptionApplication::query()
                ->where('animal_id', $animal->getKey())
                ->where('user_id', $adopter->getKey())
                ->exists();

            if ($alreadyApplied) {
                throw DuplicateApplication::for($animal, $adopter);
            }

            $application = AdoptionApplication::query()->create([
                'animal_id' => $animal->getKey(),
                'user_id' => $adopter->getKey(),
                'organization_id' => $animal->organization_id,
                // Stato di default (Submitted) applicato dal model; esplicito qui
                // per chiarezza e coerenza con submitted_at.
                'status' => Submitted::class,
                'submitted_at' => now(),
            ]);

            activity()
                ->performedOn($application)
                ->causedBy($adopter)
                ->log('application.submitted');

            // Notifica ai membri verificati dell'ente (owner + staff): il filtro
            // verifiedStaff() è per `verified=true`, non per ruolo, quindi include
            // gli owner. Recipient vuoto → Notification::send è un no-op sicuro.
            Notification::send(
                $animal->organization->verifiedStaff()->get(),
                new NewApplicationReceived($application),
            );

            return $application;
        });
    }
}
