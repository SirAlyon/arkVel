<?php

declare(strict_types=1);

use App\Models\Animal;
use App\Models\Appointment;
use App\Models\MedicalRecord;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\AppointmentReminder;
use App\Notifications\MedicalReminderDue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;

uses(RefreshDatabase::class);

/**
 * Concatena greeting + righe + salutation di una MailMessage (specchio del helper
 * in NotificationContentTest.php, ridefinito qui per indipendenza del file).
 */
function reminderMailBody(MailMessage $mail): string
{
    return implode("\n", [
        (string) $mail->greeting,
        ...$mail->introLines,
        ...$mail->outroLines,
        (string) $mail->salutation,
    ]);
}

// --- MedicalReminderDue (allo staff dell'ente) ----------------------------------

it('MedicalReminderDue is queued after commit and uses mail + database', function () {
    $record = MedicalRecord::factory()->create(['next_due_at' => now()->addDay()]);
    $notification = new MedicalReminderDue($record);

    expect($notification)->toBeInstanceOf(ShouldQueue::class)
        ->and($notification->afterCommit)->toBeTrue()
        ->and($notification->via($record->animal->organization))->toBe(['mail', 'database']);
});

it('MedicalReminderDue mail carries the translated subject/body, animal, type and due date', function () {
    $animal = Animal::factory()->create(['name' => 'Fido']);
    $record = MedicalRecord::factory()->forAnimal($animal)->vaccination()->create([
        'next_due_at' => '2026-07-01',
    ]);

    $mail = (new MedicalReminderDue($record))->toMail($animal->organization);

    expect($mail->subject)->toBe(__('medical.reminder.subject', ['animal' => 'Fido']))
        ->and(reminderMailBody($mail))
        ->toContain('Fido')
        ->toContain($record->type->getLabel())
        ->toContain('2026-07-01');
});

it('MedicalReminderDue database payload carries ids, animal, type, due date and a non-empty message', function () {
    $animal = Animal::factory()->create(['name' => 'Fido']);
    $record = MedicalRecord::factory()->forAnimal($animal)->vaccination()->create([
        'next_due_at' => '2026-07-01',
    ]);

    $payload = (new MedicalReminderDue($record))->toArray($animal->organization);

    expect($payload)
        ->toHaveKeys(['medical_record_id', 'animal_id', 'organization_id', 'animal', 'type', 'next_due_at', 'message'])
        ->and($payload['medical_record_id'])->toBe($record->getKey())
        ->and($payload['animal_id'])->toBe($record->animal_id)
        ->and($payload['organization_id'])->toBe($animal->organization_id)
        ->and($payload['animal'])->toBe('Fido')
        ->and($payload['type'])->toBe($record->type->value)
        ->and($payload['message'])->not->toBe('')
        ->and($payload['message'])->toContain('Fido');
});

// --- AppointmentReminder (al richiedente) ---------------------------------------

it('AppointmentReminder is queued after commit and uses mail + database', function () {
    $appointment = Appointment::factory()->confirmed()->create();
    $notification = new AppointmentReminder($appointment);

    expect($notification)->toBeInstanceOf(ShouldQueue::class)
        ->and($notification->afterCommit)->toBeTrue()
        ->and($notification->via($appointment->user))->toBe(['mail', 'database']);
});

it('AppointmentReminder mail carries the translated subject/body, animal, type and datetime', function () {
    $animal = Animal::factory()->create(['name' => 'Luna']);
    $requester = User::factory()->create(['name' => 'Mario Rossi']);
    $appointment = Appointment::factory()
        ->confirmed()
        ->forAnimal($animal)
        ->forUser($requester)
        ->at(now()->addHours(6))
        ->create();

    $mail = (new AppointmentReminder($appointment))->toMail($requester);

    expect($mail->subject)->toBe(__('appointments.reminder.subject', ['animal' => 'Luna']))
        ->and(reminderMailBody($mail))
        ->toContain('Luna')
        ->toContain('Mario Rossi')
        ->toContain($appointment->type->getLabel());
});

it('AppointmentReminder database payload carries ids, animal, type, datetime and a non-empty message', function () {
    $animal = Animal::factory()->create(['name' => 'Luna']);
    $appointment = Appointment::factory()->confirmed()->forAnimal($animal)->create();

    $payload = (new AppointmentReminder($appointment))->toArray($appointment->user);

    expect($payload)
        ->toHaveKeys(['appointment_id', 'animal_id', 'organization_id', 'animal', 'type', 'scheduled_at', 'message'])
        ->and($payload['appointment_id'])->toBe($appointment->getKey())
        ->and($payload['animal_id'])->toBe($appointment->animal_id)
        ->and($payload['organization_id'])->toBe($appointment->organization_id)
        ->and($payload['animal'])->toBe('Luna')
        ->and($payload['type'])->toBe($appointment->type->value)
        ->and($payload['message'])->not->toBe('')
        ->and($payload['message'])->toContain('Luna');
});

// --- Organization scope check ---------------------------------------------------
// Contesto: assicura che l'org non nulla usata sopra provenga da un ente reale.
it('resolves the owning organization for the medical reminder', function () {
    $org = Organization::factory()->verified()->create();
    $animal = Animal::factory()->forOrganization($org)->create();
    $record = MedicalRecord::factory()->forAnimal($animal)->create(['next_due_at' => now()->addDay()]);

    $payload = (new MedicalReminderDue($record))->toArray($org);

    expect($payload['organization_id'])->toBe($org->getKey());
});
