<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Connessione di un ente a un calendario esterno (Google/CalDAV) verso cui
        // ArkVel sincronizza gli appuntamenti. È la spina dorsale del sync a due vie
        // (Cal-4..7): qui vivono provider, credenziali (cifrate), token incrementale,
        // direzione, stato operativo ed esito dell'ultima sincronizzazione. Gli enum
        // applicativi sono colonne string + cast Eloquent (mai enum di colonna DB).
        Schema::create('calendar_connections', function (Blueprint $table): void {
            $table->id();

            // Ente proprietario della connessione (tenant). cascade: senza l'ente la
            // connessione non ha ragione di esistere.
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            // Provider: valore string dell'enum CalendarProvider (google/caldav/log).
            $table->string('provider');

            // Nome scelto dall'utente per identificare la connessione in UI.
            $table->string('name');

            // Id del calendario esterno: Google calendarId oppure URL della collezione
            // CalDAV. Nullo finché il provider reale non lo risolve (es. connessione demo).
            $table->string('external_calendar_id')->nullable();

            // Credenziali del provider (token OAuth o creds CalDAV) come JSON CIFRATO
            // a riposo (cast 'encrypted:array' sul model): mai in chiaro nel DB. TEXT
            // perché il ciphertext è più lungo dei valori originali.
            $table->text('credentials')->nullable();

            // Token per il pull incrementale (Cal-7): Google syncToken / CalDAV ctag.
            $table->string('sync_token')->nullable();

            // Direzione: valore string dell'enum CalendarSyncDirection (push/both).
            $table->string('direction')->default('both');

            // Stato operativo: valore string dell'enum CalendarConnectionStatus.
            $table->string('status')->default('active');

            // Esito dell'ultima sincronizzazione riuscita e dettaglio dell'ultimo errore.
            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_error')->nullable();

            $table->timestamps();

            // Una sola connessione per (ente, provider, calendario esterno): evita
            // doppioni verso lo stesso calendario. external_calendar_id nullable è
            // ammesso nell'unique (una sola connessione "non ancora risolta" per provider).
            $table->unique(['organization_id', 'provider', 'external_calendar_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_connections');
    }
};
