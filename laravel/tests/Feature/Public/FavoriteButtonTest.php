<?php

declare(strict_types=1);

use App\Livewire\AnimalDetail;
use App\Livewire\FavoriteButton;
use App\Models\Animal;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Animale pubblicamente visibile (Available + ente Verified): l'unico contesto
 * in cui il toggle è offerto (la card compare solo per animali visibili).
 */
function visibleAnimal(): Animal
{
    $org = Organization::factory()->verified()->create();

    return Animal::factory()->available()->forOrganization($org)->create();
}

it('redirects a guest to the adopter login and creates no favorite', function () {
    $animal = visibleAnimal();

    Livewire::test(FavoriteButton::class, ['animal' => $animal])
        ->assertSet('favorited', false)
        ->call('toggle')
        ->assertRedirect(route('filament.adopter.auth.login'));

    expect($animal->favoritedBy()->count())->toBe(0);
});

it('lets an authenticated user favorite then unfavorite an animal', function () {
    $user = User::factory()->create();
    $animal = visibleAnimal();

    $component = Livewire::actingAs($user)
        ->test(FavoriteButton::class, ['animal' => $animal])
        ->assertSet('favorited', false);

    // Add
    $component->call('toggle')->assertSet('favorited', true);
    expect($user->favorites()->whereKey($animal->getKey())->exists())->toBeTrue();

    // Remove
    $component->call('toggle')->assertSet('favorited', false);
    expect($user->favorites()->whereKey($animal->getKey())->exists())->toBeFalse();
});

it('reflects the initial favorited state', function () {
    $user = User::factory()->create();
    $animal = visibleAnimal();
    $user->favorites()->attach($animal->getKey());

    Livewire::actingAs($user)
        ->test(FavoriteButton::class, ['animal' => $animal])
        ->assertSet('favorited', true);
});

it('respects the unique constraint: toggling on twice keeps a single pivot row', function () {
    $user = User::factory()->create();
    $animal = visibleAnimal();

    // First toggle attaches; toggle() flips the flag, so a real second "add"
    // must go through a fresh component whose initial state is already true.
    Livewire::actingAs($user)
        ->test(FavoriteButton::class, ['animal' => $animal])
        ->call('toggle')
        ->assertSet('favorited', true);

    // Guard: a direct duplicate attach must not create a second row (DB unique).
    expect(
        fn () => $user->favorites()->attach($animal->getKey())
    )->toThrow(QueryException::class);

    expect($user->favorites()->count())->toBe(1);
});

it('only affects the current users pivot rows when toggling', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $animal = visibleAnimal();

    // The other user already favorited the same animal.
    $other->favorites()->attach($animal->getKey());

    // The owner toggles: their own row is created, the other user is untouched.
    Livewire::actingAs($owner)
        ->test(FavoriteButton::class, ['animal' => $animal])
        ->call('toggle')
        ->assertSet('favorited', true);

    expect($owner->favorites()->whereKey($animal->getKey())->exists())->toBeTrue()
        ->and($other->favorites()->whereKey($animal->getKey())->exists())->toBeTrue();

    // The owner toggles off: only their row is detached.
    Livewire::actingAs($owner)
        ->test(FavoriteButton::class, ['animal' => $animal])
        ->call('toggle')
        ->assertSet('favorited', false);

    expect($owner->favorites()->whereKey($animal->getKey())->exists())->toBeFalse()
        ->and($other->favorites()->whereKey($animal->getKey())->exists())->toBeTrue();
});

it('renders the favorite toggle on a publicly visible animal detail page', function () {
    $animal = visibleAnimal();

    Livewire::test(AnimalDetail::class, ['animal' => $animal])
        ->assertSeeLivewire(FavoriteButton::class);
});
