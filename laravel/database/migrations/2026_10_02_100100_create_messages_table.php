<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Singolo messaggio in un thread di conversazione. Immutabile: solo corpo
        // testuale e mittente. L'orchestrazione (creazione, aggiornamento del
        // thread, notifica alla controparte) vive nelle Action in
        // App\Actions\Messaging — mai nel model.
        Schema::create('messages', function (Blueprint $table): void {
            $table->id();

            // Thread di appartenenza. cascade: i messaggi non hanno senso senza il
            // loro thread.
            $table->foreignId('message_thread_id')->constrained()->cascadeOnDelete();

            // Mittente del messaggio (adottante oppure operatore dell'ente).
            // cascade con l'utente.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->text('body');

            $table->timestamps();

            // Lettura cronologica dei messaggi di un thread (l'id è monotono
            // crescente, quindi ordina come created_at ma in modo stabile).
            $table->index(['message_thread_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
