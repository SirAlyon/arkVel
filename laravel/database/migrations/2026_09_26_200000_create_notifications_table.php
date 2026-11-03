<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabella standard delle notifiche database di Laravel: alimenta il canale
     * `database` (es. ApplicationStatusChanged) e la UI notifiche del pannello
     * adottante (slice 11). Schema identico allo stub di framework
     * (`notifications:table`), qui versionato in repo per riproducibilità.
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            // `json` (non `text`): la UI notifiche di Filament interroga
            // `data->>'format'`. Su PostgreSQL l'operatore JSON `->>` NON esiste
            // per `text` (SQLSTATE 42883), quindi ogni pagina del pannello con la
            // campanella andrebbe in errore. `json` supporta `->>` nativamente; su
            // SQLite `json()` è memorizzato come text e Laravel traduce `->>` in
            // json_extract, così i test restano coerenti.
            $table->json('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
