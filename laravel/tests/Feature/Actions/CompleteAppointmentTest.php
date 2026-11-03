<?php

declare(strict_types=1);

use App\Actions\Appointments\CompleteAppointment;
use App\Enums\AppointmentStatus;
use App\Exceptions\InvalidAppointmentTransition;
use App\Models\Appointment;
use App\Models\User;
use App\Notifications\AppointmentCompleted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    $this->actor = User::factory()->create();
});

it('completes a confirmed appointment persisting, logging and notifying the requester', function () {
    $appointment = Appointment::factory()->confirmed()->create();

    $result = app(CompleteAppointment::class)->handle($appointment, $this->actor);

    expect($result->status)->toBe(AppointmentStatus::Completed)
        ->and($appointment->fresh()->status)->toBe(AppointmentStatus::Completed);

    $activity = Activity::query()->where('description', 'appointment.completed')->latest('id')->first();
    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($this->actor->getKey())
        ->and($activity->properties->get('from'))->toBe(AppointmentStatus::Confirmed->value)
        ->and($activity->properties->get('to'))->toBe(AppointmentStatus::Completed->value);

    Notification::assertSentTo($appointment->user, AppointmentCompleted::class);
});

it('throws InvalidAppointmentTransition completing a requested appointment (must be confirmed first)', function () {
    $appointment = Appointment::factory()->requested()->create();

    expect(fn () => app(CompleteAppointment::class)->handle($appointment, $this->actor))
        ->toThrow(InvalidAppointmentTransition::class);

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Requested)
        ->and(Activity::query()->where('description', 'appointment.completed')->count())->toBe(0);

    Notification::assertNothingSent();
});

it('throws InvalidAppointmentTransition completing a cancelled appointment', function () {
    $appointment = Appointment::factory()->cancelled()->create();

    expect(fn () => app(CompleteAppointment::class)->handle($appointment, $this->actor))
        ->toThrow(InvalidAppointmentTransition::class);

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Cancelled);
    Notification::assertNothingSent();
});
