<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows any authenticated user to view the list', function () {
    $user = User::factory()->create();

    expect($user->can('viewAny', Organization::class))->toBeTrue();
});

it('allows any authenticated user to view a single organization', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    expect($user->can('view', $organization))->toBeTrue();
});

it('denies creation by default', function () {
    $user = User::factory()->create();

    expect($user->can('create', Organization::class))->toBeFalse();
});

it('denies update by default', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    expect($user->can('update', $organization))->toBeFalse();
});

it('denies deletion by default', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    expect($user->can('delete', $organization))->toBeFalse();
});
