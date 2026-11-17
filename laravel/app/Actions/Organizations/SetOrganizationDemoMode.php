<?php

declare(strict_types=1);

namespace App\Actions\Organizations;

use App\Models\Organization;
use App\Models\User;
use App\Support\Organizations\DemoOrganizationGuard;

/**
 * Marca (o smarca) un ente come DIMOSTRATIVO. Caso d'uso del solo Super Admin, dal
 * pannello /admin: l'autorizzazione è della Resource che la invoca.
 *
 * Effetti del flag: badge «Esempio» sul sito pubblico e rifiuto di candidature, affidi,
 * visite, messaggi e donazioni ({@see DemoOrganizationGuard}). La visibilità pubblica non
 * cambia. Il cambio è tracciato nell'activity log; nessuna scrittura se il valore è già
 * quello richiesto.
 */
final class SetOrganizationDemoMode
{
    public function handle(Organization $organization, bool $isDemo, ?User $causer = null): Organization
    {
        if ($organization->is_demo === $isDemo) {
            return $organization;
        }

        $organization->forceFill(['is_demo' => $isDemo])->save();

        activity()
            ->performedOn($organization)
            ->causedBy($causer)
            ->withProperties(['is_demo' => $isDemo])
            ->log($isDemo ? 'organization.demo_enabled' : 'organization.demo_disabled');

        return $organization;
    }
}
