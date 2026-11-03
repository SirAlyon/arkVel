<?php

declare(strict_types=1);

use App\Actions\Appointments\RescheduleAppointment;
use App\Exceptions\AppointmentSlotUnavailable;
use App\Exceptions\CannotRescheduleAppointment;
use App\Exceptions\InvalidAppointmentSlot;
use App\Models\Animal;
use App\Models\Appointment;
use App\Models\User;
use App\Notifications\AppointmentRescheduled;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

it('moves the appointment, resets the reminder, logs from/to and notifies the requester', function () {
    Notification::fake();

    $actor = User::factory()->create();
    $appointment = Appointment::factory()
        ->confirmed()
        ->at(now()->addDays(3)->startOfHour())
        // Un promemoria già inviato: lo spostamento deve azzerarlo.
        ->create(['reminder_sent_at' => now()]);

    $from = $appointment->scheduled_at->copy();
    $to = now()->addDays(5)->startOfHour();

    $result = app(RescheduleAppointment::class)->handle($appointment, $actor, $to);

    expect($result->scheduled_at->equalTo($to))->toBeTrue()
        ->and($appointment->refresh()->scheduled_at->equalTo($to))->toBeTrue()
        // Il promemoria dell'imminenza deve ripartire per il nuovo orario.
        ->and($appointment->reminder_sent_at)->toBeNull();

    $activity = Activity::query()->where('description', 'appointment.rescheduled')->latest('id')->first();
    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($actor->getKey())
        ->and($activity->properties['from'])->toBe($from->toIso8601String())
        ->and($activity->properties['to'])->toBe($to->toIso8601String());

    Notification::assertSentToTimes($appointment->user, AppointmentRescheduled::class, 1);
});

it('throws InvalidAppointmentSlot and does not mutate for a slot in the past', function () {
    Notification::fake();

    $actor = User::factory()->create();
    $when = now()->addDays(4)->startOfHour();
    $appointment = Appointment::factory()->confirmed()->at($when)->create();

    expect(fn () => app(RescheduleAppointment::class)->handle($appointment, $actor, now()->subHour()))
        ->toThrow(InvalidAppointmentSlot::class);

    expect($appointment->refresh()->scheduled_at->equalTo($when))->toBeTrue();
    Notification::assertNothingSent();
});

it('throws AppointmentSlotUnavailable when the new slot overlaps ANOTHER active appointment', function () {
    Notification::fake();

    $actor = User::factory()->create();
    $animal = Animal::factory()->create();
    $when = now()->addDays(6)->startOfHour();

    $moving = Appointment::factory()->forAnimal($animal)->confirmed()->at($when)->create();
    // Un altro appuntamento attivo dello stesso animale occupa il target.
    $blockerAt = now()->addDays(7)->startOfHour();
    Appointment::factory()->forAnimal($animal)->confirmed()->at($blockerAt)->create();

    expect(fn () => app(RescheduleAppointment::class)->handle($moving, $actor, $blockerAt->copy()->addMinutes(30)))
        ->toThrow(AppointmentSlotUnavailable::class);

    expect($moving->refresh()->scheduled_at->equalTo($when))->toBeTrue();
    Notification::assertNothingSent();
});

it('succeeds when the target window only overlaps the appointment ITSELF (exclude-self)', function () {
    Notification::fake();

    $actor = User::factory()->create();
    $when = now()->addDays(8)->startOfHour();
    $appointment = Appointment::factory()->confirmed()->at($when)->create();

    // Spostamento di 30 min: la nuova finestra si sovrappone SOLO alla vecchia
    // finestra dello stesso appuntamento → consentito (exclude-self).
    $to = $when->copy()->addMinutes(30);
    $result = app(RescheduleAppointment::class)->handle($appointment, $actor, $to);

    expect($result->scheduled_at->equalTo($to))->toBeTrue();
    Notification::assertSentToTimes($appointment->user, AppointmentRescheduled::class, 1);
});

it('throws CannotRescheduleAppointment for a terminal (completed) appointment', function () {
    Notification::fake();

    $actor = User::factory()->create();
    $when = now()->addDays(9)->startOfHour();
    $appointment = Appointment::factory()->completed()->at($when)->create();

    expect(fn () => app(RescheduleAppointment::class)->handle($appointment, $actor, now()->addDays(10)->startOfHour()))
        ->toThrow(CannotRescheduleAppointment::class);

    expect($appointment->refresh()->scheduled_at->equalTo($when))->toBeTrue();
    Notification::assertNothingSent();
});

it('throws CannotRescheduleAppointment for a cancelled appointment', function () {
    $actor = User::factory()->create();
    $appointment = Appointment::factory()->cancelled()->at(now()->addDays(9)->startOfHour())->create();

    expect(fn () => app(RescheduleAppointment::class)->handle($appointment, $actor, now()->addDays(11)->startOfHour()))
        ->toThrow(CannotRescheduleAppointment::class);
});

it('allows rescheduling a still-active (requested) appointment', function () {
    Notification::fake();

    $actor = User::factory()->create();
    $appointment = Appointment::factory()->requested()->at(now()->addDays(9)->startOfHour())->create();
    $to = now()->addDays(12)->startOfHour();

    $result = app(RescheduleAppointment::class)->handle($appointment, $actor, $to);

    expect($result->scheduled_at->equalTo($to))->toBeTrue();
});
