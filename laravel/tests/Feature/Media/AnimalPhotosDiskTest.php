<?php

declare(strict_types=1);

use App\Models\Animal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * Regressione: le foto dell'animale DEVONO stare sul disco `public` (servito dal web su
 * /storage), non sul filesystem di default ('local' = storage/app/private, privato). Un
 * upload da Filament senza disco esplicito ereditava il default e finiva su un disco
 * privato → 404 sul sito pubblico. useDisk('public') sulle collection lo impedisce.
 */
it('stores animal photos on the public disk', function (): void {
    Storage::fake('public');

    $animal = Animal::factory()->create();

    $media = $animal
        ->addMedia(UploadedFile::fake()->image('kiba.jpg', 600, 600))
        ->toMediaCollection('photos');

    expect($media->disk)->toBe('public')
        ->and($media->conversions_disk)->toBe('public');
});

it('stores the animal avatar on the public disk', function (): void {
    Storage::fake('public');

    $animal = Animal::factory()->create();

    $media = $animal
        ->addMedia(UploadedFile::fake()->image('avatar.jpg', 400, 400))
        ->toMediaCollection('avatar');

    expect($media->disk)->toBe('public');
});
