<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Affido fisico: la collocazione temporanea di un animale di un ente
        // (organization) presso un affidatario (user). Nasce normalmente da una
        // richiesta di affido approvata (foster_application), ma la FK è nullable
        // per ammettere in futuro affidi diretti/di emergenza senza pratica
        // formale. Type e status sono string + cast Eloquent verso gli enum
        // FosterPlacementType / FosterPlacementStatus (mai enum di colonna DB).
        Schema::create('foster_placements', function (Blueprint $table): void {
            $table->id();

            // Animale collocato. cascade: senza l'animale l'affido non esiste.
            $table->foreignId('animal_id')->constrained()->cascadeOnDelete();

            // Affidatario che ospita l'animale. cascade: l'affido è legato all'utente.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Ente proprietario dell'animale (ridondato per query/tenant scoping);
            // coerente con animal.organization_id. cascade con l'ente.
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            // Pratica di affido di origine (1:1). nullable + nullOnDelete: se la
            // pratica viene rimossa, lo storico dell'affido resta, scollegato.
            $table->foreignId('foster_application_id')->nullable()
                ->constrained('foster_applications')->nullOnDelete();

            // Motivo dell'affido: valore string dell'enum FosterPlacementType.
            $table->string('type');

            $table->date('start_date');
            $table->date('end_date')->nullable();

            // Stato del ciclo di vita: valore string dell'enum FosterPlacementStatus.
            // Il default 'active' rispecchia FosterPlacementStatus::Active.
            $table->string('status')->default('active');

            $table->text('notes')->nullable();

            $table->timestamps();

            // Interroga l'affido attivo di un animale. NON usiamo un unique parziale
            // ("un solo affido attivo per animale"): la partial-index syntax è
            // fragile cross-DB (SQLite nei test) — quella regola è imposta
            // nell'Action della slice 3 con un lock di riga.
            $table->index(['animal_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('foster_placements');
    }
};
