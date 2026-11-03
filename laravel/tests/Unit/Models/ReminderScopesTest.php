<?php

declare(strict_types=1);

use App\Models\Appointment;
use App\Models\MedicalRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-06-15 08:00:00'));
});

afterEach(function () {
    Carbon::setTestNow();
});

// --- MedicalRecord::dueForReminder ---------------------------------------------

it('MedicalRecord dueForReminder catches overdue and soon, not-yet-reminded records', function () {
    $overdue = MedicalRecord::factory()->create(['next_due_at' => now()->subDay(), 'reminder_sent_at' => null]);
    $soon = MedicalRecord::factory()->create(['next_due_at' => now()->addDays(5), 'reminder_sent_at' => null]);
    MedicalRecord::factory()->create(['next_due_at' => now()->addDays(20), 'reminder_sent_at' => null]);
    MedicalRecord::factory()->create(['next_due_at' => null, 'reminder_sent_at' => null]);
    MedicalRecord::factory()->create(['next_due_at' => now()->addDay(), 'reminder_sent_at' => now()]);

    $due = MedicalRecord::query()->dueForReminder(7)->get();

    expect($due->pluck('id')->all())
        ->toEqualCanonicalizing([$overdue->getKey(), $soon->getKey()]);
});

it('MedicalRecord dueForReminder ignores already-reminded records', function () {
    MedicalRecord::factory()->create(['next_due_at' => now()->addDay(), 'reminder_sent_at' => now()]);

    expect(MedicalRecord::query()->dueForReminder(7)->count())->toBe(0);
});

// --- MedicalRecord::dueWithin --------------------------------------------------

it('MedicalRecord dueWithin includes reminded and non-reminded records within the window', function () {
    $reminded = MedicalRecord::factory()->create(['next_due_at' => now()->addDays(3), 'reminder_sent_at' => now()]);
    $fresh = MedicalRecord::factory()->create(['next_due_at' => now()->addDays(3), 'reminder_sent_at' => null]);
    MedicalRecord::factory()->create(['next_due_at' => now()->addDays(40), 'reminder_sent_at' => null]);
    MedicalRecord::factory()->create(['next_due_at' => null, 'reminder_sent_at' => null]);

    $within = MedicalRecord::query()->dueWithin(30)->get();

    expect($within->pluck('id')->all())
        ->toEqualCanonicalizing([$reminded->getKey(), $fresh->getKey()]);
});

// --- Appointment::dueForReminder -----------------------------------------------

it('Appointment dueForReminder catches only confirmed, in-window, not-yet-reminded appointments', function () {
    $inWindow = Appointment::factory()->confirmed()->at(now()->addHours(6))->create(['reminder_sent_at' => null]);
    Appointment::factory()->requested()->at(now()->addHours(6))->create(['reminder_sent_at' => null]);
    Appointment::factory()->confirmed()->at(now()->addDays(3))->create(['reminder_sent_at' => null]);
    Appointment::factory()->confirmed()->at(now()->subHour())->create(['reminder_sent_at' => null]);
    Appointment::factory()->confirmed()->at(now()->addHours(6))->create(['reminder_sent_at' => now()]);

    $due = Appointment::query()->dueForReminder(24)->get();

    expect($due->pluck('id')->all())->toBe([$inWindow->getKey()]);
});
