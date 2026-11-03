<?php

declare(strict_types=1);

use App\Models\Animal;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns 200 for an available animal of a verified organization', function () {
    $org = Organization::factory()->verified()->create();
    $animal = Animal::factory()->available()->forOrganization($org)->create(['name' => 'Argo']);

    $this->get(route('animals.show', $animal))
        ->assertOk()
        ->assertSee('Argo');
});

it('returns 404 for a draft animal', function () {
    $org = Organization::factory()->verified()->create();
    $animal = Animal::factory()->draft()->forOrganization($org)->create();

    $this->get(route('animals.show', $animal))->assertNotFound();
});

it('returns 404 for an animal whose organization is not verified', function () {
    $org = Organization::factory()->pending()->create();
    $animal = Animal::factory()->available()->forOrganization($org)->create();

    $this->get(route('animals.show', $animal))->assertNotFound();
});

it('shows the description in the current locale', function () {
    app()->setLocale('it');

    $org = Organization::factory()->verified()->create();
    $animal = Animal::factory()->available()->forOrganization($org)->create([
        'description' => ['it' => 'Cagnolino dolcissimo', 'en' => 'A very sweet dog'],
    ]);

    $this->get(route('animals.show', $animal))
        ->assertOk()
        ->assertSee('Cagnolino dolcissimo')
        ->assertDontSee('A very sweet dog');
});

it('shows the adoption CTA linking to the account area for a guest', function () {
    $org = Organization::factory()->verified()->create();
    $animal = Animal::factory()->available()->forOrganization($org)->create();

    $this->get(route('animals.show', $animal))
        ->assertOk()
        ->assertSee(__('public.detail.cta_button'))
        ->assertSee('href="/account"', false);
});
