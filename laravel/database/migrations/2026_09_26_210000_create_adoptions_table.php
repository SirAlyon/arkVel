<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Adozione FINALIZZATA: nasce SOLO dalla finalizzazione di una
        // candidatura in AdoptionPending (App\Actions\Adoptions\FinalizeAdoption).
        // È il record definitivo dell'atto di adozione: parti coinvolte, data,
        // eventuale contributo (fee) e riferimento al contratto PDF.
        Schema::create('adoptions', function (Blueprint $table): void {
            $table->id();

            // Animale adottato. cascade: l'adozione segue l'animale.
            $table->foreignId('animal_id')->constrained()->cascadeOnDelete();

            // Adottante. cascade: l'adozione appartiene all'adottante.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Ente che ha finalizzato l'adozione (tenant scoping). cascade.
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            // Candidatura da cui l'adozione è stata finalizzata. UNIQUE: una sola
            // adozione per candidatura (1:1). cascade con la candidatura.
            $table->foreignId('application_id')
                ->constrained('adoption_applications')
                ->cascadeOnDelete();
            $table->unique('application_id');

            // Data effettiva dell'adozione (finalizzazione).
            $table->timestamp('adopted_at');

            // Contributo di adozione (facoltativo) e valuta ISO-4217 (default EUR).
            $table->decimal('fee', 10, 2)->nullable();
            $table->string('currency', 3)->default('EUR');

            // Percorso del contratto PDF sul disco PRIVATO 'local'
            // (storage/app/private/contracts/...): mai pubblico.
            $table->string('contract_path')->nullable();

            // Stato del follow-up post-adozione: valore string dell'enum
            // App\Enums\FollowUpStatus, cast Eloquent verso l'enum PHP.
            $table->string('follow_up_status')->default('pending');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adoptions');
    }
};
