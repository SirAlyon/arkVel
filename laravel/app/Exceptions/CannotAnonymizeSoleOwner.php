<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Actions\Account\AnonymizeUserAccount;
use App\Models\User;
use DomainException;

/**
 * Sollevata quando un utente prova ad anonimizzare il proprio account mentre è
 * l'UNICO titolare (membership role `owner`, verificato) di almeno un ente.
 *
 * È una guardia di DOMINIO: anonimizzare l'ultimo titolare orfanerebbe l'ente
 * (nessuno potrebbe più gestirlo). L'account deve prima trasferire la titolarità
 * a un altro membro o chiudere l'ente. La guardia è valutata in
 * {@see AnonymizeUserAccount} PRIMA di qualunque mutazione,
 * così la transazione si interrompe senza intaccare alcun dato.
 *
 * Non registra PII: porta solo l'utente e l'id dell'ente coinvolto.
 */
final class CannotAnonymizeSoleOwner extends DomainException
{
    public function __construct(
        public readonly User $user,
        public readonly int $organizationId,
    ) {
        parent::__construct(
            __('account.delete.sole_owner_exception', ['organization' => $organizationId]),
        );
    }

    public static function for(User $user, int $organizationId): self
    {
        return new self($user, $organizationId);
    }
}
