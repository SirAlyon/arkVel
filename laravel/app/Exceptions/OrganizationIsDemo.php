<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Organization;
use App\Support\Organizations\DemoOrganizationGuard;
use DomainException;

/**
 * Sollevata da {@see DemoOrganizationGuard} quando un'interazione che invierebbe dati
 * personali o denaro (candidatura, affido, visita, messaggio, donazione) è rivolta a un
 * ente dimostrativo (`is_demo`). Nessuna riga è scritta e nessuna sessione Stripe è
 * creata: la guardia gira prima di qualunque persistenza.
 *
 * Se non intercettata, il gestore delle eccezioni la traduce in un 403 con lo stesso
 * messaggio localizzato (bootstrap/app.php).
 */
final class OrganizationIsDemo extends DomainException
{
    public function __construct(public readonly Organization $organization)
    {
        parent::__construct(__('demo.refused'));
    }

    public static function for(Organization $organization): self
    {
        return new self($organization);
    }
}
