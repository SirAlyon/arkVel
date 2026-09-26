<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // La cartella sanitaria è animal-scoped: NON porta organization_id.
        // L'isolamento per tenant passa dall'animale (animal->organization),
        // verificato dalla Policy; evita denormalizzazioni e derive cross-tenant.
        // Gli allegati (referti, PDF, foto) sono gestiti da media-library
        // (collection `attachments`), non da una colonna.
        Schema::create('medical_records', function (Blueprint $table): void {
            $table->id();

            // Cascade on delete: senza l'animale la cartella non ha significato.
            // La FK indicizza già animal_id.
            $table->foreignId('animal_id')->constrained()->cascadeOnDelete();

            // Enum applicativo salvato come string + cast verso MedicalRecordType.
            $table->string('type');

            // Data dell'evento sanitario (obbligatoria).
            $table->date('date');

            $table->string('vet_name')->nullable();
            $table->text('description')->nullable();

            // Scadenza/richiamo (es. prossima dose vaccino, prossima sverminazione).
            // Utile allo scadenzario sanitario avanzato (Fase 3).
            $table->date('next_due_at')->nullable();

            $table->timestamps();

            // Indici per gli scadenzari e i filtri per tipo.
            $table->index('type');
            $table->index('next_due_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_records');
    }
};
