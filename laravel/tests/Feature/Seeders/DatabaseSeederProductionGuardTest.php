<?php

declare(strict_types=1);

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Nessuna scrittura reale su disco se il seeder demo dovesse (per errore)
    // tentare di generare immagini placeholder.
    Storage::fake('public');

    // Forziamo l'ambiente a `production` per verificare la guardia GDPR.
    app()->detectEnvironment(fn (): string => 'production');
});

afterEach(function () {
    // Ripristiniamo l'ambiente di test per non contaminare gli altri test.
    app()->detectEnvironment(fn (): string => 'testing');
});

it('seeds roles and permissions but no demo data in production', function () {
    // Risolviamo ed eseguiamo i seeder direttamente (senza il comando artisan
    // db:seed, che in produzione chiederebbe conferma interattiva): così
    // esercitiamo esattamente la guardia d'ambiente in DatabaseSeeder::run.
    app(DatabaseSeeder::class)->run();

    // I ruoli/permessi globali DEVONO essere presenti anche in produzione.
    expect(Role::query()->where('name', RoleName::SuperAdmin->value)->exists())->toBeTrue()
        ->and(Permission::query()->where('name', PermissionName::AnimalView->value)->exists())->toBeTrue();

    // I dati demo (Super Admin a password nota + PII fittizia) NON devono esistere.
    expect(User::query()->where('email', 'admin@arkvel.test')->exists())->toBeFalse()
        ->and(User::count())->toBe(0)
        ->and(Organization::count())->toBe(0);
});

it('early-returns from DemoDataSeeder when invoked directly in production', function () {
    // Difesa in profondità: anche invocando DemoDataSeeder da solo (bypassando la
    // guardia in DatabaseSeeder) non deve creare nulla in produzione.
    app(DemoDataSeeder::class)->run();

    expect(User::query()->where('email', 'admin@arkvel.test')->exists())->toBeFalse()
        ->and(User::count())->toBe(0)
        ->and(Organization::count())->toBe(0);
});
