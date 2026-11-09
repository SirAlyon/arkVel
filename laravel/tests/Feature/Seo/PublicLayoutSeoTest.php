<?php

declare(strict_types=1);

use App\Models\Animal;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 | Integrazione con il layout pubblico vero (layouts/app): i partial SEO sono inclusi una
 | sola volta, senza doppioni dei fallback di <title> e description.
 */

uses(RefreshDatabase::class);

it('prints each SEO tag once in the public layout', function (string $page): void {
    $organization = Organization::factory()->verified()->create();
    $animal = Animal::factory()->available()->forOrganization($organization)->create(['name' => 'Argo']);

    $url = match ($page) {
        'home' => route('home'),
        'catalog' => route('catalog'),
        'animal' => route('animals.show', $animal),
        'organization' => route('organizations.show', $organization),
    };

    $html = (string) $this->get($url)->assertOk()->getContent();

    expect(substr_count($html, '<title>'))->toBe(1)
        ->and(substr_count($html, '<meta name="description"'))->toBe(1)
        ->and(substr_count($html, '<link rel="canonical"'))->toBe(1)
        ->and(substr_count($html, '<meta property="og:title"'))->toBe(1)
        ->and(substr_count($html, 'application/ld+json'))->toBe(1)
        ->and(substr_count($html, 'data-breadcrumb'))->toBe($page === 'home' ? 0 : 1);
})->with(['home', 'catalog', 'animal', 'organization']);

it('shows the breadcrumb of an animal page inside the main content', function (): void {
    $animal = Animal::factory()->available()->forOrganization(Organization::factory()->verified()->create())->create(['name' => 'Argo']);

    $this->get(route('animals.show', $animal))->assertOk()
        ->assertSeeInOrder(['<main', 'data-breadcrumb', 'href="'.route('catalog').'"', 'aria-current="page"', 'Argo'], escape: false);
});
