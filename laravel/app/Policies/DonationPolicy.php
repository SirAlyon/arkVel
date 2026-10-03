<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Donation;
use App\Models\User;

/**
 * Policy per Donation.
 *
 * Due facce, come FosterApplicationPolicy:
 *  - OWNERSHIP: il donatore proprietario vede la propria donazione (`is()`), senza
 *    permessi spatie (vale solo per le donazioni non anonime, con un donor collegato).
 *  - TEAM-SCOPED: lo staff dell'ente gestisce le donazioni del proprio ente, con
 *    permesso team-scoped + membership VERIFICATA (doppia difesa contro fughe
 *    cross-tenant).
 *
 * Il Super Admin bypassa tutto via Gate::before (AppServiceProvider).
 * Registrazione: auto-discovery Laravel (App\Models\Donation → App\Policies\DonationPolicy).
 *
 * Le guardie di dominio del FLUSSO di pagamento (Checkout, importo minimo, anti-abuso)
 * vivono nelle Action in App\Actions\Donations (P4-6/P4-7), non qui.
 */
class DonationPolicy
{
    /**
     * Le liste sono scoped dal chiamante (ownership donatore o tenant staff): l'accesso
     * all'elenco è permissivo, l'isolamento è a livello di query/riga.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * La vede il donatore proprietario, oppure lo staff dell'ente beneficiario (con
     * permesso di vista e membership verificata).
     */
    public function view(User $user, Donation $donation): bool
    {
        return $user->is($donation->donor)
            || ($user->can(PermissionName::DonationView->value)
                && $user->isVerifiedMemberOf($donation->organization));
    }

    /**
     * Chiunque può donare (donazioni pubbliche): le guardie del flusso vivono nelle
     * Action in P4-6. Le donazioni anonime/da ospite NON passano da questa policy
     * (nessun utente autenticato), ma il predicato resta permissivo per gli autenticati.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Gestione staff (es. rimborso in una slice successiva): permesso di gestione +
     * membership verificata dell'ente beneficiario.
     */
    public function update(User $user, Donation $donation): bool
    {
        return $user->can(PermissionName::DonationManage->value)
            && $user->isVerifiedMemberOf($donation->organization);
    }

    /**
     * Azione di dominio dedicata (es. rimborso) — stessa regola di update: alias esplicito
     * per leggibilità nelle Resource/Action che chiedono `can('manage', $donation)`.
     */
    public function manage(User $user, Donation $donation): bool
    {
        return $this->update($user, $donation);
    }

    /**
     * Le donazioni non si cancellano: sono un registro contabile. Nessuno può eliminarle
     * (il Super Admin resta l'unica eccezione, via Gate::before).
     */
    public function delete(User $user, Donation $donation): bool
    {
        return false;
    }
}
