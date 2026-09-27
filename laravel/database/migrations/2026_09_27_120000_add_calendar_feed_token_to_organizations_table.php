<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Token segreto del feed iCal per-ente (Cal-3).
 *
 * Il token vive nell'URL di sottoscrizione (come le "secret ICS URL" di
 * Google/Fastmail): è quindi memorizzato IN CHIARO (non hashato one-way), perché va
 * confrontato con quello nell'URL — il segreto È la capacità di leggere il feed. È
 * nullable (generato al primo utilizzo) e unique (l'URL identifica un solo ente). La
 * rigenerazione sostituisce il valore, invalidando di fatto il vecchio URL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->string('calendar_feed_token')->nullable()->unique()->after('website');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropUnique(['calendar_feed_token']);
            $table->dropColumn('calendar_feed_token');
        });
    }
};
