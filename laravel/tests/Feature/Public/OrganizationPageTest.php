<?php

declare(strict_types=1);

use App\Models\Animal;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns 200 and lists available animals for a verified organization', function () {
    $org = Organization::factory()->verified()->create(['name' => 'Rifugio Speranza']);
    Animal::factory()->available()->forOrganization($org)->create(['name' => 'Milo']);

    $this->get(route('organizations.show', $org))
        ->assertOk()
        ->assertSee('Rifugio Speranza')
        ->assertSee('Milo');
});

it('returns 404 for a non-verified organization', function () {
    $org = Organization::factory()->pending()->create();

    $this->get(route('organizations.show', $org))->assertNotFound();
});

it('does not list the organization\'s draft animals', function () {
    $org = Organization::factory()->verified()->create();
    Animal::factory()->available()->forOrganization($org)->create(['name' => 'Disponibile']);
    Animal::factory()->draft()->forOrganization($org)->create(['name' => 'BozzaEnte']);

    $this->get(route('organizations.show', $org))
        ->assertOk()
        ->assertSee('Disponibile')
        ->assertDontSee('BozzaEnte');
});

it('renders the organizations index listing verified organizations only', function () {
    Organization::factory()->verified()->create(['name' => 'Ente Verificato']);
    Organization::factory()->pending()->create(['name' => 'Ente In Attesa']);

    $this->get(route('organizations.index'))
        ->assertOk()
        ->assertSee('Ente Verificato')
        ->assertDontSee('Ente In Attesa');
});
