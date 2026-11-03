<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Collega ogni addebito al proprio abbonamento (P4-7). Ogni invoice ricorrente
        // andata a buon fine crea una riga `donations` (type=recurring) con questa FK
        // valorizzata, così il registro per-ente mostra uniformemente una tantum e
        // ricorrenti. nullOnDelete: le donazioni una tantum restano scollegate (null) e,
        // se l'abbonamento venisse cancellato, gli addebiti storici restano a registro
        // dell'ente, orfani dell'abbonamento (storico contabile preservato).
        Schema::table('donations', function (Blueprint $table): void {
            $table->foreignId('recurring_donation_id')
                ->nullable()
                ->after('organization_id')
                ->constrained('recurring_donations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('donations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('recurring_donation_id');
        });
    }
};
