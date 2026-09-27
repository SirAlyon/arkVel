<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Marca l'invio del promemoria di appuntamento (Fase 3). Rende idempotente
        // il comando arkvel:send-appointment-reminders: un appuntamento confermato
        // viene promemorizzato UNA SOLA volta. Nullable: null = mai promemorizzato.
        Schema::table('appointments', function (Blueprint $table): void {
            $table->timestamp('reminder_sent_at')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropColumn('reminder_sent_at');
        });
    }
};
