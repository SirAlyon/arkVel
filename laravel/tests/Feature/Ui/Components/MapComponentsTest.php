<?php

declare(strict_types=1);

/*
 | Styleguide · gruppo "map" (solo local): x-ui.map con segnaposto e area, x-ui.map.list,
 | x-ui.view-switch.
 */

beforeEach(function (): void {
    app()->detectEnvironment(static fn (): string => 'local');
});

it('documents the map components', function (): void {
    $this->get(route('styleguide.group', 'map'))
        ->assertOk()
        ->assertSeeInOrder(['id="map"', 'data-demo="map-markers"', 'data-demo="map-area"', 'id="map-list"', 'data-demo="map-list"', 'id="view-switch"', 'data-demo="view-switch"'], escape: false)
        ->assertSee('Mappa di esempio degli enti')
        ->assertSee('Posizione non disponibile')
        ->assertSee('contributori di OpenStreetMap')
        ->assertSee('aria-current="true"', escape: false);
});

it('is translated in English', function (): void {
    $this->withSession(['locale' => 'en'])
        ->get(route('styleguide.group', 'map'))
        ->assertOk()
        ->assertSee('Sample map of organizations')
        ->assertSee('Location not available')
        ->assertSee('OpenStreetMap contributors');
});
