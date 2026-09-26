<?php

declare(strict_types=1);

use App\Filament\Adopter\Pages\Favorites;
use App\Models\Animal;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('adopter'));
});

afterEach(function () {
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

/**
 * Un animale pubblicamente visibile con un nome dato.
 */
function favoritableAnimal(string $name): Animal
{
    $org = Organization::factory()->verified()->create();

    return Animal::factory()->available()->forOrganization($org)->create(['name' => $name]);
}

it('lets an adopter reach the favorites page', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Favorites::class)
        ->assertOk();
});

it('shows an empty state when the adopter has no favorites', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Favorites::class)
        ->assertSee(__('public.favorites.empty_title'));
});

it('lists only the current users favorites, not another users', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $mine = favoritableAnimal('MioPreferito');
    $theirs = favoritableAnimal('LoroPreferito');

    $owner->favorites()->attach($mine->getKey());
    $other->favorites()->attach($theirs->getKey());

    Livewire::actingAs($owner)
        ->test(Favorites::class)
        ->assertSee('MioPreferito')
        ->assertDontSee('LoroPreferito');
});

it('removes a favorite detaching only the current users pivot row', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $animal = favoritableAnimal('Condiviso');

    // Both users favorited the same animal.
    $owner->favorites()->attach($animal->getKey());
    $other->favorites()->attach($animal->getKey());

    Livewire::actingAs($owner)
        ->test(Favorites::class)
        ->assertSee('Condiviso')
        ->call('removeFavorite', $animal->getKey())
        ->assertDontSee('Condiviso');

    // Only the owner's row is gone; the other user still has it.
    expect($owner->favorites()->whereKey($animal->getKey())->exists())->toBeFalse()
        ->and($other->favorites()->whereKey($animal->getKey())->exists())->toBeTrue();
});

it('orders favorites from the most recently added', function () {
    $user = User::factory()->create();

    $older = favoritableAnimal('PiuVecchio');
    $newer = favoritableAnimal('PiuRecente');

    $user->favorites()->attach($older->getKey(), ['created_at' => now()->subDay(), 'updated_at' => now()->subDay()]);
    $user->favorites()->attach($newer->getKey(), ['created_at' => now(), 'updated_at' => now()]);

    $favorites = Livewire::actingAs($user)
        ->test(Favorites::class)
        ->instance()
        ->getFavoritesProperty();

    expect($favorites->first()?->name)->toBe('PiuRecente')
        ->and($favorites->last()?->name)->toBe('PiuVecchio');
});
