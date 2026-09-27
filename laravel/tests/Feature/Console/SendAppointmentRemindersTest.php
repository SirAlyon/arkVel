<?php

declare(strict_types=1);

use App\Models\Appointment;
use App\Models\User;
use App\Notifications\AppointmentReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    Carbon::setTestNow(Carbon::parse('2026-06-15 08:00:00'));
});

afterEach(function () {
    Carbon::setTestNow();
});

it('reminds the requester of a confirmed appointment within the default 24h window', function () {
    $requester = User::factory()->create();
    $appointment = Appointment::factory()
        ->confirmed()
        ->forUser($requester)
        ->at(now()->addHours(6))
        ->create();

    $this->artisan('arkvel:send-appointment-reminders')->assertSuccessful();

    Notification::assertSentTo(
        $requester,
        AppointmentReminder::class,
        fn (AppointmentReminder $notification): bool => $notification->appointment->is($appointment),
    );
    Notification::assertSentToTimes($requester, AppointmentReminder::class, 1);

    expect($appointment->refresh()->reminder_sent_at)->not->toBeNull();
});

it('does not remind a requested (unconfirmed) appointment', function () {
    $appointment = Appointment::factory()
        ->requested()
        ->at(now()->addHours(6))
        ->create();

    $this->artisan('arkvel:send-appointment-reminders')->assertSuccessful();

    Notification::assertNothingSent();
    expect($appointment->refresh()->reminder_sent_at)->toBeNull();
});

it('does not remind a confirmed appointment beyond the window (3 days out)', function () {
    $appointment = Appointment::factory()
        ->confirmed()
        ->at(now()->addDays(3))
        ->create();

    $this->artisan('arkvel:send-appointment-reminders')->assertSuccessful();

    Notification::assertNothingSent();
    expect($appointment->refresh()->reminder_sent_at)->toBeNull();
});

it('is idempotent: a second run sends nothing', function () {
    Appointment::factory()->confirmed()->at(now()->addHours(6))->create();

    $this->artisan('arkvel:send-appointment-reminders')->assertSuccessful();
    Notification::assertSentTimes(AppointmentReminder::class, 1);

    $this->artisan('arkvel:send-appointment-reminders')->assertSuccessful();
    Notification::assertSentTimes(AppointmentReminder::class, 1);
});

it('honours a custom --hours window', function () {
    $appointment = Appointment::factory()
        ->confirmed()
        ->at(now()->addHours(48))
        ->create();

    // Default 24h → escluso.
    $this->artisan('arkvel:send-appointment-reminders')->assertSuccessful();
    Notification::assertNothingSent();

    // --hours=72 → incluso.
    $this->artisan('arkvel:send-appointment-reminders', ['--hours' => 72])->assertSuccessful();
    Notification::assertSentTimes(AppointmentReminder::class, 1);
    expect($appointment->refresh()->reminder_sent_at)->not->toBeNull();
});
