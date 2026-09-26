<?php

declare(strict_types=1);

use App\Models\Adoption;
use App\Models\AdoptionApplication;
use App\Models\Animal;
use App\Support\Contracts\ContractGenerator;
use App\Support\Pdf\DompdfContractGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('binds the ContractGenerator interface to the dompdf implementation', function () {
    expect(app(ContractGenerator::class))->toBeInstanceOf(DompdfContractGenerator::class);
});

it('renders a non-empty PDF for an adoption via the real dompdf generator', function () {
    $animal = Animal::factory()->create(['breed' => 'Meticcio']);
    $application = AdoptionApplication::factory()->forAnimal($animal)->adoptionPending()->create();
    $adoption = Adoption::factory()->forApplication($application)->create([
        'fee' => 50.00,
        'currency' => 'EUR',
    ]);

    $bytes = app(DompdfContractGenerator::class)->generate($adoption);

    // Un PDF valido inizia con l'header %PDF ed è tutt'altro che vuoto.
    expect($bytes)->toStartWith('%PDF')
        ->and(strlen($bytes))->toBeGreaterThan(1000);
});

it('renders a free-adoption contract when the fee is null', function () {
    $application = AdoptionApplication::factory()->adoptionPending()->create();
    $adoption = Adoption::factory()->forApplication($application)->create([
        'fee' => null,
    ]);

    $bytes = app(DompdfContractGenerator::class)->generate($adoption);

    expect($bytes)->toStartWith('%PDF');
});
