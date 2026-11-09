<?php

declare(strict_types=1);

namespace App\Support\Map;

use App\Models\Organization;

/**
 * Posizione APPROSSIMATA di un ente, l'unica che il sito pubblico mostra su una mappa
 * (privacy, §4 del piano UI): molte associazioni hanno sede a casa di un volontario, quindi
 * le coordinate salvate (`latitude`/`longitude`, precise) non escono mai dal server.
 *
 * Griglia deterministica: le coordinate cadono in una cella quadrata (in gradi) e se ne
 * pubblica il CENTRO. Il lato è configurabile (`services.maps.grid_degrees`, env
 * MAP_GRID_DEGREES): {@see self::DEFAULT_GRID_DEGREES} (0,02°, circa 2,2 km in latitudine e
 * 1,6 km in longitudine alle nostre latitudini), mai sotto {@see self::MIN_GRID_DEGREES} (una
 * zona, non un isolato) né sopra {@see self::MAX_GRID_DEGREES}. Stesse coordinate, stessa
 * cella: niente rumore casuale, che ricaricando la pagina si potrebbe mediare fino a
 * ritrovare il punto vero. Il calcolo è in interi (micro-gradi), così il bordo di una cella
 * non dipende dagli arrotondamenti dei float.
 *
 * Il cerchio della pagina dell'ente ({@see self::radiusMeters()}) ha come raggio la
 * semidiagonale della cella all'equatore, dove è massima, arrotondata per eccesso ai 100 m
 * (1,6 km con la griglia predefinita): contiene sempre la sede, a qualunque latitudine,
 * senza indicarla.
 *
 * Coordinate mancanti, fuori intervallo o (0, 0) (il valore di un campo mai compilato) non
 * danno una posizione.
 */
final readonly class ApproximateLocation
{
    /** Lato predefinito della cella della griglia, in gradi. */
    public const float DEFAULT_GRID_DEGREES = 0.02;

    /** Lato minimo accettato dalla configurazione (circa 1,1 km). */
    public const float MIN_GRID_DEGREES = 0.01;

    /** Lato massimo accettato dalla configurazione. */
    public const float MAX_GRID_DEGREES = 1.0;

    /** Metri per grado di latitudine (lo stesso valore del cerchio in resources/js/ui/map.js). */
    public const int METERS_PER_DEGREE = 111_320;

    private const int MICRODEGREES = 1_000_000;

    private function __construct(
        public float $latitude,
        public float $longitude,
        private int $gridMicrodegrees,
    ) {}

    public static function of(?Organization $organization): ?self
    {
        if ($organization === null) {
            return null;
        }

        $latitude = $organization->getAttribute('latitude');
        $longitude = $organization->getAttribute('longitude');

        return self::fromCoordinates(
            is_numeric($latitude) ? (float) $latitude : null,
            is_numeric($longitude) ? (float) $longitude : null,
        );
    }

    /**
     * @param  float|null  $gridDegrees  lato della cella (default: la configurazione)
     */
    public static function fromCoordinates(?float $latitude, ?float $longitude, ?float $gridDegrees = null): ?self
    {
        if ($latitude === null || $longitude === null || ! is_finite($latitude) || ! is_finite($longitude)) {
            return null;
        }

        if (abs($latitude) > 90 || abs($longitude) > 180 || ($latitude === 0.0 && $longitude === 0.0)) {
            return null;
        }

        $grid = self::gridMicrodegrees($gridDegrees ?? config('services.maps.grid_degrees'));

        return new self(self::snap($latitude, 90, $grid), self::snap($longitude, 180, $grid), $grid);
    }

    /**
     * Lato della cella in micro-gradi: il valore dato entro i limiti, o quello predefinito
     * se non è un numero positivo.
     */
    public static function gridMicrodegrees(mixed $degrees): int
    {
        $degrees = is_numeric($degrees) && (float) $degrees > 0 ? (float) $degrees : self::DEFAULT_GRID_DEGREES;

        return (int) round(max(self::MIN_GRID_DEGREES, min(self::MAX_GRID_DEGREES, $degrees)) * self::MICRODEGREES);
    }

    /** Lato della cella che contiene la posizione, in gradi. */
    public function gridDegrees(): float
    {
        return $this->gridMicrodegrees / self::MICRODEGREES;
    }

    /** Raggio dell'area mostrata: la semidiagonale massima della cella, per eccesso ai 100 m. */
    public function radiusMeters(): int
    {
        $halfSide = $this->gridDegrees() / 2 * self::METERS_PER_DEGREE;

        return (int) (ceil($halfSide * M_SQRT2 / 100) * 100);
    }

    /**
     * Per il JavaScript della mappa (x-ui.map).
     *
     * @return array{lat: float, lng: float, radius: int}
     */
    public function toArray(): array
    {
        return ['lat' => $this->latitude, 'lng' => $this->longitude, 'radius' => $this->radiusMeters()];
    }

    /**
     * Centro della cella che contiene `$degrees`, entro ±`$limit` (le celle sul bordo
     * restano dentro l'intervallo valido).
     */
    private static function snap(float $degrees, int $limit, int $grid): float
    {
        $micro = (int) round($degrees * self::MICRODEGREES);
        $cell = intdiv($micro, $grid) - ($micro < 0 && $micro % $grid !== 0 ? 1 : 0);
        $center = $cell * $grid + intdiv($grid, 2);
        $bound = $limit * self::MICRODEGREES - intdiv($grid, 2);

        return max(-$bound, min($bound, $center)) / self::MICRODEGREES;
    }
}
