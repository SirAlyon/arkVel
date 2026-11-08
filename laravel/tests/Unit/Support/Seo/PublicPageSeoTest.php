<?php

declare(strict_types=1);

use App\Enums\OrganizationType;
use App\Enums\Species;
use App\Models\Animal;
use App\Models\Organization;
use App\Support\Seo\Breadcrumb;
use App\Support\Seo\OpenGraphType;
use App\Support\Seo\PublicPageSeo;
use App\Support\Seo\SeoData;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app()->setLocale('it');
    $this->seo = app(PublicPageSeo::class);
});

/**
 * @return list<string>
 */
function graphTypes(SeoData $seo): array
{
    return array_map(static fn (array $node): string => $node['@type'], $seo->jsonLd);
}

/**
 * @return list<array{name: string, url: string}>
 */
function crumbs(SeoData $seo): array
{
    return array_map(static fn (Breadcrumb $crumb): array => $crumb->toArray(), $seo->breadcrumbs);
}

it('builds the home page with the site and ArkVel, without breadcrumbs', function (): void {
    $seo = $this->seo->home();

    expect($seo->title)->toBe('ArkVel — Adotta un amico')
        ->and($seo->description)->toBe(__('public.meta.default_description'))
        ->and($seo->canonical)->toBe(route('home'))
        ->and($seo->locale)->toBe('it')
        ->and($seo->noindex)->toBeFalse()
        ->and($seo->breadcrumbs)->toBe([])
        ->and(graphTypes($seo))->toBe(['WebSite', 'Organization']);
});

it('builds the plain catalog as an indexable collection', function (): void {
    $seo = $this->seo->catalog(search: '', hasFacetFilters: false, page: 1, total: 12, lastPage: 1);

    expect($seo->title)->toBe('Catalogo — ArkVel')
        ->and($seo->canonical)->toBe(route('catalog'))
        ->and($seo->noindex)->toBeFalse()
        ->and(crumbs($seo))->toBe([
            ['name' => 'Home', 'url' => route('home')],
            ['name' => 'Catalogo', 'url' => route('catalog')],
        ])
        ->and(graphTypes($seo))->toBe(['CollectionPage', 'BreadcrumbList']);
});

it('keeps the page number of an unfiltered catalog page', function (): void {
    $seo = $this->seo->catalog(search: '', hasFacetFilters: false, page: 3, total: 40, lastPage: 4);

    expect($seo->canonical)->toBe(route('catalog', ['page' => 3]))
        ->and($seo->title)->toBe('Catalogo, pagina 3 — ArkVel')
        ->and($seo->noindex)->toBeFalse();
});

it('points filtered catalog pages to the base catalog', function (): void {
    $seo = $this->seo->catalog(search: '', hasFacetFilters: true, page: 2, total: 25, lastPage: 3);

    expect($seo->canonical)->toBe(route('catalog'))
        ->and($seo->title)->toBe('Catalogo — ArkVel')
        ->and($seo->noindex)->toBeFalse();
});

it('keeps internal search results and empty results out of the index', function (string $search, int $total): void {
    $seo = $this->seo->catalog(search: $search, hasFacetFilters: false, page: 1, total: $total, lastPage: 1);

    expect($seo->noindex)->toBeTrue()
        ->and($seo->canonical)->toBe(route('catalog'));
})->with([
    'search' => ['labrador', 7],
    'no results' => ['', 0],
]);

it('keeps the pages past the last one out of the index (no soft 404)', function (): void {
    $organization = Organization::factory()->verified()->create();

    expect($this->seo->catalog(search: '', hasFacetFilters: false, page: 999, total: 40, lastPage: 4)->noindex)->toBeTrue()
        ->and($this->seo->catalog(search: '', hasFacetFilters: false, page: 4, total: 40, lastPage: 4)->noindex)->toBeFalse()
        ->and($this->seo->organizations(999, 2)->noindex)->toBeTrue()
        ->and($this->seo->organizations(2, 2)->noindex)->toBeFalse()
        // Pagina base di una lista vuota: lastPage è 1, resta indicizzabile.
        ->and($this->seo->organizations(1, 1)->noindex)->toBeFalse()
        ->and($this->seo->organization($organization, 999, 1)->noindex)->toBeTrue()
        ->and($this->seo->organization($organization, 1, 1)->noindex)->toBeFalse();
});

