<?php

declare(strict_types=1);

use App\Models\Animal;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('includes only available animals of verified organizations', function () {
    $verified = Organization::factory()->verified()->create();
    $pending = Organization::factory()->pending()->create();

    $visible = Animal::factory()->available()->forOrganization($verified)->create();
    $draftInVerified = Animal::factory()->draft()->forOrganization($verified)->create();
    $availableInPending = Animal::factory()->available()->forOrganization($pending)->create();

    $ids = Animal::query()->publiclyVisible()->pluck('id');

    expect($ids)->toContain($visible->getKey())
        ->and($ids)->not->toContain($draftInVerified->getKey())
        ->and($ids)->not->toContain($availableInPending->getKey());
});

it('excludes adopted and reserved animals even in verified organizations', function () {
    $verified = Organization::factory()->verified()->create();

    Animal::factory()->adopted()->forOrganization($verified)->create();
    $available = Animal::factory()->available()->forOrganization($verified)->create();

    $ids = Animal::query()->publiclyVisible()->pluck('id');

    expect($ids)->toHaveCount(1)
        ->and($ids->first())->toBe($available->getKey());
});
