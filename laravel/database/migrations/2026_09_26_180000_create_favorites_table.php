<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Wishlist: la lista dei preferiti di un utente. Pivot semplice fra utenti
        // e animali, SELF-OWNED per definizione (ogni riga appartiene all'utente
        // che l'ha creata): non è una risorsa d'ente (tenant), l'ownership è per
        // USER. Cascade on delete su entrambe le FK: senza l'utente o l'animale la
        // riga di preferito non ha ragione di esistere.
        Schema::create('favorites', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('animal_id')->constrained()->cascadeOnDelete();

            // Un utente può mettere un animale tra i preferiti UNA sola volta:
            // idempotenza del toggle garantita a livello di schema.
            $table->unique(['user_id', 'animal_id']);

            // created_at usato per ordinare la wishlist (più recenti prima).
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favorites');
    }
};
