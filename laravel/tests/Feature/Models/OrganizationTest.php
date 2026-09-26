<?php

declare(strict_types=1);

use App\Enums\OrganizationStatus;
use App\Enums\OrganizationType;
use App\Models\Organization;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('creates an organization via factory', function () {
    $organization = Organization::factory()->create();

    expect($organization->exists)->toBeTrue()
        ->and($organization->name)->toBeString()->not->toBeEmpty();
});

it('casts type and status to their enums', function () {
    $organization = Organization::factory()->create([
        'type' => OrganizationType::Rifugio,
        'status' => OrganizationStatus::Verified,
    ]);

    $organization->refresh();

    expect($organization->type)->toBe(OrganizationType::Rifugio)
        ->and($organization->status)->toBe(OrganizationStatus::Verified);
});

it('stores description as a translatable field', function () {
    $organization = Organization::factory()->create([
        'description' => ['it' => 'Un rifugio accogliente', 'en' => 'A cozy shelter'],
    ]);

    $organization->refresh();

    expect($organization->getTranslation('description', 'it'))->toBe('Un rifugio accogliente')
        ->and($organization->getTranslation('description', 'en'))->toBe('A cozy shelter');
});

it('generates a slug from the name when none is provided', function () {
    $organization = Organization::factory()->create([
        'name' => 'Canile Municipale di Milano',
        'slug' => null,
    ]);

    expect($organization->slug)->toBe('canile-municipale-di-milano');
});

it('keeps an explicitly provided slug', function () {
    $organization = Organization::factory()->create([
        'name' => 'Canile Municipale di Milano',
        'slug' => 'custom-slug',
    ]);

    expect($organization->slug)->toBe('custom-slug');
});

it('enforces slug uniqueness', function () {
    Organization::factory()->create(['slug' => 'duplicated']);

    Organization::factory()->create(['slug' => 'duplicated']);
})->throws(QueryException::class);

it('does not yet de-duplicate auto-generated slugs on collision', function () {
    // Regression anchor: two orgs with the same name and no explicit slug both
    // generate the same slug, so the second insert violates the unique index.
    // Slug suffixing/uniqueness is deferred to a later slice (Action/observer);
    // when implemented, this test should be updated to assert de-duplication.
    Organization::factory()->create(['name' => 'Canile di Milano', 'slug' => null]);

    Organization::factory()->create(['name' => 'Canile di Milano', 'slug' => null]);
})->throws(QueryException::class);

it('filters verified organizations with the verified scope', function () {
    Organization::factory()->verified()->count(2)->create();
    Organization::factory()->pending()->create();
    Organization::factory()->suspended()->create();

    $verified = Organization::query()->verified()->get();

    expect($verified)->toHaveCount(2)
        ->and($verified->every(fn (Organization $o): bool => $o->status === OrganizationStatus::Verified))
        ->toBeTrue();
});

it('casts latitude and longitude to float', function () {
    $organization = Organization::factory()->create([
        'latitude' => 45.4642,
        'longitude' => 9.19,
    ]);

    $organization->refresh();

    expect($organization->latitude)->toBeFloat()
        ->and($organization->longitude)->toBeFloat();
});

it('casts verified_at to a datetime', function () {
    $organization = Organization::factory()->verified()->create();

    $organization->refresh();

    expect($organization->verified_at)->toBeInstanceOf(Carbon::class);
});
