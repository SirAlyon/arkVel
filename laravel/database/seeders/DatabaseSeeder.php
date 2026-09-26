<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * NB: NON usiamo WithoutModelEvents. Il dominio si appoggia agli eventi
     * `creating` dei model (generazione automatica dello slug univoco via
     * GeneratesUniqueSlug) e a spatie/medialibrary: silenziarli produrrebbe
     * slug NULL e media non processati. La seminatura di ruoli/permessi resta
     * idempotente a prescindere.
     */
    public function run(): void
    {
        // I ruoli e permessi globali servono in OGNI ambiente (anche produzione):
        // vanno sempre seminati per primi ed è un'operazione idempotente.
        $this->call(RolesAndPermissionsSeeder::class);

        // I dati demo (Super Admin a password nota + PII fittizia) NON devono MAI
        // finire in produzione: privacy/GDPR by design. Prima difesa: li chiamiamo
        // solo negli ambienti non produttivi. Seconda difesa: DemoDataSeeder::run
        // early-returna se rileva la produzione.
        if (app()->environment('local', 'testing')) {
            $this->call(DemoDataSeeder::class);
        }
    }
}
