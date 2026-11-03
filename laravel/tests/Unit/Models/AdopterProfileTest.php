<?php

declare(strict_types=1);

use App\Enums\HousingType;
use App\Enums\RentOrOwn;
use App\Models\AdopterProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

/**
 * Restituisce l'insieme di attributi che rendono `isComplete()` vero, così ogni
 * test può partire da uno stato valido e togliere/alterare un solo campo per
 * isolare esattamente il ramo che vuole coprire.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function completeAttributes(array $overrides = []): array
{
    return array_merge([
        'housing_type' => HousingType::House,
        'has_garden' => false,
        'fenced' => null,
        'rent_or_own' => RentOrOwn::Own,
        'landlord_allows_pets' => null,
        'adults_in_home' => 2,
        'hours_alone_weekday' => 4,
        'contingency_plan' => 'Affido a un familiare in caso di necessità.',
        'consent_data_processing' => true,
        'consented_at' => Carbon::now(),
    ], $overrides);
}

/**
 * Un modello NON persistito con gli attributi dati: i cast si applicano comunque
 * in lettura, quindi il predicato è testabile senza toccare il DB.
 *
 * @param  array<string, mixed>  $overrides
 */
function profile(array $overrides = []): AdopterProfile
{
    return new AdopterProfile(completeAttributes($overrides));
}

it('is complete when all required fields and consent are present', function () {
    expect(profile()->isComplete())->toBeTrue();
});

it('is complete for a rented home when the landlord allows pets', function () {
    expect(profile([
        'rent_or_own' => RentOrOwn::Rent,
        'landlord_allows_pets' => true,
    ])->isComplete())->toBeTrue();
});

it('is complete for a garden home once fenced is answered', function () {
    expect(profile(['has_garden' => true, 'fenced' => false])->isComplete())->toBeTrue()
        ->and(profile(['has_garden' => true, 'fenced' => true])->isComplete())->toBeTrue();
});

it('is incomplete when a required questionnaire field is missing', function (string $field) {
    expect(profile([$field => null])->isComplete())->toBeFalse();
})->with([
    'housing_type',
    'rent_or_own',
    'adults_in_home',
    'hours_alone_weekday',
    'contingency_plan',
]);

it('is incomplete when renting without the landlord allowing pets', function (?bool $landlordAllowsPets) {
    expect(profile([
        'rent_or_own' => RentOrOwn::Rent,
        'landlord_allows_pets' => $landlordAllowsPets,
    ])->isComplete())->toBeFalse();
})->with([
    'null answer' => [null],
    'explicit no' => [false],
]);

it('is incomplete when there is a garden but fenced is unanswered', function () {
    expect(profile(['has_garden' => true, 'fenced' => null])->isComplete())->toBeFalse();
});

it('is incomplete when data-processing consent is missing', function () {
    expect(profile(['consent_data_processing' => false])->isComplete())->toBeFalse();
});

it('is incomplete when consented_at is null even with the consent flag', function () {
    expect(profile(['consent_data_processing' => true, 'consented_at' => null])->isComplete())->toBeFalse();
});

it('casts columns to the expected types', function () {
    $profile = AdopterProfile::factory()->complete()->create();

    $fresh = $profile->fresh();

    expect($fresh->housing_type)->toBeInstanceOf(HousingType::class)
        ->and($fresh->rent_or_own)->toBeInstanceOf(RentOrOwn::class)
        ->and($fresh->has_garden)->toBeBool()
        ->and($fresh->consent_data_processing)->toBeBool()
        ->and($fresh->consent_marketing)->toBeBool()
        ->and($fresh->adults_in_home)->toBeInt()
        ->and($fresh->hours_alone_weekday)->toBeInt()
        ->and($fresh->consented_at)->toBeInstanceOf(Carbon::class);
});

it('builds a complete profile from the factory state', function () {
    expect(AdopterProfile::factory()->complete()->create()->isComplete())->toBeTrue();
});

it('builds an incomplete profile by default (no consent yet)', function () {
    $profile = AdopterProfile::factory()->create();

    expect($profile->consent_data_processing)->toBeFalse()
        ->and($profile->consented_at)->toBeNull()
        ->and($profile->isComplete())->toBeFalse();
});

it('builds a without-consent profile that is not complete', function () {
    $profile = AdopterProfile::factory()->withoutConsent()->create();

    expect($profile->consent_data_processing)->toBeFalse()
        ->and($profile->consented_at)->toBeNull()
        ->and($profile->isComplete())->toBeFalse();
});
