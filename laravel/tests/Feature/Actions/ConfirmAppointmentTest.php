<?php

declare(strict_types=1);

use App\Actions\Appointments\ConfirmAppointment;
use App\Enums\AppointmentStatus;
use App\Exceptions\InvalidAppointmentTransition;
use App\Models\Appointment;
use App\Models\User;
use App\Notifications\AppointmentConfirmed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    $this->actor = User::factory()->create();
});

it('confirms a requested appointment persisting, logging and notifying the requester', function () {
    $appointment = Appointment::factory()->requested()->create();

    $result = app(ConfirmAppointment::class)->handle($appointment, $this->actor);

    expect($result->status)->toBe(AppointmentStatus::Confirmed)
        ->and($appointment->fresh()->status)->toBe(AppointmentStatus::Confirmed);

    $activity = Activity::query()->where('description', 'appointment.confirmed')->latest('id')->first();
    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($this->actor->getKey())
        ->and($activity->properties->get('from'))->toBe(AppointmentStatus::Requested->value)
        ->and($activity->properties->get('to'))->toBe(AppointmentStatus::Confirmed->value);

    Notification::assertSentTo($appointment->user, AppointmentConfirmed::class);
});

it('throws InvalidAppointmentTransition confirming a completed appointment and changes nothing', function () {
    $appointment = Appointment::factory()->completed()->create();

    expect(fn () => app(ConfirmAppointment::class)->handle($appointment, $this->actor))
        ->toThrow(InvalidAppointmentTransition::class);

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Completed)
        ->and(Activity::query()->where('description', 'appointment.confirmed')->count())->toBe(0);

    Notification::assertNothingSent();
});

it('throws InvalidAppointmentTransition confirming a cancelled appointment', function () {
    $appointment = Appointment::factory()->cancelled()->create();

    expect(fn () => app(ConfirmAppointment::class)->handle($appointment, $this->actor))
        ->toThrow(InvalidAppointmentTransition::class);

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Cancelled);
    Notification::assertNothingSent();
});

it('carries the from/to states on the InvalidAppointmentTransition exception', function () {
    $appointment = Appointment::factory()->confirmed()->create();

    try {
        app(ConfirmAppointment::class)->handle($appointment, $this->actor);
        $this->fail('Expected InvalidAppointmentTransition was not thrown.');
    } catch (InvalidAppointmentTransition $exception) {
        expect($exception->from)->toBe(AppointmentStatus::Confirmed)
            ->and($exception->to)->toBe(AppointmentStatus::Confirmed);
    }
});
