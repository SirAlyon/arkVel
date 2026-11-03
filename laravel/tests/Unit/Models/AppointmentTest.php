<?php

declare(strict_types=1);

use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use App\Models\Animal;
use App\Models\Appointment;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('casts scheduled_at to Carbon and type/status to their enums', function () {
    $appointment = Appointment::factory()
        ->ofType(AppointmentType::HomeCheck)
        ->confirmed()
        ->create();

    expect($appointment->scheduled_at)->toBeInstanceOf(Carbon::class)
        ->and($appointment->type)->toBe(AppointmentType::HomeCheck)
        ->and($appointment->status)->toBe(AppointmentStatus::Confirmed);
});

it('persists the enum string values in the columns', function () {
    $appointment = Appointment::factory()
        ->ofType(AppointmentType::Interview)
        ->cancelled()
        ->create();

    expect($appointment->getRawOriginal('type'))->toBe(AppointmentType::Interview->value)
        ->and($appointment->getRawOriginal('status'))->toBe(AppointmentStatus::Cancelled->value);
});

it('defaults to a Requested status via the factory', function () {
    $appointment = Appointment::factory()->create();

    expect($appointment->status)->toBe(AppointmentStatus::Requested);
});

it('exposes the slot duration constant', function () {
    expect(Appointment::SLOT_MINUTES)->toBe(60);
});

it('links to animal, requester and organization', function () {
    $organization = Organization::factory()->create();
    $animal = Animal::factory()->forOrganization($organization)->create();
    $requester = User::factory()->create();

    $appointment = Appointment::factory()
        ->forAnimal($animal)
        ->forUser($requester)
        ->create();

    expect($appointment->animal)->toBeInstanceOf(Animal::class)
        ->and($appointment->animal->is($animal))->toBeTrue()
        ->and($appointment->user->is($requester))->toBeTrue()
        ->and($appointment->organization->is($organization))->toBeTrue();
});

it('keeps organization consistent with the animal org via the factory', function () {
    $appointment = Appointment::factory()->create();

    expect($appointment->organization_id)->toBe($appointment->animal->organization_id);
});

it('scopes active to Requested and Confirmed only', function () {
    Appointment::factory()->requested()->create();
    Appointment::factory()->confirmed()->create();
    Appointment::factory()->completed()->create();
    Appointment::factory()->cancelled()->create();
    Appointment::factory()->noShow()->create();

    $active = Appointment::query()->active()->get();

    expect($active)->toHaveCount(2)
        ->and($active->pluck('status')->all())->each->toBeIn([
            AppointmentStatus::Requested,
            AppointmentStatus::Confirmed,
        ]);
});

it('scopes upcoming to active appointments in the future', function () {
    // Attivo e futuro → incluso.
    $future = Appointment::factory()->confirmed()->at(now()->addDay())->create();
    // Attivo ma passato → escluso.
    Appointment::factory()->confirmed()->at(now()->subDay())->create();
    // Futuro ma NON attivo (completato) → escluso.
    Appointment::factory()->completed()->at(now()->addDay())->create();

    $upcoming = Appointment::query()->upcoming()->get();

    expect($upcoming)->toHaveCount(1)
        ->and($upcoming->first()->is($future))->toBeTrue();
});
