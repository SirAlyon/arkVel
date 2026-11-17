<?php

declare(strict_types=1);

namespace App\Support\Organizations;

use App\Exceptions\OrganizationIsDemo;
use App\Models\Organization;

/**
 * Guardia di dominio, stateless: un ente dimostrativo (`is_demo`) non riceve dati
 * personali né denaro.
 *
 * Fonte UNICA della regola, iniettata nelle Action che inviano qualcosa a un ente
 * (SubmitApplication, SubmitFosterApplication, RequestAppointment,
 * ContactOrganizationAboutAnimal, StartMessageThread, StartDonationCheckout,
 * StartRecurringDonationCheckout) e chiamata prima di qualunque scrittura. La UI
 * pubblica nasconde già le CTA, ma il confine è qui.
 */
final class DemoOrganizationGuard
{
    /**
     * @throws OrganizationIsDemo quando l'ente è dimostrativo
     */
    public function ensureAcceptsSubmissions(Organization $organization): void
    {
        if ($organization->is_demo) {
            throw OrganizationIsDemo::for($organization);
        }
    }
}
