<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\RecurringDonation;
use App\Models\User;

/**
 * Policy per RecurringDonation.
 *
 * OWNERSHIP pura: una donazione ricorrente appartiene al suo titolare (l'utente che l'ha
 * avviata). Solo lui può vederla e disdirla dal pannello /account. Non c'è tenancy:
 * l'abbonamento è della persona, non dell'ente. L'isolamento cross-utente è garantito sia
 * dallo scope della query (`where('user_id', ...)`) sia da questa policy sulla singola riga.
 *
 * Il Super Admin bypassa tutto via Gate::before (AppServiceProvider).
 * Registrazione: auto-discovery Laravel (App\Models\RecurringDonation →
 * App\Policies\RecurringDonationPolicy).
 *
 * Le guardie di dominio del flusso (config Stripe, importo, identità, idempotenza della
 * disdetta) vivono nelle Action in App\Actions\Donations, non qui.
 */
class RecurringDonationPolicy
{
    /**
     * La lista è scoped dal chiamante (ownership): l'accesso all'elenco è permissivo,
     * l'isolamento è a livello di query/riga.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * La vede solo il titolare dell'abbonamento.
     */
    public function view(User $user, RecurringDonation $recurring): bool
    {
        return $user->is($recurring->donor);
    }

    /**
     * La disdice solo il titolare dell'abbonamento.
     */
    public function cancel(User $user, RecurringDonation $recurring): bool
    {
        return $user->is($recurring->donor);
    }
}
