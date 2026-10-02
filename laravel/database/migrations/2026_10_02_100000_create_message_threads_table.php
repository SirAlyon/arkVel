<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Thread di conversazione fra un ente (organization) e un adottante (user).
        // Ha DUE partecipanti logici — l'ente (lato staff, molti utenti) e il
        // singolo adottante — ma qui modelliamo solo i due estremi: l'ente e
        // l'utente adottante. Gli operatori dell'ente partecipano attraverso la
        // membership verificata dell'ente, non con righe pivot.
        //
        // Lettura/non-letto: due marcatori temporali per lato
        // (adopter_last_read_at, org_last_read_at). Il non-letto di un lato sono i
        // messaggi creati DOPO il proprio last_read_at e inviati DALL'ALTRO lato.
        // Scelta pulita e testabile: nessuna tabella di stato per-utente, il
        // confine fra i due lati è deterministico (mittente == adottante ↔ lato
        // adottante). Vedi MessageThread::unreadCountForAdopter()/forOrg().
        Schema::create('message_threads', function (Blueprint $table): void {
            $table->id();

            // Ente partecipante (lato staff). cascade con l'ente: senza l'ente la
            // conversazione non ha più contesto.
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            // Adottante partecipante. cascade con l'utente: il thread è legato alla
            // persona con cui l'ente conversa.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Contesto opzionale: animale di riferimento. nullOnDelete: se l'animale
            // sparisce la conversazione resta, perde solo il riferimento.
            $table->foreignId('animal_id')->nullable()->constrained()->nullOnDelete();

            // Contesto opzionale: candidatura di adozione collegata. nullOnDelete:
            // come sopra, il thread sopravvive alla candidatura.
            $table->foreignId('adoption_application_id')->nullable()->constrained()->nullOnDelete();

            // Oggetto/titolo opzionale della conversazione.
            $table->string('subject')->nullable();

            // Timestamp dell'ultimo messaggio: pivot per l'ordinamento delle
            // conversazioni e per il calcolo del non-letto.
            $table->timestamp('last_message_at')->nullable();

            // Marcatori di lettura per lato (vedi nota in testa).
            $table->timestamp('adopter_last_read_at')->nullable();
            $table->timestamp('org_last_read_at')->nullable();

            $table->timestamps();

            // Elenco delle conversazioni per ente e per adottante, ordinate
            // sull'ultimo messaggio (inbox lato shelter e lato account).
            $table->index(['organization_id', 'last_message_at']);
            $table->index(['user_id', 'last_message_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_threads');
    }
};
