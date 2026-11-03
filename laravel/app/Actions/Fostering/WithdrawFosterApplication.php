<?php

declare(strict_types=1);

namespace App\Actions\Fostering;

use App\Actions\Animals\TransitionAnimalStatus;
use App\Enums\AnimalStatus;
use App\Enums\FosterApplicationStatus;
use App\Enums\FosterPlacementStatus;
use App\Exceptions\CannotWithdrawFosterApplication;
use App\Exceptions\InvalidFosterApplicationTransition;
use App\Models\FosterApplication;
use App\Models\User;
use App\Notifications\FosterApplicationWithdrawn;
use App\States\Foster\Withdrawn;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Spatie\ModelStates\Exceptions\CouldNotPerformTransition;

/**
 * Ritiro di una richiesta di affido da parte dell'AFFIDATARIO proprietario.
 *
 * Single responsibility: unico punto in cui una richiesta passa a Withdrawn.
 * Guardia di ownership esplicita (solo il proprietario ritira) valutata prima di
 * mutare; il ritiro da uno stato terminale (Completed/Rejected/Withdrawn)
 * fallisce perché il grafo non lo consente e viene surfacciato come
 * InvalidFosterApplicationTransition, senza lasciare stato/log parziali. Fa da
 * specchio a App\Actions\Applications\WithdrawApplication.
 *
 * Notifica: dopo il ritiro (e l'eventuale rilascio dell'animale), lo STAFF
 * dell'ente riceve {@see FosterApplicationWithdrawn} (mail + database). I
 * destinatari sono l'operatore in carico (assignedTo) se assegnato, altrimenti i
 * membri verificati dell'ente ({@see Organization::verifiedStaff()}). La notifica
 * è accodata after-commit ($afterCommit).
 *
 * Accoppiamento con lo stato dell'ANIMALE: se la richiesta ritirata era in
 * ADFIDO ATTIVO (Active) con un FosterPlacement Active, il ritiro CHIUDE
 * l'affido (end_date = ora, status = Ended) e RIENTRA l'animale
 * (Fostered → Available) via TransitionAnimalStatus, dentro la stessa
 * transazione. È lo specchio del rilascio della riserva in WithdrawApplication.
 */
final class WithdrawFosterApplication
{
    public function __construct(
        private readonly TransitionAnimalStatus $transitionAnimalStatus,
    ) {}

    /**
     * @throws CannotWithdrawFosterApplication quando l'attore non è il proprietario
     * @throws InvalidFosterApplicationTransition quando lo stato corrente è terminale
     */
    public function handle(
        FosterApplication $application,
        User $carer,
        ?string $reason = null,
    ): FosterApplication {
        if (! $carer->is($application->user)) {
            throw CannotWithdrawFosterApplication::notOwner($application, $carer);
        }

        $from = $application->statusEnum();

        return DB::transaction(function () use ($application, $from, $carer, $reason): FosterApplication {
            try {
                $application->status->transitionTo(Withdrawn::class);
            } catch (CouldNotPerformTransition) {
                throw InvalidFosterApplicationTransition::between($from, FosterApplicationStatus::Withdrawn);
            }

            $application->decided_at = now();
            $application->decision_reason = $reason;
            $application->save();

            // Rientro dell'animale: se QUESTA richiesta aveva un affido attivo,
            // chiuderlo e riportare l'animale disponibile. Dentro la transazione:
            // il rientro e il ritiro sono un unico atto atomico.
            $this->releaseFosteredAnimal($application);

            activity()
                ->performedOn($application)
                ->causedBy($carer)
                ->withProperties([
                    'from' => $from->value,
                    'reason' => $reason,
                ])
                ->log('foster_application.withdrawn');

            // Notifica allo staff dell'ente. Accodata after-commit: parte solo dopo
            // il commit di questa transazione (nessun invio mail sincrono qui).
            // Recipient vuoto → Notification::send è un no-op sicuro.
            Notification::send(
                $this->recipients($application),
                new FosterApplicationWithdrawn($application),
            );

            return $application;
        });
    }

    /**
     * Destinatari lato ente della notifica di ritiro: l'operatore in carico
     * (assignedTo) se assegnato, altrimenti i membri VERIFICATI dell'ente
     * (owner + staff). Restituita come Collection di User, coerente col contratto
     * di Notification::send.
     *
     * @return Collection<int, User>
     */
    private function recipients(FosterApplication $application): Collection
    {
        if ($application->assignedTo !== null) {
            return collect([$application->assignedTo]);
        }

        return $application->organization->verifiedStaff()->get();
    }

    /**
     * Chiude l'affido attivo di QUESTA richiesta e riporta l'animale disponibile
     * (Fostered → Available) SOLO se la richiesta ritirata aveva un affido in
     * corso. Se non esiste un placement Active per questa richiesta non tocchiamo
     * nulla. Il rientro dell'animale avviene solo se l'animale è effettivamente
     * In affido (Fostered): la guardia dell'enum garantisce la legalità della
     * transizione.
     */
    private function releaseFosteredAnimal(FosterApplication $application): void
    {
        $placement = $application->placement;

        if ($placement === null || $placement->status !== FosterPlacementStatus::Active) {
            return;
        }

        $placement->end_date = now();
        $placement->status = FosterPlacementStatus::Ended;
        $placement->save();

        if ($application->animal->status === AnimalStatus::Fostered) {
            $this->transitionAnimalStatus->handle($application->animal, AnimalStatus::Available);
        }
    }
}
