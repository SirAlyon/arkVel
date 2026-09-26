<?php

declare(strict_types=1);

use App\Enums\OrganizationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table): void {
            $table->id();

            $table->string('name');
            $table->string('slug')->unique();
            // Cast applicativo verso App\Enums\OrganizationType (string in DB).
            $table->string('type');
            // Translatable (spatie/laravel-translatable): mappa locale => testo.
            $table->json('description')->nullable();

            // Dati legali UE.
            $table->string('legal_name')->nullable();
            $table->string('vat_number')->nullable();
            $table->string('tax_code')->nullable();
            $table->string('registration_number')->nullable();

            // Indirizzo.
            $table->string('country', 2); // ISO-3166 alpha-2, es. 'IT'.
            $table->string('region')->nullable();
            $table->string('province')->nullable();
            $table->string('city')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('address_line')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            // Contatti.
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('website')->nullable();

            // Ciclo di vita: cast applicativo verso App\Enums\OrganizationStatus.
            $table->string('status')->default(OrganizationStatus::Pending->value);
            $table->timestamp('verified_at')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('country');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
