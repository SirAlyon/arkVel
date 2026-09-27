<?php

declare(strict_types=1);

use App\Exceptions\AppointmentSlotUnavailable;
use App\Models\Animal;
use App\Models\Appointment;
use App\Models\User;
use App\Support\Appointments\AppointmentOverlapGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Unit della guardia di overlap condivisa: matematica della finestra, entità
 * controllate (animale E richiedente), esclusione di sé e bordi esatti.
 */
it('passes when no active appointment overlaps the window', function () {
    $animal = Animal::factory()->create();
    $requester = User::factory()->create();
    $when = now()->addDays(2)->startOfHour();

    app(AppointmentOverlapGuard::class)->ensureAvailable($animal, $requester, $when);
})->throwsNoExceptions();

it('throws forAnimal when the same animal has an overlapping active appointment', function () {
    $animal = Animal::factory()->create();
    $requester = User::factory()->create();
    $when = now()->addDays(3)->startOfHour();

    // Altro appuntamento attivo dello stesso ANIMALE nella finestra (30 min dopo).
    Appointment::factory()->forAnimal($animal)->confirmed()->at($when)->create();

    expect(fn () => app(AppointmentOverlapGuard::class)
        ->ensureAvailable($animal, $requester, $when->copy()->addMinutes(30)))
        ->toThrow(AppointmentSlotUnavailable::class);
});

it('throws forUser when the same requester overlaps on a different animal', function () {
    $requester = User::factory()->create();
    $firstAnimal = Animal::factory()->create();
    $secondAnimal = Animal::factory()->create();
    $when = now()->addDays(4)->startOfHour();

    // Appuntamento attivo del RICHIEDENTE su un altro animale, finestra sovrapposta.
    Appointment::factory()->forUser($requester)->forAnimal($firstAnimal)->confirmed()->at($when)->create();

    expect(fn () => app(AppointmentOverlapGuard::class)
        ->ensureAvailable($secondAnimal, $requester, $when->copy()->addMinutes(15)))
        ->toThrow(AppointmentSlotUnavailable::class);
});

it('does not block on a terminal (cancelled) appointment in the same window', function () {
    $animal = Animal::factory()->create();
    $requester = User::factory()->create();
    $when = now()->addDays(5)->startOfHour();

    Appointment::factory()->forAnimal($animal)->cancelled()->at($when)->create();

    app(AppointmentOverlapGuard::class)->ensureAvailable($animal, $requester, $when);
})->throwsNoExceptions();

it('treats the SLOT_MINUTES boundary as non-overlapping (edges excluded)', function () {
    $animal = Animal::factory()->create();
    $requester = User::factory()->create();
    $when = now()->addDays(6)->startOfHour();

    // Finestra esistente [when, when+60): esattamente +60 min NON si sovrappone.
    Appointment::factory()->forAnimal($animal)->confirmed()->at($when)->create();

    app(AppointmentOverlapGuard::class)->ensureAvailable(
        $animal,
        $requester,
        $when->copy()->addMinutes(Appointment::SLOT_MINUTES),
    );
})->throwsNoExceptions();

it('excludes the given appointment id so a record does not conflict with itself', function () {
    $animal = Animal::factory()->create();
    $requester = User::factory()->create();
    $when = now()->addDays(7)->startOfHour();

    // L'UNICO appuntamento attivo nella finestra è quello che stiamo spostando.
    $appointment = Appointment::factory()
        ->forAnimal($animal)
        ->forUser($requester)
        ->confirmed()
        ->at($when)
        ->create();

    // Escludendo il proprio id, la sua stessa finestra non conta come conflitto.
    app(AppointmentOverlapGuard::class)->ensureAvailable(
        $animal,
        $requester,
        $when,
        exceptAppointmentId: $appointment->getKey(),
    );
})->throwsNoExceptions();

it('still detects a conflict with a DIFFERENT appointment even when excluding self', function () {
    $animal = Animal::factory()->create();
    $requester = User::factory()->create();
    $when = now()->addDays(8)->startOfHour();

    $own = Appointment::factory()->forAnimal($animal)->forUser($requester)->confirmed()->at($when)->create();
    // Un ALTRO appuntamento attivo dell'animale sovrapposto: deve bloccare comunque.
    Appointment::factory()->forAnimal($animal)->confirmed()->at($when->copy()->addMinutes(30))->create();

    expect(fn () => app(AppointmentOverlapGuard::class)
        ->ensureAvailable($animal, $requester, $when, exceptAppointmentId: $own->getKey()))
        ->toThrow(AppointmentSlotUnavailable::class);
});
