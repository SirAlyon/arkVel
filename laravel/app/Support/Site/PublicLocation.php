<?php

declare(strict_types=1);

namespace App\Support\Site;

use App\Models\Organization;

/**
 * La località pubblica di un ente (e quindi dei suoi animali) sul sito: «città, regione»,
 * un solo formato per card, pagina dell'ente e scheda. Niente indirizzo, CAP o
 * coordinate (privacy: la posizione resta approssimata); niente paese, perché il sito
 * oggi elenca enti di un solo paese.
 *
 * Valori vuoti saltati, un doppione (città uguale alla regione) mostrato una volta sola;
 * null se non resta niente.
 */
final class PublicLocation
{
    public static function of(?Organization $organization): ?string
    {
        if ($organization === null) {
            return null;
        }

        /** @var string|null $region */
        $region = $organization->getAttribute('region');

        return self::format($organization->city, $region);
    }

    public static function format(?string $city, ?string $region): ?string
    {
        $parts = [];

        foreach ([$city, $region] as $part) {
            $part = trim((string) $part);

            if ($part !== '' && ! in_array(mb_strtolower($part), array_map(mb_strtolower(...), $parts), true)) {
                $parts[] = $part;
            }
        }

        return $parts === [] ? null : implode(', ', $parts);
    }
}
