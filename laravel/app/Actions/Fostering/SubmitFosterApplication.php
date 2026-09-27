<?php

declare(strict_types=1);

namespace App\Actions\Fostering;

use App\Exceptions\DuplicateFosterApplication;
use App\Exceptions\IncompleteAdopterProfile;
use App\Models\Animal;
use App\Models\FosterApplication;
use App\Models\User;
use App\Notifications\NewFosterApplicationReceived;
use App\States\Foster\Submitted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Invia una richiesta di affido: un affidatario si candida per ospitare
 * temporaneamente un animale.
 *
 * Single responsibility: è l'unico punto in cui una richiesta di affido nasce.
 * Le guardie di dominio (profilo/consenso completo, unicità animale/utente) sono
 * verificate PRIMA di scrivere, così un rifiuto non lascia righe orfane; lo
 * unique DB (animal_id, user_id) resta il backstop. La persistenza, la
 * tracciatura (activity log) e la notifica all'ente sono orchestrate qui, non
 * nel model. Fa da specchio a App\Actions\Applications\SubmitApplication.
 *
 * RIUSO della guardia di screening: il profilo con questionario e consenso GDPR
 * ({@see User::hasCompleteAdopterProfile()}) è pensato per l'adozione ma si
 * applica anche all'affido — chi accoglie un animale in casa deve superare lo
 * stesso screening. Riusiamo perciò IncompleteAdopterProfile senza duplicare la
 * guardia.
 *
 * Notifica: dopo la creazione, i membri VERIFICATI dell'ente (owner + staff,
 * {@see Organization::verifiedStaff()}) ricevono {@see NewFosterApplicationReceived}
 * (mail + database). La notifica è {@see ShouldQueue} + `$afterCommit`: viene
 * accodata DOPO il commit di questa transazione (nessun invio sincrono qui).
 * Nessuna notifica se una guardia fallisce (nessuna riga creata → nessun commit
 * → nessun accodamento).
 */
final class SubmitFosterApplication
{
    /**
     * @throws IncompleteAdopterProfile quando il profilo affidatario è incompleto
     *                                  o manca il consenso GDPR
     * @throws DuplicateFosterApplication quando esiste già una richiesta
     *                                    dell'affidatario per lo stesso animale
     */
    public function handle(User $carer, Animal $animal): FosterApplication
    {
        // Guardia GDPR/questionario: nessuna riga viene creata se il profilo di
        // screening non è completo (consenso incluso). Riuso della guardia
        // dell'adozione (vedi docblock).
        if (! $carer->hasCompleteAdopterProfile()) {
            throw IncompleteAdopterProfile::for($carer);
        }

        return DB::transaction(function () use ($carer, $animal): FosterApplication {
            // Guardia di unicità applicativa: controlliamo prima dell'insert, così
            // solleviamo un'eccezione di dominio parlante invece di un
            // QueryException dallo unique DB (che resta comunque il backstop).
            $alreadyApplied = FosterApplication::query()
                ->where('animal_id', $animal->getKey())
                ->where('user_id', $carer->getKey())
                ->exists();

            if ($alreadyApplied) {
                throw DuplicateFosterApplication::for($animal, $carer);
            }

            $application = FosterApplication::query()->create([
                'animal_id' => $animal->getKey(),
                'user_id' => $carer->getKey(),
                'organization_id' => $animal->organization_id,
                // Stato di default (Submitted) applicato dal model; esplicito qui
                // per chiarezza e coerenza con submitted_at.
                'status' => Submitted::class,
                'submitted_at' => now(),
            ]);

            activity()
                ->performedOn($application)
                ->causedBy($carer)
                ->log('foster_application.submitted');

            // Notifica ai membri verificati dell'ente (owner + staff): il filtro
            // verifiedStaff() è per `verified=true`, non per ruolo, quindi include
            // gli owner. La notifica è accodata after-commit ($afterCommit): parte
            // solo dopo il commit di questa transazione (nessun invio mail sincrono
            // qui). Recipient vuoto → Notification::send è un no-op sicuro.
            Notification::send(
                $animal->organization->verifiedStaff()->get(),
                new NewFosterApplicationReceived($application),
            );

            return $application;
        });
    }
}
