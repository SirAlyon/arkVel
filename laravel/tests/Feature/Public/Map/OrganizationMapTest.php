<?php

declare(strict_types=1);

use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 | Pagina dell'ente (UI-7): «Dove si trova» con la località e una piccola mappa che mostra
 | un cerchio sulla zona approssimata. Mai coordinate precise né indirizzo nella risposta.
 */

uses(RefreshDatabase::class);

it('shows the approximate area of the organization with its location', function (): void {
    $organization = Organization::factory()->verified()->create([
        'name' => 'Rifugio Aurora',
        'city' => 'Roma',
        'region' => 'Lazio',
        'address_line' => 'Via dei Mille 12',
        'postal_code' => '00185',
        'latitude' => 41.8902102,
        'longitude' => 12.4922309,
    ]);

    $response = $this->get(route('organizations.show', $organization))->assertOk();

    $response->assertSeeInOrder(['data-organization-animals', 'data-organization-map', 'Dove si trova', 'Roma, Lazio', 'Il cerchio indica la zona', 'data-ui-map'], escape: false)
        ->assertSee('Mappa della zona di Rifugio Aurora')
        ->assertSee('id="organization-map-instructions"', escape: false)
        ->assertSee('contributori di OpenStreetMap')
        ->assertSee('data-map-markers="[]"', escape: false);

    $html = (string) $response->getContent();

    expect($html)->toContain('41.89')
        ->toContain('12.49')
        ->not->toContain('41.89021')
        ->not->toContain('12.49223')
        ->not->toContain('Via dei Mille')
        ->not->toContain('00185');
});

it('has no map without a position', function (): void {
    $organization = Organization::factory()->verified()->create(['latitude' => null, 'longitude' => null]);

    $this->get(route('organizations.show', $organization))
        ->assertOk()
        ->assertDontSee('data-organization-map', escape: false)
        ->assertDontSee('data-ui-map', escape: false);
});

it('is translated in English', function (): void {
    $organization = Organization::factory()->verified()->create(['name' => 'Aurora', 'latitude' => 45.07, 'longitude' => 7.68]);

    $this->withSession(['locale' => 'en'])
        ->get(route('organizations.show', $organization))
        ->assertOk()
        ->assertSee('Where it is')
        ->assertSee('Map of the area of Aurora')
        ->assertSee('OpenStreetMap contributors');
});
