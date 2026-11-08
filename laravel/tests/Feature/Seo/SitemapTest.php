<?php

declare(strict_types=1);

use App\Http\Controllers\SitemapController;
use App\Models\Animal;
use App\Models\Organization;
use App\Support\Seo\Sitemap\SitemapXml;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

/**
 * @return array<string, string|null> loc => lastmod
 */
function sitemapUrls(TestResponse $response): array
{
    $document = simplexml_load_string((string) $response->getContent());

    expect($document)->not->toBeFalse()
        ->and($document->getName())->toBe('urlset')
        ->and($document->getNamespaces())->toBe(['' => SitemapXml::NAMESPACE]);

    $urls = [];

    foreach ($document->url as $url) {
        $urls[(string) $url->loc] = isset($url->lastmod) ? (string) $url->lastmod : null;
    }

    return $urls;
}

it('lists the public pages as valid XML', function (): void {
    $response = $this->get(route('seo.sitemap'))->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

    expect(sitemapUrls($response))->toBe([
        route('home') => null,
        route('catalog') => null,
        route('organizations.index') => null,
    ]);
});

it('lists exactly what the public pages show, nothing more', function (): void {
    $verified = Organization::factory()->verified()->create();
    $pending = Organization::factory()->pending()->create();
    $suspended = Organization::factory()->suspended()->create();

    $visible = Animal::factory()->available()->forOrganization($verified)->create();
    $draft = Animal::factory()->draft()->forOrganization($verified)->create();
    $adopted = Animal::factory()->adopted()->forOrganization($verified)->create();
    $trashed = Animal::factory()->available()->forOrganization($verified)->create();
    $trashed->delete();
    $ofPending = Animal::factory()->available()->forOrganization($pending)->create();

    $urls = array_keys(sitemapUrls($this->get(route('seo.sitemap'))));

    expect($urls)
        ->toContain(route('animals.show', $visible), route('organizations.show', $verified))
        ->not->toContain(route('animals.show', $draft))
        ->not->toContain(route('animals.show', $adopted))
        ->not->toContain(route('animals.show', $trashed))
        ->not->toContain(route('animals.show', $ofPending))
        ->not->toContain(route('organizations.show', $pending))
        ->not->toContain(route('organizations.show', $suspended))
        ->not->toContain(route('organizations.donate.thanks', $verified))
        ->and($urls)->toHaveCount(5);

    // Coerenza con le pagine: ciò che la sitemap esclude risponde 404.
    $this->get(route('animals.show', $draft))->assertNotFound();
    $this->get(route('organizations.show', $pending))->assertNotFound();
    $this->get(route('animals.show', $visible))->assertOk();
});

it('dates animals by their last update and organizations by their latest visible change', function (): void {
    $organization = Organization::factory()->verified()->create();
    $organization->forceFill(['updated_at' => Carbon::parse('2026-01-10 08:00:00')])->saveQuietly();

    $animal = Animal::factory()->available()->forOrganization($organization)->create();
    $animal->forceFill(['updated_at' => Carbon::parse('2026-03-05 12:00:00')])->saveQuietly();

    $quiet = Organization::factory()->verified()->create();
    $quiet->forceFill(['updated_at' => Carbon::parse('2026-02-01 09:00:00')])->saveQuietly();
    $hidden = Animal::factory()->draft()->forOrganization($quiet)->create();
    $hidden->forceFill(['updated_at' => Carbon::parse('2026-06-01 09:00:00')])->saveQuietly();

    $urls = sitemapUrls($this->get(route('seo.sitemap')));

    expect($urls[route('animals.show', $animal)])->toBe('2026-03-05T12:00:00+00:00')
        ->and($urls[route('organizations.show', $organization)])->toBe('2026-03-05T12:00:00+00:00')
        ->and($urls[route('organizations.show', $quiet)])->toBe('2026-02-01T09:00:00+00:00');
});

it('is cached for a short while', function (): void {
    $this->get(route('seo.sitemap'))->assertOk();

    expect(Cache::has(SitemapController::CACHE_KEY))->toBeTrue();

    $animal = Animal::factory()->available()->forOrganization(Organization::factory()->verified()->create())->create();

    $this->get(route('seo.sitemap'))->assertDontSee(route('animals.show', $animal), escape: false);

    Cache::forget(SitemapController::CACHE_KEY);

    $this->get(route('seo.sitemap'))->assertSee(route('animals.show', $animal), escape: false);
});

it('serves robots.txt with the private areas blocked and the absolute sitemap URL', function (): void {
    $this->get(route('seo.robots'))->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('User-agent: *')
        ->assertSee('Disallow: /account')
        ->assertSee('Disallow: /manage')
        ->assertSee('Disallow: /admin')
        ->assertDontSee('_styleguide')
        ->assertSee('Sitemap: '.url('/sitemap.xml'));

    expect(route('seo.robots'))->toBe(url('/robots.txt'))
        ->and(file_exists(public_path('robots.txt')))->toBeFalse();
});

it('cannot be poisoned through the Host header: URLs come from APP_URL, the cache is host-independent', function (): void {
    $animal = Animal::factory()->available()->forOrganization(Organization::factory()->verified()->create())->create();
    $animalUrl = route('animals.show', $animal);

    // Prima richiesta (quella che riempie la cache) con un host arbitrario.
    $poisoned = $this->get('http://evil.example/sitemap.xml')->assertOk();

    expect(Cache::has(SitemapController::CACHE_KEY))->toBeTrue()
        ->and((string) $poisoned->getContent())->not->toContain('evil.example');

    // La richiesta normale successiva legge la stessa cache: nessuna traccia dell'host.
    $urls = array_keys(sitemapUrls($this->get(route('seo.sitemap'))->assertOk()));

    expect(implode("\n", $urls))->not->toContain('evil.example')
        ->and($urls)->toContain($animalUrl)
        ->and($urls)->each->toStartWith(rtrim((string) config('app.url'), '/'));
});

it('points robots.txt to the sitemap on APP_URL whatever the Host header', function (): void {
    $this->get('http://evil.example/robots.txt')->assertOk()
        ->assertDontSee('evil.example')
        ->assertSee('Sitemap: '.rtrim((string) config('app.url'), '/').'/sitemap.xml');
});

it('serves sitemap and robots.txt without session, cookies or locale', function (string $route): void {
    $response = $this->get(route($route))->assertOk();

    expect($response->headers->getCookies())->toBe([])
        ->and($response->headers->has('Set-Cookie'))->toBeFalse();
})->with(['seo.sitemap', 'seo.robots']);