it('builds the organization index, paged when needed', function (): void {
    expect($this->seo->organizations(1, 1))
        ->title->toBe('Enti — ArkVel')
        ->canonical->toBe(route('organizations.index'))
        ->and(crumbs($this->seo->organizations(1, 1)))->toBe([
            ['name' => 'Home', 'url' => route('home')],
            ['name' => 'Enti', 'url' => route('organizations.index')],
        ])
        ->and($this->seo->organizations(2, 2))
        ->title->toBe('Enti, pagina 2 — ArkVel')
        ->canonical->toBe(route('organizations.index', ['page' => 2]))
        ->noindex->toBeFalse();
});

it('builds an animal page from its own description', function (): void {
    $animal = Animal::factory()->available()->create([
        'name' => 'Argo',
        'description' => ['it' => 'Argo è un cane socievole che adora i bambini.', 'en' => 'Argo is a friendly dog.'],
    ]);

    $seo = $this->seo->animal($animal);

    expect($seo->title)->toBe('Argo — ArkVel')
        ->and($seo->description)->toBe('Argo è un cane socievole che adora i bambini.')
        ->and($seo->canonical)->toBe(route('animals.show', $animal))
        ->and($seo->type)->toBe(OpenGraphType::Article)
        ->and($seo->modifiedAt?->equalTo($animal->updated_at))->toBeTrue()
        ->and($seo->image)->toBeNull()
        ->and($seo->imageAlt)->toBeNull()
        ->and(crumbs($seo))->toBe([
            ['name' => 'Home', 'url' => route('home')],
            ['name' => 'Catalogo', 'url' => route('catalog')],
            ['name' => 'Argo', 'url' => route('animals.show', $animal)],
        ])
        ->and(graphTypes($seo))->toBe(['ItemPage', 'BreadcrumbList']);
});

it('describes an animal from its real data when it has no description', function (): void {
    $organization = Organization::factory()->verified()->create(['name' => 'Rifugio Aurora']);
    $animal = Animal::factory()->available()->forOrganization($organization)->create([
        'name' => 'Micia',
        'species' => Species::Cat,
        'description' => ['it' => '', 'en' => ''],
    ]);

    expect($this->seo->animal($animal)->description)->toBe('Micia (Gatto) cerca casa: in adozione presso Rifugio Aurora.');

    app()->setLocale('en');

    expect(app(PublicPageSeo::class)->animal($animal))
        ->title->toBe('Micia — ArkVel')
        ->description->toBe('Micia (Cat) is looking for a home: up for adoption at Rifugio Aurora.')
        ->locale->toBe('en');
});

it('builds an organization page with its typed node', function (): void {
    $organization = Organization::factory()->verified()->create([
        'name' => 'Canile di Lucca',
        'type' => OrganizationType::CanileComunale,
        'description' => ['it' => '', 'en' => ''],
    ]);

    $seo = $this->seo->organization($organization, 1, 1);

    expect($seo->title)->toBe('Canile di Lucca — ArkVel')
        ->and($seo->description)->toBe('Canile di Lucca (Canile comunale): scopri gli animali in adozione e come sostenerlo.')
        ->and($seo->canonical)->toBe(route('organizations.show', $organization))
        ->and(crumbs($seo))->toBe([
            ['name' => 'Home', 'url' => route('home')],
            ['name' => 'Enti', 'url' => route('organizations.index')],
            ['name' => 'Canile di Lucca', 'url' => route('organizations.show', $organization)],
        ])
        ->and(graphTypes($seo))->toBe(['AnimalShelter', 'BreadcrumbList'])
        ->and($this->seo->organization($organization, 2, 3))
        ->title->toBe('Canile di Lucca, pagina 2 — ArkVel')
        ->canonical->toBe(route('organizations.show', [$organization, 'page' => 2]))
        ->noindex->toBeFalse();
});

it('keeps the donation landings out of the index', function (string $method, string $route, string $heading): void {
    $organization = Organization::factory()->verified()->create(['name' => 'Rifugio Aurora']);

    $seo = $this->seo->{$method}($organization);

    expect($seo->noindex)->toBeTrue()
        ->and($seo->title)->toBe($heading.' — ArkVel')
        ->and($seo->canonical)->toBe(route($route, $organization))
        ->and($seo->description)->toContain('Rifugio Aurora')
        ->and(array_column(crumbs($seo), 'name'))->toBe(['Home', 'Enti', 'Rifugio Aurora', $heading])
        ->and(graphTypes($seo))->toBe(['BreadcrumbList']);
})->with([
    'thanks' => ['donationThanks', 'organizations.donate.thanks', 'Grazie per la tua donazione'],
    'cancelled' => ['donationCancelled', 'organizations.donate.cancel', 'Donazione annullata'],
]);
