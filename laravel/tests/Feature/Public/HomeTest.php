<?php

declare(strict_types=1);

use App\Livewire\Home;
use App\Models\Animal;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('renders the home page', function () {
    $this->get(route('home'))->assertOk()->assertSeeLivewire(Home::class);
});

it('shows a featured available animal from a verified organization', function () {
    $organization = Organization::factory()->verified()->create();
    $animal = Animal::factory()
        ->available()
        ->featured()
        ->forOrganization($organization)
        ->create(['name' => 'Fiocco']);

    Livewire::test(Home::class)->assertSee('Fiocco');
    expect($animal->fresh()?->name)->toBe('Fiocco');
});

it('does not show draft animals', function () {
    $organization = Organization::factory()->verified()->create();
    Animal::factory()->draft()->featured()->forOrganization($organization)->create(['name' => 'BozzaSegreta']);

    Livewire::test(Home::class)->assertDontSee('BozzaSegreta');
});

it('does not show animals of unverified organizations', function () {
    $pending = Organization::factory()->pending()->create();
    Animal::factory()->available()->featured()->forOrganization($pending)->create(['name' => 'NascostoPending']);

    Livewire::test(Home::class)->assertDontSee('NascostoPending');
});
