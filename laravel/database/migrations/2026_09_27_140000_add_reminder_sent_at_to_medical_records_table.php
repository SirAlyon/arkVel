<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Marca l'invio del promemoria di scadenza sanitaria (scadenzario, Fase 3).
        // Rende idempotente il comando arkvel:send-medical-reminders: una voce viene
        // notificata UNA SOLA volta per ciclo di scadenza (finché next_due_at non
        // cambia). Nullable: null = mai promemorizzata.
        Schema::table('medical_records', function (Blueprint $table): void {
            $table->timestamp('reminder_sent_at')->nullable()->after('next_due_at');
        });
    }

    public function down(): void
    {
        Schema::table('medical_records', function (Blueprint $table): void {
            $table->dropColumn('reminder_sent_at');
        });
    }
};
