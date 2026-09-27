<?php

declare(strict_types=1);

use App\Enums\AppointmentStatus;
use App\Jobs\SyncAppointmentToCalendarsJob;
use App\Models\Animal;
use App\Models\Appointment;
use App\Models\CalendarConnection;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

/**
 * L'observer traduce OGNI mutazione di un appuntamento in un dispatch della pipeline
 * di push — ma SOLO quando l'ente ha almeno una connessione attiva (guardia
 * economica). Questi test provano entrambi i lati della guardia e la copertura
 * create/update, con il Bus finto per non eseguire davvero il job.
 */
it('dispatches an upsert sync job on create when the org has an active connection', function () {
    Bus::fake();

    $org = Organization::factory()->create();
    CalendarConnection::factory()->log()->active()->forOrganization($org)->create();
    $animal = Animal::factory()->forOrganization($org)->create();

    $appointment = Appointment::factory()->requested()->forAnimal($animal)->create();

    Bus::assertDispatched(
        SyncAppointmentToCalendarsJob::class,
        fn (SyncAppointmentToCalendarsJob $job): bool => $job->appointmentId === $appointment->getKey()
            && $job->operation === SyncAppointmentToCalendarsJob::OP_UPSERT,
    );
});

it('does NOT dispatch when the org has no active connection (guard is cheap)', function () {
    Bus::fake();

    $org = Organization::factory()->create();
    // Nessuna connessione attiva: solo una disconnessa.
    CalendarConnection::factory()->log()->disconnected()->forOrganization($org)->create();
    $animal = Animal::factory()->forOrganization($org)->create();

    Appointment::factory()->requested()->forAnimal($animal)->create();

    Bus::assertNotDispatched(SyncAppointmentToCalendarsJob::class);
});

it('dispatches on a status change (confirm / reschedule)', function () {
    $org = Organization::factory()->create();
    CalendarConnection::factory()->log()->active()->forOrganization($org)->create();
    $animal = Animal::factory()->forOrganization($org)->create();
    $appointment = Appointment::factory()->requested()->forAnimal($animal)->create();

    // Fake DOPO la creazione, così misuriamo solo il dispatch della mutazione.
    Bus::fake();

    // Cambio di stato (conferma).
    $appointment->update(['status' => AppointmentStatus::Confirmed]);

    // Spostamento (reschedule = cambio di scheduled_at).
    $appointment->update(['scheduled_at' => now()->addDays(10)->startOfHour()]);

    Bus::assertDispatched(SyncAppointmentToCalendarsJob::class, 2);
});

it('dispatches a delete sync job on deletion', function () {
    $org = Organization::factory()->create();
    CalendarConnection::factory()->log()->active()->forOrganization($org)->create();
    $animal = Animal::factory()->forOrganization($org)->create();
    $appointment = Appointment::factory()->requested()->forAnimal($animal)->create();

    Bus::fake();

    $appointment->delete();

    Bus::assertDispatched(
        SyncAppointmentToCalendarsJob::class,
        fn (SyncAppointmentToCalendarsJob $job): bool => $job->operation === SyncAppointmentToCalendarsJob::OP_DELETE,
    );
});
