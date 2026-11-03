<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Donazione a un ENTE tramite Stripe. ARCHITETTURA (P4-5): un solo account Stripe
        // di PIATTAFORMA (chiavi config-gated), donazioni registrate PER-ENTE. I payout
        // per-ente (Stripe Connect) sono FUORI SCOPE / enhancement futuro. Il ciclo di
        // vita (pending → completed/failed/canceled → refunded) è governato dall'enum
        // App\Enums\DonationStatus e sarà orchestrato dalle Action in App\Actions\Donations
        // (P4-6/P4-7) — colonne string + cast Eloquent verso gli enum PHP (mai enum DB).
        Schema::create('donations', function (Blueprint $table): void {
            $table->id();

            // Ente beneficiario della donazione. cascade: senza l'ente la donazione
            // registrata non ha più un destinatario nel dominio.
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            // Donatore autenticato (facoltativo): le donazioni ANONIME / da ospite sono
            // ammesse. nullOnDelete: se l'utente viene cancellato la donazione resta a
            // registro dell'ente, orfana del donatore (storico contabile preservato).
            $table->foreignId('donor_user_id')->nullable()->constrained('users')->nullOnDelete();

            // Dati del donatore per ospiti/ricevute (quando donor_user_id è null).
            $table->string('donor_name')->nullable();
            $table->string('donor_email')->nullable();

            // Importo in CENTESIMI (intero senza segno) per evitare float sul denaro,
            // coerente con le API Stripe (amount in minor units) + valuta ISO 4217.
            $table->unsignedInteger('amount');
            $table->string('currency')->default('eur');

            // Tipologia (one_off|recurring): valore string dell'enum DonationType.
            $table->string('type');

            // Stato del ciclo di vita: valore string dell'enum DonationStatus.
            $table->string('status')->default('pending');

            // Provider di pagamento (per ora sempre 'stripe'; stringa per estensibilità).
            $table->string('provider')->default('stripe');

            // Riferimento lato provider: Checkout session / PaymentIntent / subscription id.
            // Indicizzato per la riconciliazione via webhook (P4-6/P4-7).
            $table->string('provider_ref')->nullable();

            // Nota facoltativa del donatore.
            $table->text('message')->nullable();

            // Istante di incasso effettivo (impostato al passaggio a Completed).
            $table->timestamp('paid_at')->nullable();

            $table->timestamps();

            // Elenchi per ente filtrati/raggruppati per stato (dashboard KPI) e lookup
            // rapido del riferimento provider nella riconciliazione.
            $table->index(['organization_id', 'status']);
            $table->index(['provider_ref']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('donations');
    }
};
