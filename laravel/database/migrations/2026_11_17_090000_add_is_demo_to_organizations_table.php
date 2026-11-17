<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ente DIMOSTRATIVO: un ente reale nel DB ma marcato come esempio, per mostrare il sito
 * durante la beta. Il sito pubblico lo segnala con un badge e il dominio rifiuta ogni
 * invio di dati personali o denaro verso di esso (candidature, affidi, visite, messaggi,
 * donazioni). La visibilità pubblica resta governata solo dallo stato di verifica.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->boolean('is_demo')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropColumn('is_demo');
        });
    }
};
