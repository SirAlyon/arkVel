<?php

declare(strict_types=1);

use App\Actions\Appointments\MarkAppointmentNoShow;
use App\Enums\AppointmentStatus;
use App\Exceptions\InvalidAppointmentTransition;
use App\Models\Appointment;
use App\Models\User;
use App\Notifications\AppointmentNoShow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    $this->actor = User::factory()->create();
});

it('marks a confirmed appointment as no-show persisting, logging and notifying the requester', function () {
    $appointment = Appointment::factory()->confirmed()->create();

    $result = app(MarkAppointmentNoShow::class)->handle($appointment, $this->actor);

    expect($result->status)->toBe(AppointmentStatus::NoShow)
        ->and($appointment->fresh()->status)->toBe(AppointmentStatus::NoShow);

    $activity = Activity::query()->where('description', 'appointment.no_show')->latest('id')->first();
    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($this->actor->getKey())
        ->and($activity->properties->get('from'))->toBe(AppointmentStatus::Confirmed->value)
        ->and($activity->properties->get('to'))->toBe(AppointmentStatus::NoShow->value);

    Notification::assertSentTo($appointment->user, AppointmentNoShow::class);
});

it('throws InvalidAppointmentTransition marking a requested appointment no-show (must be confirmed first)', function () {
    $appointment = Appointment::factory()->requested()->create();

    expect(fn () => app(MarkAppointmentNoShow::class)->handle($appointment, $this->actor))
        ->toThrow(InvalidAppointmentTransition::class);

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Requested)
        ->and(Activity::query()->where('description', 'appointment.no_show')->count())->toBe(0);

    Notification::assertNothingSent();
});

it('throws InvalidAppointmentTransition marking a completed appointment no-show', function () {
    $appointment = Appointment::factory()->completed()->create();

    expect(fn () => app(MarkAppointmentNoShow::class)->handle($appointment, $this->actor))
        ->toThrow(InvalidAppointmentTransition::class);

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Completed);
    Notification::assertNothingSent();
});
