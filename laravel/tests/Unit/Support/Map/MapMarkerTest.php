<?php

declare(strict_types=1);

use App\Support\Map\ApproximateLocation;
use App\Support\Map\CatalogMap;
use App\Support\Map\MapMarker;

function mapMarker(string $id, ?ApproximateLocation $location): MapMarker
{
    return new MapMarker(
        id: $id,
        title: 'Rifugio '.$id,
        description: 'Roma, Lazio',
        count: 3,
        countLabel: '3 animali',
        url: 'https://arkvel.test/animali?organization='.$id,
        location: $location,
    );
}

it('exposes only public data and the approximate position to the map', function (): void {
    $marker = mapMarker('aurora', ApproximateLocation::fromCoordinates(41.8902102, 12.4922309));

    expect($marker->isLocated())->toBeTrue()
        ->and($marker->toArray())->toBe([
            'id' => 'aurora',
            'lat' => 41.89,
            'lng' => 12.49,
            'title' => 'Rifugio aurora',
            'description' => 'Roma, Lazio',
            'count' => 3,
            'countLabel' => '3 animali',
            'url' => 'https://arkvel.test/animali?organization=aurora',
        ]);
});

it('keeps the organizations without a position for the list only', function (): void {
    $located = mapMarker('aurora', ApproximateLocation::fromCoordinates(41.89, 12.49));
    $unlocated = mapMarker('stella', null);

    $map = new CatalogMap([$located, $unlocated]);

    expect($unlocated->isLocated())->toBeFalse()
        ->and($unlocated->toArray())->toMatchArray(['lat' => null, 'lng' => null])
        ->and($map->located())->toBe([$located])
        ->and($map->hasUnlocated())->toBeTrue()
        ->and($map->isEmpty())->toBeFalse()
        ->and((new CatalogMap([$located]))->hasUnlocated())->toBeFalse()
        ->and((new CatalogMap)->isEmpty())->toBeTrue();
});
