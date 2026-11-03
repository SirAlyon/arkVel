<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Store chiave/valore delle impostazioni di PIATTAFORMA (una sola istanza per
        // l'intero ArkVel, non per ente). Serve a configurare da UI cose come le
        // credenziali dell'app OAuth Google Calendar, senza toccare l'.env del server.
        //
        // SICUREZZA: il valore è cifrato a riposo (cast 'encrypted' sul model
        // PlatformSetting): anche il client_secret non compare mai in chiaro nel DB.
        Schema::create('platform_settings', function (Blueprint $table): void {
            $table->id();

            // Chiave logica dell'impostazione (es. google_calendar.client_id). Unica:
            // una sola riga per chiave (semantica di upsert nello store).
            $table->string('key')->unique();

            // Valore cifrato. TEXT perché il ciphertext è più lungo del testo in chiaro.
            // Nullable: una chiave può esistere senza valore.
            $table->text('value')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
    }
};
