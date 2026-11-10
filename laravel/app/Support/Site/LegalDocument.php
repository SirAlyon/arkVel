<?php

declare(strict_types=1);

namespace App\Support\Site;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Versione in vigore di un documento legale (informativa, termini) e, quando la versione è
 * una data `YYYY-MM`, il momento dell'ultimo aggiornamento da mostrare in pagina.
 *
 * La versione è una stringa opaca (di norma `2026-11`): se è nel formato anno-mese se ne
 * ricava il primo giorno del mese per l'etichetta «ultimo aggiornamento»; altrimenti resta
 * solo la stringa di versione e {@see self::updatedOn()} è null (la pagina mostra la versione
 * grezza, senza inventare una data).
 */
final readonly class LegalDocument
{
    private function __construct(
        public string $version,
        private ?CarbonImmutable $updatedOn,
    ) {}

    public static function fromVersion(string $version): self
    {
        return new self($version, self::parse($version));
    }

    /** Momento dell'ultimo aggiornamento, o null se la versione non è una data `YYYY-MM`. */
    public function updatedOn(): ?CarbonInterface
    {
        return $this->updatedOn;
    }

    private static function parse(string $version): ?CarbonImmutable
    {
        if (preg_match('/^(\d{4})-(\d{2})$/', $version, $matches) !== 1) {
            return null;
        }

        $year = (int) $matches[1];
        $month = (int) $matches[2];

        if ($month < 1 || $month > 12) {
            return null;
        }

        return CarbonImmutable::create($year, $month, 1)?->startOfDay();
    }
}
