<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Support\Map\ApproximateLocation;

/*
 | Posizione approssimata degli enti per le mappe pubbliche: griglia deterministica
 | configurabile (services.maps.grid_degrees, predefinita 0,02° ≈ 2 km), centro della cella,
 | nessuna coordinata precisa, cerchio che contiene sempre la sede.
 */

it('publishes the center of the grid cell, never the precise coordinates', function (float $latitude, float $longitude, float $expectedLatitude, float $expectedLongitude): void {
    $location = ApproximateLocation::fromCoordinates($latitude, $longitude);

    expect($location)->not->toBeNull()
        ->and($location->latitude)->toBe($expectedLatitude)
        ->and($location->longitude)->toBe($expectedLongitude);
})->with([
    'Roma' => [41.8902102, 12.4922309, 41.89, 12.49],
    'cell edge' => [45.48, 9.18, 45.49, 9.19],
    'just below the edge' => [45.479999, 9.179999, 45.47, 9.17],
    'west and south' => [-33.8567844, -70.6482653, -33.85, -70.65],
    'negative edge' => [-0.02, -0.04, -0.01, -0.03],
]);

it('uses a grid of 0.02 degrees by default', function (): void {
    expect(config('services.maps.grid_degrees'))->toBe(0.02)
        ->and(ApproximateLocation::fromCoordinates(41.8902102, 12.4922309)?->gridDegrees())->toBe(0.02);
});

it('follows the configured grid, within the accepted limits', function (mixed $configured, float $expected): void {
    config(['services.maps.grid_degrees' => $configured]);

    expect(ApproximateLocation::fromCoordinates(41.8902102, 12.4922309)?->gridDegrees())->toBe($expected);
})->with([
    'wider' => [0.05, 0.05],
    'from the environment, as a string' => ['0.03', 0.03],
    'too fine: clamped to the minimum' => [0.001, ApproximateLocation::MIN_GRID_DEGREES],
    'too wide: clamped to the maximum' => [5, ApproximateLocation::MAX_GRID_DEGREES],
    'not a number: the default' => ['abc', ApproximateLocation::DEFAULT_GRID_DEGREES],
    'zero: the default' => [0, ApproximateLocation::DEFAULT_GRID_DEGREES],
]);

it('is deterministic: the same place always gives the same cell', function (): void {
    $first = ApproximateLocation::fromCoordinates(43.7695604, 11.2558136);
    $second = ApproximateLocation::fromCoordinates(43.7695604, 11.2558136);
    $nearby = ApproximateLocation::fromCoordinates(43.7612, 11.2501);

    expect($first)->toEqual($second)
        ->and($nearby)->toEqual($first);
});

it('keeps the precise place within the published radius', function (float $grid, float $latitude, float $longitude): void {
    $location = ApproximateLocation::fromCoordinates($latitude, $longitude, $grid);

    // Come il cerchio di resources/js/ui/map.js: semiassi in gradi alla latitudine del centro.
    $north = ($latitude - $location->latitude) * ApproximateLocation::METERS_PER_DEGREE;
    $east = ($longitude - $location->longitude) * ApproximateLocation::METERS_PER_DEGREE * cos(deg2rad($location->latitude));

    expect(sqrt($north ** 2 + $east ** 2))->toBeLessThan($location->radiusMeters());
})->with([
    'cell corner' => [0.02, 45.4600001, 9.1600001],
    'opposite corner' => [0.02, 45.479999, 9.179999],
    'equator corner' => [0.02, 0.0000001, 0.0199999],
    'equator corner, wider grid' => [0.05, 0.0000001, 0.0499999],
    'equator corner, finest grid' => [0.01, -0.0099999, 0.0099999],
]);

it('derives the radius from the cell: 1.6 km with the default grid', function (float $grid, int $radius): void {
    expect(ApproximateLocation::fromCoordinates(41.8902102, 12.4922309, $grid)?->radiusMeters())->toBe($radius);
})->with([
    'default' => [0.02, 1600],
    'finest' => [0.01, 800],
    'wider' => [0.05, 4000],
]);

it('moves the precise place by several hundred meters unless it is near the cell center', function (): void {
    $location = ApproximateLocation::fromCoordinates(41.8812, 12.4810);

    expect(abs($location->latitude - 41.8812) + abs($location->longitude - 12.4810))->toBeGreaterThan(0.01);
});

it('has no position without valid coordinates', function (?float $latitude, ?float $longitude): void {
    expect(ApproximateLocation::fromCoordinates($latitude, $longitude))->toBeNull();
})->with([
    'missing latitude' => [null, 12.49],
    'missing longitude' => [41.89, null],
    'null island' => [0.0, 0.0],
    'latitude out of range' => [91.0, 12.49],
    'longitude out of range' => [41.89, -180.5],
    'not a number' => [NAN, 12.49],
    'infinite' => [41.89, INF],
]);

it('keeps the cells on the poles and the antimeridian inside the valid range', function (): void {
    $location = ApproximateLocation::fromCoordinates(90.0, 180.0);

    expect($location->latitude)->toBe(89.99)
        ->and($location->longitude)->toBe(179.99);
});

it('reads the coordinates of an organization', function (): void {
    $organization = new Organization(['latitude' => 44.4056499, 'longitude' => 8.9462564]);

    expect(ApproximateLocation::of($organization)?->toArray())->toBe(['lat' => 44.41, 'lng' => 8.95, 'radius' => 1600])
        ->and(ApproximateLocation::of(new Organization))->toBeNull()
        ->and(ApproximateLocation::of(null))->toBeNull();
});
