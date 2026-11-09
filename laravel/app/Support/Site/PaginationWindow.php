<?php

declare(strict_types=1);

namespace App\Support\Site;

/**
 * I numeri di pagina da mostrare in x-ui.pagination: la prima, l'ultima e quelle intorno
 * alle pagine correnti, con `null` al posto di ogni salto («…»). Un salto di una sola
 * pagina non vale i puntini: si mostra la pagina (1 2 3, non 1 … 3).
 *
 * Le pagine correnti sono un intervallo (`current`…`currentTo`): il catalogo mostra più
 * pagine di seguito con «Carica altri»; per una lista normale coincidono.
 */
final class PaginationWindow
{
    /**
     * @return list<int|null>
     */
    public static function pages(int $current, int $last, ?int $currentTo = null, int $onEachSide = 1): array
    {
        $last = max(1, $last);
        $current = min(max(1, $current), $last);
        $currentTo = min(max($current, $currentTo ?? $current), $last);
        $onEachSide = max(0, $onEachSide);

        $pages = [1, $last, ...range(max(1, $current - $onEachSide), min($last, $currentTo + $onEachSide))];
        $pages = array_values(array_unique($pages));
        sort($pages);

        $window = [];
        $previous = null;

        foreach ($pages as $page) {
            if ($previous !== null && $page - $previous === 2) {
                $window[] = $previous + 1;
            } elseif ($previous !== null && $page - $previous > 2) {
                $window[] = null;
            }

            $window[] = $page;
            $previous = $page;
        }

        return $window;
    }
}
