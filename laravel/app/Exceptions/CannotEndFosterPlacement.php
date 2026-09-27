<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\FosterPlacement;
use DomainException;

/**
 * Sollevata (slice 3) quando si prova a concludere un affido il cui stato non è
 * Active (es. già Ended): solo un affido in corso può essere chiuso.
 *
 * È una guardia di DOMINIO: l'action EndFosterPlacement è l'unico punto in cui
 * un affido passa ad Ended e deve rifiutare gli affidi non attivi prima di
 * mutare qualunque stato.
 */
final class CannotEndFosterPlacement extends DomainException
{
    public function __construct(
        public readonly FosterPlacement $placement,
    ) {
        parent::__construct(
            __('Solo un affido in corso può essere concluso.'),
        );
    }

    public static function notActive(FosterPlacement $placement): self
    {
        return new self($placement);
    }
}
