<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Profilo/questionario dell'adottante, legato 1:1 all'utente. Non è una
        // risorsa d'ente (tenant): l'ownership è per USER, non per organization.
        //
        // TODO (GDPR — Fase 5): definire retention e diritto all'oblio. I dati di
        // contatto/indirizzo sono minimizzati (niente via/civico) e i consensi
        // sono tracciati (consent_* + consented_at); la cancellazione automatica
        // e l'esportabilità saranno gestite nella slice privacy.
        Schema::create('adopter_profiles', function (Blueprint $table): void {
            $table->id();

            // 1:1 con l'utente. unique => un solo profilo per utente. cascade on
            // delete: senza l'utente il profilo non ha ragione di esistere.
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            // --- Questionario ---
            // Enum applicativi salvati come string + cast Eloquent (mai enum DB).
            $table->string('housing_type')->nullable(); // App\Enums\HousingType
            $table->boolean('has_garden')->default(false);
            // fenced nullable: NULL = "non applicabile / non risposto" (rilevante
            // solo se has_garden = true).
            $table->boolean('fenced')->nullable();
            $table->string('rent_or_own')->nullable(); // App\Enums\RentOrOwn
            // Rilevante solo in affitto: se in affitto, deve essere true perché il
            // profilo sia completo.
            $table->boolean('landlord_allows_pets')->nullable();
            $table->unsignedTinyInteger('adults_in_home')->nullable();
            $table->unsignedTinyInteger('kids_in_home')->nullable();
            $table->text('other_pets')->nullable();
            $table->text('experience')->nullable();
            $table->unsignedTinyInteger('hours_alone_weekday')->nullable();
            $table->unsignedTinyInteger('hours_alone_weekend')->nullable();
            $table->text('lifestyle')->nullable();
            $table->text('ideal_animal')->nullable();
            $table->text('contingency_plan')->nullable();
            $table->boolean('previous_surrender')->nullable();
            $table->string('referral_source')->nullable();

            // --- Contatto / indirizzo (minimizzato per GDPR) ---
            $table->string('phone')->nullable();
            $table->string('country', 2)->nullable(); // ISO-3166 alpha-2
            $table->string('region')->nullable();
            $table->string('city')->nullable();
            $table->string('postal_code')->nullable();

            // --- Consensi GDPR ---
            $table->boolean('consent_data_processing')->default(false);
            $table->boolean('consent_marketing')->default(false);
            $table->timestamp('consented_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adopter_profiles');
    }
};
