<?php

declare(strict_types=1);

use App\Enums\MedicalRecordType;
use App\Models\Animal;
use App\Models\MedicalRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('creates a medical record from the factory', function () {
    $record = MedicalRecord::factory()->create();

    expect($record->exists)->toBeTrue()
        ->and($record->type)->toBeInstanceOf(MedicalRecordType::class);
});

it('casts the type column to the enum', function () {
    $record = MedicalRecord::factory()->create(['type' => MedicalRecordType::Surgery]);

    expect($record->fresh()->type)->toBe(MedicalRecordType::Surgery);
});

it('casts the date columns to Carbon', function () {
    $record = MedicalRecord::factory()->create([
        'date' => '2024-03-10',
        'next_due_at' => '2025-03-10',
    ]);

    $fresh = $record->fresh();

    expect($fresh->date)->toBeInstanceOf(Carbon::class)
        ->and($fresh->date->toDateString())->toBe('2024-03-10')
        ->and($fresh->next_due_at)->toBeInstanceOf(Carbon::class)
        ->and($fresh->next_due_at->toDateString())->toBe('2025-03-10');
});

it('allows a null next_due_at', function () {
    $record = MedicalRecord::factory()->create(['next_due_at' => null]);

    expect($record->fresh()->next_due_at)->toBeNull();
});

it('belongs to an animal', function () {
    $animal = Animal::factory()->create();
    $record = MedicalRecord::factory()->forAnimal($animal)->create();

    expect($record->animal)->toBeInstanceOf(Animal::class)
        ->and($record->animal->getKey())->toBe($animal->getKey());
});

it('is reachable through the animal hasMany relation', function () {
    $animal = Animal::factory()->create();
    MedicalRecord::factory()->count(3)->forAnimal($animal)->create();

    expect($animal->medicalRecords)->toHaveCount(3)
        ->and($animal->medicalRecords->first())->toBeInstanceOf(MedicalRecord::class);
});

it('is deleted in cascade when the animal is force deleted', function () {
    $animal = Animal::factory()->create();
    MedicalRecord::factory()->count(2)->forAnimal($animal)->create();

    $animal->forceDelete();

    expect(MedicalRecord::query()->count())->toBe(0);
});

it('attaches a pdf document to the attachments collection', function () {
    Storage::fake('public');

    $record = MedicalRecord::factory()->create();

    // Il file fake deve avere contenuto reale: un file vuoto verrebbe rilevato
    // come `application/x-empty` e rifiutato dalla collection (accetta pdf/img).
    $pdf = UploadedFile::fake()->createWithContent(
        'referto.pdf',
        "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF",
    );

    $record->addMedia($pdf)->toMediaCollection('attachments');

    // L'accettazione stessa prova che il mime è tra quelli consentiti (pdf/img):
    // un tipo non ammesso avrebbe lanciato FileUnacceptableForCollection.
    expect($record->getMedia('attachments'))->toHaveCount(1)
        ->and($record->getFirstMedia('attachments')?->file_name)->toBe('referto.pdf');
});

it('attaches an image to the attachments collection', function () {
    Storage::fake('public');

    $record = MedicalRecord::factory()->create();

    $record->addMedia(UploadedFile::fake()->image('foto.jpg'))
        ->toMediaCollection('attachments');

    expect($record->getMedia('attachments'))->toHaveCount(1);
});
