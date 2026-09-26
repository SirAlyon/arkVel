<?php

declare(strict_types=1);

use App\Enums\AnimalStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Campi anagrafici rinviati a una slice futura (WBS §4, fuori scope qui):
        // weight, color, training_level, fiv_felv, special_needs, age_unit.
        // Anche la conversione di intake_source in enum e' rinviata (slice 5).
        Schema::create('animals', function (Blueprint $table): void {
            $table->id();

            // Tenant: l'animale appartiene a un ente. La FK indicizza gia
            // organization_id; cascade on delete perche senza ente l'anagrafica
            // non ha senso (i record collegati usano invece soft delete).
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('slug');

            // Enum applicativi salvati come string + cast Eloquent (mai enum DB).
            $table->string('species'); // App\Enums\Species
            $table->string('breed')->nullable();
            $table->string('sex'); // App\Enums\Sex
            $table->string('size')->nullable(); // App\Enums\Size

            // Eta: due sorgenti mutuamente utili. `birthdate` quando nota; in
            // alternativa `estimated_age_months` (eta stimata in mesi) quando la
            // data di nascita e ignota, tipico degli animali da recupero.
            $table->date('birthdate')->nullable();
            $table->unsignedSmallInteger('estimated_age_months')->nullable();

            // Il microchip e univoco a livello anagrafe nazionale: unique globale.
            // In PostgreSQL piu NULL non violano il vincolo unique (animali senza
            // chip registrata restano ammessi).
            $table->string('microchip_number')->nullable()->unique();

            // Stato sanitario.
            $table->boolean('sterilized')->default(false);
            $table->boolean('vaccinated')->default(false);
            $table->boolean('dewormed')->default(false);
            $table->text('health_notes')->nullable();

            // Comportamento e compatibilita. I flag good_with_* sono nullable:
            // NULL = "sconosciuto" (diverso da false = "non compatibile").
            $table->string('temperament')->nullable();
            $table->string('energy_level')->nullable(); // App\Enums\EnergyLevel
            $table->boolean('good_with_kids')->nullable();
            $table->boolean('good_with_dogs')->nullable();
            $table->boolean('good_with_cats')->nullable();

            // Descrizione multilingua (spatie/laravel-translatable): locale => testo.
            $table->json('description')->nullable();

            // Ciclo di vita: cast verso App\Enums\AnimalStatus. Le transizioni
            // passano dall'Action di dominio, mai mutando la colonna a mano.
            $table->string('status')->default(AnimalStatus::Draft->value);

            // Ingresso in struttura.
            $table->date('intake_date')->nullable();
            $table->string('intake_source')->nullable();

            // Sospensione candidature (es. animale non ancora pronto).
            $table->boolean('application_paused')->default(false);
            $table->string('pause_reason')->nullable();

            $table->boolean('featured')->default(false);

            $table->timestamps();

            // Gli animali non si cancellano fisicamente: referenziati da cartelle
            // mediche e adozioni storiche. Soft delete.
            $table->softDeletes();

            // Slug univoco PER ENTE (catalogo multi-tenant), non globale.
            $table->unique(['organization_id', 'slug']);
            $table->index('status');
            $table->index('species');
            $table->index('featured');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('animals');
    }
};
