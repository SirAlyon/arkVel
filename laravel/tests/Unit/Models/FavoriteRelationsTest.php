<?php

declare(strict_types=1);

use App\Models\Animal;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function ownedAnimal(): Animal
{
    $org = Organization::factory()->verified()->create();

    return Animal::factory()->available()->forOrganization($org)->create();
}

it('links a user to their favorited animals and back', function () {
    $user = User::factory()->create();
    $animal = ownedAnimal();

    $user->favorites()->attach($animal->getKey());

    expect($user->favorites()->whereKey($animal->getKey())->exists())->toBeTrue()
        ->and($animal->favoritedBy()->whereKey($user->getKey())->exists())->toBeTrue();
});

it('exposes hasFavorited as a trivial ownership predicate', function () {
    $user = User::factory()->create();
    $animal = ownedAnimal();

    expect($user->hasFavorited($animal))->toBeFalse();

    $user->favorites()->attach($animal->getKey());

    expect($user->fresh()->hasFavorited($animal))->toBeTrue();
});

it('keeps favorites carrying pivot timestamps', function () {
    $user = User::factory()->create();
    $animal = ownedAnimal();

    $user->favorites()->attach($animal->getKey());

    $favorite = $user->favorites()->firstOrFail();

    expect($favorite->pivot->created_at)->not->toBeNull();
});

it('cascades: deleting a user removes their favorite rows', function () {
    $user = User::factory()->create();
    $animal = ownedAnimal();
    $user->favorites()->attach($animal->getKey());

    $user->delete();

    expect($animal->favoritedBy()->count())->toBe(0);
});
