<?php

declare(strict_types=1);

use App\Enums\CatalogFacet;
use App\Enums\Sex;
use App\Enums\Size;
use App\Enums\Species;
use App\Models\Organization;
use App\Support\Catalog\CatalogConstraint;
use App\Support\Catalog\CatalogFilters;

function catalogOrganization(int $id, string $slug = 'rifugio-aurora', string $name = 'Rifugio Aurora'): Organization
{
    $organization = new Organization(['slug' => $slug, 'name' => $name]);
    $organization->id = $id;

    return $organization;
}

it('has no constraints and no active facets when empty', function (): void {
    $filters = new CatalogFilters;

    expect($filters->constraints())->toBe([])
        ->and($filters->activeFacets())->toBe([])
        ->and($filters->hasFacetFilters())->toBeFalse()
        ->and($filters->isFiltered())->toBeFalse()
        ->and($filters->matchesNothing())->toBeFalse()
        ->and($filters->queryParameters())->toBe([]);
});

it('counts the free search as a filter but not as a facet', function (): void {
    $filters = new CatalogFilters(search: 'rex');

    expect($filters->isFiltered())->toBeTrue()
        ->and($filters->hasFacetFilters())->toBeFalse()
        ->and($filters->queryParameters())->toBe(['q' => 'rex']);
});

it('exposes the query string value of every facet', function (): void {
    $filters = new CatalogFilters(
        species: Species::Dog,
        sex: Sex::Female,
        size: Size::Large,
        region: 'Lombardia',
        regionOrganizationIds: [3],
        organization: catalogOrganization(3),
    );

    expect($filters->value(CatalogFacet::Species))->toBe('dog')
        ->and($filters->value(CatalogFacet::Sex))->toBe('female')
        ->and($filters->value(CatalogFacet::Size))->toBe('large')
        ->and($filters->value(CatalogFacet::Region))->toBe('Lombardia')
        ->and($filters->value(CatalogFacet::Organization))->toBe('rifugio-aurora')
        ->and($filters->activeFacets())->toBe(CatalogFacet::cases())
        ->and($filters->queryParameters())->toBe([
            'species' => 'dog',
            'sex' => 'female',
            'size' => 'large',
            'region' => 'Lombardia',
            'organization' => 'rifugio-aurora',
        ]);
});

it('turns enum filters into IN constraints on the animal columns', function (): void {
    $constraints = (new CatalogFilters(species: Species::Cat, sex: Sex::Male, size: Size::Small))->constraints();

    expect($constraints)->toEqual([
        new CatalogConstraint('species', ['cat']),
        new CatalogConstraint('sex', ['male']),
        new CatalogConstraint('size', ['small']),
    ]);
});

it('turns the region into the ids of its organizations', function (): void {
    $filters = new CatalogFilters(region: 'Lazio', regionOrganizationIds: [4, 7]);

    expect($filters->constraints())->toEqual([new CatalogConstraint('organization_id', [4, 7])]);
});

it('intersects organization and region into a single constraint', function (): void {
    $inside = new CatalogFilters(region: 'Lazio', regionOrganizationIds: [4, 7], organization: catalogOrganization(7));
    $outside = new CatalogFilters(region: 'Lazio', regionOrganizationIds: [4, 7], organization: catalogOrganization(9));

    expect($inside->constraints())->toEqual([new CatalogConstraint('organization_id', [7])])
        ->and($inside->matchesNothing())->toBeFalse()
        ->and($outside->constraints())->toEqual([new CatalogConstraint('organization_id', [])])
        ->and($outside->matchesNothing())->toBeTrue();
});

it('drops a single facet and keeps the rest', function (): void {
    $filters = new CatalogFilters(
        search: 'rex',
        species: Species::Dog,
        sex: Sex::Male,
        size: Size::Medium,
        region: 'Lazio',
        regionOrganizationIds: [4],
        organization: catalogOrganization(4),
    );

    foreach (CatalogFacet::cases() as $facet) {
        $without = $filters->without($facet);

        expect($without->has($facet))->toBeFalse()
            ->and($without->search)->toBe('rex')
            ->and(count($without->activeFacets()))->toBe(4);
    }

    expect($filters->without(CatalogFacet::Region)->regionOrganizationIds)->toBe([]);
});

it('labels facets and maps them to index fields', function (CatalogFacet $facet, string $field, string $label): void {
    expect($facet->indexField())->toBe($field)
        ->and($facet->label())->toBe($label);
})->with([
    [CatalogFacet::Species, 'species', 'Specie'],
    [CatalogFacet::Sex, 'sex', 'Sesso'],
    [CatalogFacet::Size, 'size', 'Taglia'],
    [CatalogFacet::Region, 'region', 'Regione'],
    [CatalogFacet::Organization, 'organization_id', 'Ente'],
]);
