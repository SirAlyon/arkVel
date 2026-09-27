<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Mappa un appuntamento ArkVel all'evento che lo rappresenta su una specifica
        // connessione esterna. È la tabella di correlazione che rende il push
        // IDEMPOTENTE: sapere se un appuntamento è già stato creato altrove (e con quale
        // id/etag) permette di aggiornarlo invece di duplicarlo, e di cancellarlo
        // quando l'appuntamento sparisce.
        Schema::create('appointment_external_events', function (Blueprint $table): void {
            $table->id();

            // Appuntamento di origine. cascade: senza l'appuntamento la mappatura non
            // ha senso (e il push di delete verso l'esterno è comunque gestito dal job).
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();

            // Connessione esterna su cui vive l'evento. cascade: rimuovere una
            // connessione (disconnessione) elimina anche le sue mappature.
            $table->foreignId('calendar_connection_id')->constrained()->cascadeOnDelete();

            // Id dell'evento lato provider (Google eventId / CalDAV href).
            $table->string('external_event_id');

            // ETag/versione dell'evento remoto per l'aggiornamento condizionale (evita
            // di sovrascrivere modifiche concorrenti); nullo se il provider non lo espone.
            $table->string('etag')->nullable();

            // Istante dell'ultimo push riuscito verso il provider.
            $table->timestamp('last_pushed_at')->nullable();

            $table->timestamps();

            // Un solo evento esterno per (appuntamento, connessione): garanzia di
            // idempotenza a livello di schema, non solo applicativa.
            $table->unique(['appointment_id', 'calendar_connection_id']);

            // Lookup dell'evento per connessione + id esterno (utile in pull/reconcile).
            $table->index(['calendar_connection_id', 'external_event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_external_events');
    }
};
