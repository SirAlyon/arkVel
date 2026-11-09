<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Support\Site\PublicLocation;

it('formats city and region, skipping empty values and duplicates', function (?string $city, ?string $region, ?string $expected): void {
    expect(PublicLocation::format($city, $region))->toBe($expected);
})->with([
    'both' => ['Genova', 'Liguria', 'Genova, Liguria'],
    'city only' => ['Genova', null, 'Genova'],
    'region only' => ['  ', 'Liguria', 'Liguria'],
    'same name' => ['Aosta', 'aosta', 'Aosta'],
    'nothing' => [null, '', null],
]);

it('reads the public location of an organization, never its address or country', function (): void {
    $organization = new Organization(['city' => 'Lucca', 'region' => 'Toscana', 'country' => 'IT', 'address_line' => 'Via Roma 1']);

    expect(PublicLocation::of($organization))->toBe('Lucca, Toscana')
        ->and(PublicLocation::of(null))->toBeNull();
});
