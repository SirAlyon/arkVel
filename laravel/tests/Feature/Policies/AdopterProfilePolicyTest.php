<?php

declare(strict_types=1);

use App\Models\AdopterProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lets the owner view, update and delete their own profile', function () {
    $owner = User::factory()->create();
    $profile = AdopterProfile::factory()->forUser($owner)->create();

    expect($owner->can('view', $profile))->toBeTrue()
        ->and($owner->can('update', $profile))->toBeTrue()
        ->and($owner->can('delete', $profile))->toBeTrue();
});

it('denies another user any per-profile action', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $profile = AdopterProfile::factory()->forUser($owner)->create();

    expect($intruder->can('view', $profile))->toBeFalse()
        ->and($intruder->can('update', $profile))->toBeFalse()
        ->and($intruder->can('delete', $profile))->toBeFalse();
});

it('allows any authenticated user to viewAny and create', function () {
    $user = User::factory()->create();

    expect($user->can('viewAny', AdopterProfile::class))->toBeTrue()
        ->and($user->can('create', AdopterProfile::class))->toBeTrue();
});
