<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Appointment;
use App\Models\User;

/**
 * Policy per Appointment.
 *
 * Rispecchia FosterApplicationPolicy: l'appuntamento ha DUE facce, quindi questa
 * policy combina i due pattern del progetto:
 *  - OWNERSHIP: il richiedente vede e può annullare il proprio appuntamento
 *    (`is()`), senza permessi spatie.
 *  - TEAM-SCOPED: lo staff dell'ente gestisce gli appuntamenti del proprio ente,
 *    con permesso team-scoped + membership VERIFICATA (doppia difesa contro fughe
 *    cross-tenant).
 *
 * L'annullamento è concesso a ENTRAMBE le parti (richiedente o staff); la
 * gestione forward (conferma/completa/assente) è solo dello staff. Le guardie di
 * dominio (slot nel futuro, overlap) vivono in RequestAppointment, non qui.
 *
 * Il Super Admin bypassa tutto via Gate::before (AppServiceProvider).
 * Registrazione: auto-discovery Laravel (App\Models\X → App\Policies\XPolicy).
 */
class AppointmentPolicy
{
    /**
     * Le liste sono scoped dal chiamante (ownership richiedente o tenant staff):
     * l'accesso all'elenco è permissivo, l'isolamento è a livello di query/riga.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Lo vede il richiedente proprietario, oppure lo staff dell'ente (con permesso
     * di vista e membership verificata).
     */
    public function view(User $user, Appointment $appointment): bool
    {
        return $user->is($appointment->user)
            || ($user->can(PermissionName::AppointmentView->value)
                && $user->isVerifiedMemberOf($appointment->organization));
    }

    /**
     * Chiunque può richiedere un appuntamento: le guardie di dominio (slot nel
     * futuro, assenza di overlap) vivono in RequestAppointment.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Gestione staff (conferma/completa/assente): permesso di gestione + membership
     * verificata dell'ente proprietario.
     */
    public function update(User $user, Appointment $appointment): bool
    {
        return $user->can(PermissionName::AppointmentManage->value)
            && $user->isVerifiedMemberOf($appointment->organization);
    }

    /**
     * Annullamento: consentito a ENTRAMBE le parti — il richiedente proprietario,
     * oppure lo staff dell'ente (con permesso di gestione e membership verificata).
     */
    public function cancel(User $user, Appointment $appointment): bool
    {
        return $user->is($appointment->user)
            || ($user->can(PermissionName::AppointmentManage->value)
                && $user->isVerifiedMemberOf($appointment->organization));
    }

    /**
     * Gli appuntamenti non si cancellano: si annullano. Nessuno può eliminarli (il
     * Super Admin resta l'unica eccezione, via Gate::before).
     */
    public function delete(User $user, Appointment $appointment): bool
    {
        return false;
    }
}
