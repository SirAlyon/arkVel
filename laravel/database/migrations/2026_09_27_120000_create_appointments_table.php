<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Appuntamento fra un richiedente (adottante/affidatario) e un ente su un
        // animale: visita, colloquio o controllo domiciliare. Il ciclo di vita
        // (request → confirm → complete) è governato dall'enum
        // App\Enums\AppointmentStatus (grafo delle transizioni nell'enum) e
        // orchestrato dalle Action in App\Actions\Appointments — colonne string +
        // cast Eloquent verso gli enum PHP (mai enum di colonna DB).
        Schema::create('appointments', function (Blueprint $table): void {
            $table->id();

            // Animale oggetto dell'appuntamento. cascade: senza l'animale
            // l'appuntamento non ha ragione di esistere.
            $table->foreignId('animal_id')->constrained()->cascadeOnDelete();

            // Richiedente (adottante/affidatario). cascade: l'appuntamento è
            // della persona che lo ha richiesto.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Ente proprietario dell'animale (ridondato per query/tenant scoping);
            // coerente con animal.organization_id. cascade con l'ente.
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            // Data/ora programmata dell'appuntamento. La durata è implicita
            // (Appointment::SLOT_MINUTES) e usata per il calcolo dell'overlap.
            $table->timestamp('scheduled_at');

            // Tipologia: valore string dell'enum AppointmentType.
            $table->string('type');

            // Stato del ciclo di vita: valore string dell'enum AppointmentStatus.
            $table->string('status')->default('requested');

            $table->text('notes')->nullable();

            $table->timestamps();

            // Indici per l'elenco a calendario e le query di overlap: per ente,
            // per animale e per utente, ordinati/filtrati sull'orario.
            $table->index(['organization_id', 'scheduled_at']);
            $table->index(['animal_id', 'scheduled_at']);
            $table->index(['user_id', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
