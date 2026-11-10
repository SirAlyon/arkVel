<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Diritto all'oblio (GDPR art. 17): marcatore di ANONIMIZZAZIONE dell'account.
 *
 * Scelta d'architettura: anonimizzazione IN-PLACE, non hard delete. Conserviamo
 * le righe operative (candidature, affidi, appuntamenti, donazioni per obblighi
 * contabili) legate all'utente ora anonimo, per preservare l'integrità
 * referenziale e la storia dell'ente in forma anonima. `anonymized_at` è il
 * timestamp dell'operazione: non-null = account anonimizzato (login impossibile,
 * PII rimossa). Indicizzato per filtrare/escludere gli account anonimizzati.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('anonymized_at')->nullable()->index()->after('email_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['anonymized_at']);
            $table->dropColumn('anonymized_at');
        });
    }
};
