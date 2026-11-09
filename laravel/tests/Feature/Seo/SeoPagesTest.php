<?php

declare(strict_types=1);

use App\Enums\OrganizationType;
use App\Enums\Species;
use App\Http\Middleware\SetLocale;
use App\Models\Animal;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Testing\TestResponse;

/*
 | SEO delle pagine pubbliche reali (componenti Livewire full-page) attraverso un layout
 | di prova che include i partial come la shell: meta, canonical, robots, Open Graph,
 | JSON-LD e breadcrumb. I partial da soli: SeoPartialsTest.
 */

uses(RefreshDatabase::class);

beforeEach(function (): void {
    View::addNamespace('seo-fixtures', __DIR__.'/fixtures');
    config(['livewire.component_layout' => 'seo-fixtures::layout']);
});

/**
 * @return array<string, mixed>
 */
function jsonLdOf(TestResponse $response): array
{
    expect(preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', (string) $response->getContent(), $matches))->toBe(1);

    return json_decode($matches[1][0], true, flags: JSON_THROW_ON_ERROR);
}

/**
 * @param  array<string, mixed>  $document
 * @return list<string>
 */
function jsonLdTypes(array $document): array
{
    return array_column($document['@graph'], '@type');
}

/**
 * @param  array<string, mixed>  $document
 * @return array<string, mixed>
 */
function jsonLdNode(array $document, string $type): array
{
    $nodes = array_values(array_filter($document['@graph'], static fn (array $node): bool => $node['@type'] === $type));

    expect($nodes)->toHaveCount(1);

    return $nodes[0];
}

it('renders the home meta, Open Graph and site structured data', function (): void {
    $response = $this->get(route('home'))->assertOk()
        ->assertSee('<title>ArkVel — Adotta un amico</title>', escape: false)
        ->assertSee('<meta name="description" content="'.e(__('public.meta.default_description')).'">', escape: false)
        ->assertSee('<link rel="canonical" href="'.route('home').'">', escape: false)
        ->assertSee('<meta property="og:site_name" content="ArkVel">', escape: false)
        ->assertSee('<meta property="og:locale" content="it_IT">', escape: false)
        ->assertSee('<meta property="og:type" content="website">', escape: false)
        ->assertSee('<meta property="og:url" content="'.route('home').'">', escape: false)
        ->assertSee('<meta name="twitter:card" content="summary">', escape: false)
        ->assertDontSee('name="robots"', escape: false)
        ->assertDontSee('og:image', escape: false)
        ->assertDontSee('data-breadcrumb', escape: false);

    $document = jsonLdOf($response);

    expect($document['@context'])->toBe('https://schema.org')
        ->and(jsonLdTypes($document))->toBe(['WebSite', 'Organization'])
        ->and(jsonLdNode($document, 'WebSite'))->not->toHaveKey('potentialAction');
});

it('translates titles and og:locale with the session language', function (): void {
    $organization = Organization::factory()->verified()->create(['name' => 'Rifugio Aurora']);

    $this->withSession([SetLocale::SESSION_KEY => 'en'])
        ->get(route('organizations.donate.thanks', $organization))
        ->assertSee('<title>Thank you for your donation — ArkVel</title>', escape: false)
        ->assertSee('<meta property="og:locale" content="en_GB">', escape: false)
        ->assertSee('aria-label="Breadcrumb"', escape: false);

    $this->withSession([SetLocale::SESSION_KEY => 'it'])
        ->get(route('organizations.donate.thanks', $organization))
        ->assertSee('<title>Grazie per la tua donazione — ArkVel</title>', escape: false)
        ->assertSee('aria-label="Percorso di navigazione"', escape: false);
});

it('describes an animal page with its photo, an ItemPage and breadcrumbs', function (): void {
    Storage::fake('public');

    $organization = Organization::factory()->verified()->create(['name' => 'Canile di Lucca', 'type' => OrganizationType::CanileComunale]);
    $animal = Animal::factory()->available()->forOrganization($organization)->create([
        'name' => 'Argo',
        'description' => ['it' => 'Argo adora le passeggiate.', 'en' => 'Argo loves walks.'],
    ]);
    $animal->addMedia(UploadedFile::fake()->image('argo.jpg', 800, 600))->toMediaCollection('photos');

    $response = $this->get(route('animals.show', $animal).'?utm_source=newsletter&fbclid=abc')->assertOk()
        ->assertSee('<title>Argo — ArkVel</title>', escape: false)
        ->assertSee('<meta name="description" content="Argo adora le passeggiate.">', escape: false)
        ->assertSee('<link rel="canonical" href="'.route('animals.show', $animal).'">', escape: false)
        ->assertSee('<meta property="og:type" content="article">', escape: false)
        ->assertSee('<meta property="og:image" content="http', escape: false)
        ->assertSee('<meta property="og:image:alt" content="Foto di Argo">', escape: false)
        ->assertSee('<meta property="article:modified_time"', escape: false)
        ->assertSee('<meta name="twitter:card" content="summary_large_image">', escape: false)
        ->assertDontSee('utm_source', escape: false)
        ->assertSee('aria-current="page"', escape: false);

    $document = jsonLdOf($response);
    $page = jsonLdNode($document, 'ItemPage');
    $breadcrumbs = jsonLdNode($document, 'BreadcrumbList');

    expect(jsonLdTypes($document))->toBe(['ItemPage', 'BreadcrumbList'])
        ->and($page['mainEntity']['@type'])->toBe('Thing')
        ->and($page['mainEntity']['image'])->toStartWith('http')
        ->and($page['sourceOrganization']['@type'])->toBe('AnimalShelter')
        ->and(array_column($breadcrumbs['itemListElement'], 'name'))->toBe(['Home', 'Catalogo', 'Argo'])
        ->and(array_column($breadcrumbs['itemListElement'], 'position'))->toBe([1, 2, 3]);
});

it('describes an organization page without leaking its non-public animals', function (): void {
    $organization = Organization::factory()->verified()->create([
        'name' => 'Rifugio Aurora',
        'type' => OrganizationType::Rifugio,
        'city' => 'Bologna',
        'address_line' => 'Via Segreta 12',
    ]);
    Animal::factory()->available()->forOrganization($organization)->create(['name' => 'Argo']);
    Animal::factory()->draft()->forOrganization($organization)->create(['name' => 'Bozzolo']);

    $response = $this->get(route('organizations.show', $organization))->assertOk()
        ->assertSee('<title>Rifugio Aurora — ArkVel</title>', escape: false)
        ->assertSee('<link rel="canonical" href="'.route('organizations.show', $organization).'">', escape: false);

    $document = jsonLdOf($response);
    $json = json_encode($document);

    expect(jsonLdTypes($document))->toBe(['AnimalShelter', 'BreadcrumbList'])
        ->and(jsonLdNode($document, 'AnimalShelter')['address']['addressLocality'])->toBe('Bologna')
        ->and($json)->not->toContain('Bozzolo')->not->toContain('Via Segreta');
});

it('cannot break out of the JSON-LD script with content written by an organization', function (): void {
    $organization = Organization::factory()->verified()->create([
        'name' => 'Aurora</script><script>alert(1)</script>',
        'type' => OrganizationType::Associazione,
    ]);

    $response = $this->get(route('organizations.show', $organization))->assertOk()
        ->assertDontSee('</script><script>alert(1)', escape: false);

    expect(jsonLdNode(jsonLdOf($response), 'NGO')['name'])->toBe('Aurora</script><script>alert(1)</script>');
});

it('points filtered catalog pages to the base catalog and keeps searches out of the index', function (): void {
    $organization = Organization::factory()->verified()->create();
    Animal::factory()->available()->forOrganization($organization)->create(['name' => 'Argo', 'species' => Species::Dog]);

    $canonical = '<link rel="canonical" href="'.route('catalog').'">';

    $this->get(route('catalog', ['species' => 'dog', 'utm_campaign' => 'x']))->assertOk()
        ->assertSee($canonical, escape: false)
        ->assertDontSee('name="robots"', escape: false);

    $this->get(route('catalog', ['q' => 'Argo']))->assertOk()
        ->assertSee($canonical, escape: false)
        ->assertSee('<meta name="robots" content="noindex, follow">', escape: false);

    $this->get(route('catalog', ['species' => 'cat']))->assertOk()
        ->assertSee('<meta name="robots" content="noindex, follow">', escape: false);
});

it('gives the catalog and the organization index a collection page and breadcrumbs', function (string $route, string $title, string $crumb): void {
    Animal::factory()->available()->forOrganization(Organization::factory()->verified()->create())->create();

    $response = $this->get(route($route))->assertOk()
        ->assertSee('<title>'.$title.'</title>', escape: false)
        ->assertSee('<link rel="canonical" href="'.route($route).'">', escape: false)
        ->assertSee('data-breadcrumb', escape: false);

    $document = jsonLdOf($response);

    expect(jsonLdTypes($document))->toBe(['CollectionPage', 'BreadcrumbList'])
        ->and(array_column(jsonLdNode($document, 'BreadcrumbList')['itemListElement'], 'name'))->toBe(['Home', $crumb]);
})->with([
    'catalog' => ['catalog', 'Catalogo — ArkVel', 'Catalogo'],
    'organizations' => ['organizations.index', 'Enti — ArkVel', 'Enti'],
]);

it('keeps the donation landings out of the index', function (string $route, string $title): void {
    $organization = Organization::factory()->verified()->create(['name' => 'Rifugio Aurora']);

    $this->get(route($route, $organization))->assertOk()
        ->assertSee('<title>'.$title.' — ArkVel</title>', escape: false)
        ->assertSee('<meta name="robots" content="noindex, follow">', escape: false)
        ->assertSee('<link rel="canonical" href="'.route($route, $organization).'">', escape: false);
})->with([
    'thanks' => ['organizations.donate.thanks', 'Grazie per la tua donazione'],
    'cancelled' => ['organizations.donate.cancel', 'Donazione annullata'],
]);

it('renders no SEO for pages that do not exist publicly', function (): void {
    $animal = Animal::factory()->draft()->forOrganization(Organization::factory()->verified()->create())->create(['name' => 'Bozzolo']);

    $this->get(route('animals.show', $animal))->assertNotFound()->assertDontSee('Bozzolo');
});

it('keeps the pages past the last one out of the index', function (string $page): void {
    $organization = Organization::factory()->verified()->create(['slug' => 'rifugio-aurora']);
    Animal::factory()->available()->forOrganization($organization)->create();

    $url = match ($page) {
        'catalog' => route('catalog', ['page' => 999]),
        'organizations' => route('organizations.index', ['page' => 999]),
        'organization' => route('organizations.show', [$organization, 'page' => 999]),
    };

    $this->get($url)->assertOk()
        ->assertSee('<meta name="robots" content="noindex, follow">', escape: false);
})->with(['catalog', 'organizations', 'organization']);

it('keeps the existing pages of a list indexable', function (): void {
    $organization = Organization::factory()->verified()->create();
    Animal::factory()->count(13)->available()->forOrganization($organization)->create();

    $this->get(route('catalog', ['page' => 2]))->assertOk()
        ->assertSee('<link rel="canonical" href="'.route('catalog', ['page' => 2]).'">', escape: false)
        ->assertDontSee('name="robots"', escape: false);

    $this->get(route('organizations.show', [$organization, 'page' => 2]))->assertOk()
        ->assertDontSee('name="robots"', escape: false);
});
