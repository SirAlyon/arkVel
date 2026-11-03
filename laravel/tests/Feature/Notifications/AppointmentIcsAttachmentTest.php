<?php

declare(strict_types=1);

use App\Models\Animal;
use App\Models\Appointment;
use App\Models\Organization;
use App\Notifications\AppointmentConfirmed;
use App\Notifications\AppointmentRescheduled;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

uses(RefreshDatabase::class);

/**
 * Estrae il primo (e unico) allegato .ics da una MailMessage costruita via toMail().
 * Rendiamo il MailMessage in un Symfony Email (come fa il framework in invio) così da
 * ispezionare gli allegati reali senza far girare l'intera pipeline di notifica.
 */
function icsAttachment(MailMessage $mail): ?DataPart
{
    $email = (new Email)->to('test@example.test')->subject((string) $mail->subject);

    foreach ($mail->rawAttachments as $attachment) {
        $email->attach($attachment['data'], $attachment['name'] ?? null, $attachment['options']['mime'] ?? null);
    }

    $attachments = $email->getAttachments();

    return $attachments[0] ?? null;
}

it('attaches a PII-minimized ics to the AppointmentConfirmed mail', function () {
    $org = Organization::factory()->create(['name' => 'Rifugio Speranza', 'city' => 'Milano']);
    $animal = Animal::factory()->forOrganization($org)->create(['name' => 'Rex']);
    $appointment = Appointment::factory()->confirmed()->forAnimal($animal)->create();

    $mail = (new AppointmentConfirmed($appointment))->toMail($appointment->user);

    $attachment = icsAttachment($mail);

    expect($attachment)->not->toBeNull()
        ->and($attachment->getContentType())->toContain('text/calendar')
        ->and($attachment->getBody())
        ->toContain('BEGIN:VEVENT')
        ->toContain('SUMMARY:')
        // Minimizzazione: nessun dato personale del richiedente.
        ->not->toContain($appointment->user->email);
});

it('attaches the ics filename appointment.ics to the confirmed mail', function () {
    $appointment = Appointment::factory()->confirmed()->create();

    $mail = (new AppointmentConfirmed($appointment))->toMail($appointment->user);
    $attachment = icsAttachment($mail);

    expect($attachment)->not->toBeNull()
        ->and($attachment->getFilename())->toBe('appointment.ics');
});

it('attaches a PII-minimized ics to the AppointmentRescheduled mail', function () {
    $org = Organization::factory()->create(['name' => 'Rifugio Speranza', 'city' => 'Milano']);
    $animal = Animal::factory()->forOrganization($org)->create(['name' => 'Rex']);
    $appointment = Appointment::factory()->confirmed()->forAnimal($animal)->create();

    $mail = (new AppointmentRescheduled($appointment, now()->subDay()))->toMail($appointment->user);

    $attachment = icsAttachment($mail);

    expect($attachment)->not->toBeNull()
        ->and($attachment->getContentType())->toContain('text/calendar')
        ->and($attachment->getBody())
        ->toContain('BEGIN:VEVENT')
        ->not->toContain($appointment->user->email)
        ->and($attachment->getFilename())->toBe('appointment.ics');
});
