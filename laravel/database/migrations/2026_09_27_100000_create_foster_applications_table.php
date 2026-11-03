<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Richiesta di affido: un affidatario (user) si candida per un affido
        // temporaneo di un animale di un ente (organization). Lo stato è governato
        // dalla macchina a stati App\States\Foster\FosterApplicationState — colonna
        // string + cast Eloquent verso lo stato astratto (mai enum di colonna DB).
        Schema::create('foster_applications', function (Blueprint $table): void {
            $table->id();

            // Animale oggetto della richiesta. cascade: senza l'animale la
            // richiesta non ha ragione di esistere.
            $table->foreignId('animal_id')->constrained()->cascadeOnDelete();

            // Affidatario. cascade: la richiesta è di proprietà dell'utente.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Ente proprietario dell'animale (ridondato per query/tenant scoping);
            // coerente con animal.organization_id. cascade con l'ente.
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            // Operatore dell'ente che ha in carico la pratica. nullOnDelete: se
            // l'operatore viene rimosso, la richiesta resta ma senza assegnatario.
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();

            // Stato del ciclo di vita: valore string dell'enum
            // FosterApplicationStatus, cast verso FosterApplicationState.
            // Indicizzato: usato dalle guardie whereState e dai filtri di elenco.
            $table->string('status')->default('submitted')->index();

            $table->text('staff_notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_reason')->nullable();

            $table->timestamps();

            // Un utente può fare richiesta di affido una sola volta per lo stesso animale.
            $table->unique(['animal_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('foster_applications');
    }
};
