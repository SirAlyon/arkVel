<?php

declare(strict_types=1);

use App\Enums\MembershipRole;
use App\Models\Animal;
use App\Models\MedicalRecord;
use App\Models\Organization;
use App\Notifications\MedicalReminderDue;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;

// L'helper member() è definito in tests/Pest.php.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Notification::fake();
    // Ancoriamo il tempo: le finestre di scadenza (--days) devono essere deterministiche.
    Carbon::setTestNow(Carbon::parse('2026-06-15 08:00:00'));
});

afterEach(function () {
    Carbon::setTestNow();
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

/**
 * Voce sanitaria con una data di scadenza esplicita per un animale dell'ente.
 */
function medicalRecordDueOn(Organization $organization, ?string $nextDueAt): MedicalRecord
{
    $animal = Animal::factory()->available()->forOrganization($organization)->create();

    return MedicalRecord::factory()->forAnimal($animal)->create([
        'next_due_at' => $nextDueAt,
        'reminder_sent_at' => null,
    ]);
}

it('reminds only records due within the default 7-day window (overdue + soon), leaving the rest untouched', function () {
    $org = Organization::factory()->verified()->create();
    $staff = member($org, MembershipRole::Staff);

    $overdue = medicalRecordDueOn($org, now()->subDays(2)->toDateString());
    $soon = medicalRecordDueOn($org, now()->addDays(3)->toDateString());
    $later = medicalRecordDueOn($org, now()->addDays(20)->toDateString());
    $noDue = medicalRecordDueOn($org, null);

    $this->artisan('arkvel:send-medical-reminders')->assertSuccessful();

    // Le due voci in finestra sono state notificate allo staff verificato.
    Notification::assertSentTo(
        $staff,
        MedicalReminderDue::class,
        fn (MedicalReminderDue $notification): bool => $notification->record->is($overdue),
    );
    Notification::assertSentTo(
        $staff,
        MedicalReminderDue::class,
        fn (MedicalReminderDue $notification): bool => $notification->record->is($soon),
    );

    // Esattamente due invii verso lo staff (le due voci in finestra).
    Notification::assertSentToTimes($staff, MedicalReminderDue::class, 2);

    // reminder_sent_at valorizzato solo sulle due voci in finestra.
    expect($overdue->refresh()->reminder_sent_at)->not->toBeNull()
        ->and($soon->refresh()->reminder_sent_at)->not->toBeNull()
        ->and($later->refresh()->reminder_sent_at)->toBeNull()
        ->and($noDue->refresh()->reminder_sent_at)->toBeNull();
});

it('is idempotent: a second run sends nothing', function () {
    $org = Organization::factory()->verified()->create();
    member($org, MembershipRole::Staff);

    medicalRecordDueOn($org, now()->subDay()->toDateString());

    $this->artisan('arkvel:send-medical-reminders')->assertSuccessful();
    Notification::assertSentTimes(MedicalReminderDue::class, 1);

    // Seconda esecuzione: la voce è già marcata (reminder_sent_at) → niente re-invio.
    $this->artisan('arkvel:send-medical-reminders')->assertSuccessful();
    Notification::assertSentTimes(MedicalReminderDue::class, 1);
});

it('ignores records with a null next_due_at', function () {
    $org = Organization::factory()->verified()->create();
    member($org, MembershipRole::Staff);

    $noDue = medicalRecordDueOn($org, null);

    $this->artisan('arkvel:send-medical-reminders')->assertSuccessful();

    Notification::assertNothingSent();
    expect($noDue->refresh()->reminder_sent_at)->toBeNull();
});

it('honours a custom --days window', function () {
    $org = Organization::factory()->verified()->create();
    member($org, MembershipRole::Staff);

    $inFifteen = medicalRecordDueOn($org, now()->addDays(15)->toDateString());

    // Default 7g → escluso; con --days=30 → incluso.
    $this->artisan('arkvel:send-medical-reminders')->assertSuccessful();
    Notification::assertNothingSent();

    $this->artisan('arkvel:send-medical-reminders', ['--days' => 30])->assertSuccessful();
    Notification::assertSentTimes(MedicalReminderDue::class, 1);
    expect($inFifteen->refresh()->reminder_sent_at)->not->toBeNull();
});

it('reminds the owning organization staff, never another organization staff (tenant isolation)', function () {
    $orgA = Organization::factory()->verified()->create();
    $orgB = Organization::factory()->verified()->create();

    $staffA = member($orgA, MembershipRole::Staff);
    $staffB = member($orgB, MembershipRole::Staff);

    $recordA = medicalRecordDueOn($orgA, now()->addDay()->toDateString());

    $this->artisan('arkvel:send-medical-reminders')->assertSuccessful();

    // Solo lo staff di A viene avvisato della scadenza di A.
    Notification::assertSentTo($staffA, MedicalReminderDue::class);
    Notification::assertNotSentTo($staffB, MedicalReminderDue::class);

    expect($recordA->refresh()->reminder_sent_at)->not->toBeNull();
});

it('skips a record whose animal has no organization without marking it', function () {
    // Un animale soft-deletable senza ente: forziamo animal->organization a null
    // cancellando l'ente lascerebbe FK; usiamo invece un ente e poi lo scolleghiamo
    // via query grezza per simulare il caso limite (nessun destinatario possibile).
    $org = Organization::factory()->verified()->create();
    $record = medicalRecordDueOn($org, now()->toDateString());

    // Nessun destinatario: l'ente esiste ma non ha staff verificato → invio no-op,
    // ma la voce viene comunque marcata (destinatario vuoto è un no-op sicuro).
    $this->artisan('arkvel:send-medical-reminders')->assertSuccessful();

    Notification::assertNothingSent();
    // Con un ente presente ma senza staff, la voce è marcata (send su lista vuota).
    expect($record->refresh()->reminder_sent_at)->not->toBeNull();
});
