<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Donazione RICORRENTE a un ENTE tramite un abbonamento Stripe (P4-7). Ogni riga è
        // l'abbonamento mensile di UN utente verso UN ente: a differenza della donazione
        // una tantum, la ricorrente richiede un account (serve a gestirla/disdirla), per
        // cui user_id è NOT NULL. Il ciclo di vita (pending → active → past_due → canceled)
        // è governato dall'enum App\Enums\RecurringDonationStatus e orchestrato dalle Action
        // in App\Actions\Donations — colonna string + cast Eloquent (mai enum di colonna DB).
        // Ogni addebito andato a buon fine crea una riga nel registro `donations`
        // (type=recurring), collegata via donations.recurring_donation_id.
        Schema::create('recurring_donations', function (Blueprint $table): void {
            $table->id();

            // Utente titolare dell'abbonamento. cascade: senza l'utente l'abbonamento
            // (che è suo e solo lui può gestirlo) non ha più un titolare nel dominio.
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Ente beneficiario. cascade: senza l'ente la ricorrente non ha destinatario.
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            // Importo del singolo addebito in CENTESIMI (intero) + valuta ISO 4217.
            $table->integer('amount');
            $table->string('currency')->default('eur');

            // Cadenza dell'addebito. Per ora solo 'monthly' (mensile); stringa per
            // un'eventuale estensione futura (annuale, ecc.).
            $table->string('interval')->default('monthly');

            // Id dell'abbonamento lato Stripe (sub_...): valorizzato dal webhook
            // `checkout.session.completed` e chiave di riconciliazione per gli eventi
            // invoice.*/customer.subscription.*. Unico (un abbonamento = una riga).
            $table->string('stripe_subscription_id')->nullable()->unique();

            // Stato del ciclo di vita: valore string dell'enum RecurringDonationStatus.
            $table->string('status')->default('pending');

            // Istante di attivazione (prima conferma), fine periodo/grazia su disdetta a
            // fine ciclo, e istante di disdetta effettiva.
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('canceled_at')->nullable();

            $table->timestamps();

            // Elenco delle ricorrenti di un utente filtrate per stato (pagina /account).
            $table->index(['user_id', 'status']);
            // Elenco per ente (eventuali KPI di raccolta ricorrente).
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_donations');
    }
};
