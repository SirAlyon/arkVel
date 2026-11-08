<?php

declare(strict_types=1);

use App\Enums\OrganizationType;
use App\Models\Animal;
use App\Models\Organization;
use App\Support\Seo\Breadcrumb;
use App\Support\Seo\SchemaOrg;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->schema = new SchemaOrg;
});

it('describes the site, without the retired SearchAction', function (): void {
    $website = $this->schema->website('it');

    expect($website)
        ->toMatchArray([
            '@type' => 'WebSite',
            '@id' => route('home').'#website',
            'url' => route('home'),
            'name' => 'ArkVel',
            'inLanguage' => 'it',
            'publisher' => ['@id' => route('home').'#organization'],
        ])
        ->not->toHaveKey('potentialAction');
});

it('describes ArkVel as the publishing organization, without an invented logo', function (): void {
    expect($this->schema->siteOrganization())
        ->toMatchArray(['@type' => 'Organization', '@id' => route('home').'#organization', 'name' => 'ArkVel', 'url' => route('home')])
        ->not->toHaveKey('logo');
});

it('numbers breadcrumb items from one', function (): void {
    $list = $this->schema->breadcrumbList([
        new Breadcrumb('Home', 'https://arkvel.test'),
        new Breadcrumb('Enti', 'https://arkvel.test/enti'),
    ]);

    expect($list)->toBe([
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => 'https://arkvel.test'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Enti', 'item' => 'https://arkvel.test/enti'],
        ],
    ]);
});

it('describes a listing page as part of the site', function (): void {
    expect($this->schema->collectionPage('Catalogo — ArkVel', route('catalog'), 'Animali', 'en'))->toBe([
        '@type' => 'CollectionPage',
        '@id' => route('catalog'),
        'url' => route('catalog'),
        'name' => 'Catalogo — ArkVel',
        'description' => 'Animali',
        'inLanguage' => 'en',
        'isPartOf' => ['@id' => route('home').'#website'],
    ]);
});

it('maps every organization type to an honest schema.org type', function (OrganizationType $type, string $schemaType): void {
    expect(SchemaOrg::organizationType($type))->toBe($schemaType);
})->with([
    [OrganizationType::CanileComunale, 'AnimalShelter'],
    [OrganizationType::CanilePrivato, 'AnimalShelter'],
    [OrganizationType::Rifugio, 'AnimalShelter'],
    [OrganizationType::Associazione, 'NGO'],
    [OrganizationType::Societa, 'Organization'],
]);

it('publishes only an approximate address and the contacts shown on the page', function (): void {
    $organization = Organization::factory()->verified()->create([
        'type' => OrganizationType::Rifugio,
        'name' => 'Rifugio Aurora',
        'description' => ['it' => '<p>Ci prendiamo cura di cani e gatti.</p>', 'en' => 'We look after dogs and cats.'],
        'city' => 'Bologna',
        'region' => 'Emilia-Romagna',
        'country' => 'IT',
        'address_line' => 'Via Segreta 12',
        'postal_code' => '40100',
        'email' => 'ciao@aurora.test',
        'phone' => '+39 051 000000',
        'website' => 'https://aurora.test',
    ]);

    $node = $this->schema->organization($organization, 'it');

    expect($node)->toBe([
        '@type' => 'AnimalShelter',
        '@id' => route('organizations.show', $organization).'#organization',
        'name' => 'Rifugio Aurora',
        'url' => route('organizations.show', $organization),
        'description' => 'Ci prendiamo cura di cani e gatti.',
        'address' => [
            '@type' => 'PostalAddress',
            'addressLocality' => 'Bologna',
            'addressRegion' => 'Emilia-Romagna',
            'addressCountry' => 'IT',
        ],
        'email' => 'ciao@aurora.test',
        'telephone' => '+39 051 000000',
        'sameAs' => ['https://aurora.test'],
    ])->and(json_encode($node))->not->toContain('Via Segreta')->not->toContain('40100');
});

it('omits empty organization fields instead of publishing blanks', function (): void {
    $organization = Organization::factory()->verified()->create([
        'type' => OrganizationType::Associazione,
        'description' => ['it' => '', 'en' => ''],
        'city' => null,
        'region' => null,
        'country' => 'IT',
        'email' => null,
        'phone' => null,
        'website' => 'non-un-url',
    ]);

    expect($this->schema->organization($organization, 'it'))
        ->toHaveKeys(['@type', '@id', 'name', 'url'])
        ->not->toHaveKeys(['description', 'email', 'telephone', 'sameAs'])
        ->toHaveKey('@type', 'NGO')
        ->toHaveKey('address', ['@type' => 'PostalAddress', 'addressCountry' => 'IT']);
});

it('links only http(s) websites with sameAs', function (string $website, bool $linked): void {
    $organization = Organization::factory()->verified()->create(['website' => $website]);

    $node = $this->schema->organization($organization, 'it');

    expect(($node['sameAs'] ?? null) === [$website])->toBe($linked);
})->with([
    'https' => ['https://aurora.test', true],
    'http' => ['http://aurora.test/chi-siamo', true],
    'javascript' => ['javascript://aurora.test/%0Aalert(1)', false],
    'ftp' => ['ftp://aurora.test/file', false],
    'mailto' => ['mailto:info@aurora.test', false],
]);

it('describes an animal page as an ItemPage about a Thing, with no product or price', function (): void {
    $organization = Organization::factory()->verified()->create(['type' => OrganizationType::CanileComunale, 'name' => 'Canile di Lucca']);
    $animal = Animal::factory()->available()->forOrganization($organization)->create([
        'name' => 'Argo',
        'description' => ['it' => 'Argo ama le passeggiate.', 'en' => 'Argo loves walks.'],
    ]);
    $canonical = route('animals.show', $animal);

    $node = $this->schema->animalPage($animal, $canonical, 'Meta di Argo', 'https://arkvel.test/argo.jpg', 'en');

    expect($node)->toMatchArray([
        '@type' => 'ItemPage',
        '@id' => $canonical,
        'url' => $canonical,
        'name' => 'Argo',
        'description' => 'Meta di Argo',
        'inLanguage' => 'en',
        'isPartOf' => ['@id' => route('home').'#website'],
        'dateModified' => $animal->updated_at?->toIso8601String(),
        'primaryImageOfPage' => ['@type' => 'ImageObject', 'url' => 'https://arkvel.test/argo.jpg'],
        'mainEntity' => ['@type' => 'Thing', 'name' => 'Argo', 'description' => 'Argo loves walks.', 'image' => 'https://arkvel.test/argo.jpg'],
        'sourceOrganization' => [
            '@type' => 'AnimalShelter',
            '@id' => route('organizations.show', $organization).'#organization',
            'name' => 'Canile di Lucca',
            'url' => route('organizations.show', $organization),
        ],
    ])->and(json_encode($node))->not->toContain('Product')->not->toContain('Offer')->not->toContain('price');
});

it('leaves the image out of an animal page without photos', function (): void {
    $animal = Animal::factory()->available()->create(['description' => ['it' => '', 'en' => '']]);

    $node = $this->schema->animalPage($animal, route('animals.show', $animal), 'Meta', null, 'it');

    expect($node)->not->toHaveKey('primaryImageOfPage')
        ->and($node['mainEntity'])->toBe(['@type' => 'Thing', 'name' => $animal->name]);
});
