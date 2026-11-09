<?php

declare(strict_types=1);

namespace App\Data\Media;

/**
 * Esito di una rigenerazione: media coinvolti, conversioni richieste (eseguite subito o
 * accodate) ed errori per id del media.
 */
final readonly class RegenerationSummary
{
    /**
     * @param  array<int, string>  $failures
     */
    public function __construct(
        public int $media = 0,
        public int $conversions = 0,
        public array $failures = [],
    ) {}
}
