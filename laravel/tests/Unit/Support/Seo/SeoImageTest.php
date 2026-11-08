<?php

declare(strict_types=1);

use App\Models\Animal;
use App\Support\Seo\SeoImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('public');
});

it('has no share image for an animal without photos', function (): void {
    expect(app(SeoImage::class)->forAnimal(Animal::factory()->create()))->toBeNull();
});

it('uses the first photo, as an absolute URL of a generated conversion', function (): void {
    $animal = Animal::factory()->create();
    $animal->addMedia(UploadedFile::fake()->image('argo.jpg', 800, 600))->toMediaCollection('photos');
    $animal->addMedia(UploadedFile::fake()->image('avatar.jpg'))->toMediaCollection('avatar');

    $url = app(SeoImage::class)->forAnimal($animal->fresh());

    expect($url)->toStartWith('http')
        ->toContain('argo-'.SeoImage::CONVERSIONS[0])
        ->and(filter_var($url, FILTER_VALIDATE_URL))->not->toBeFalse();
});

it('falls back to the avatar', function (): void {
    $animal = Animal::factory()->create();
    $animal->addMedia(UploadedFile::fake()->image('avatar.jpg'))->toMediaCollection('avatar');

    expect(app(SeoImage::class)->forAnimal($animal->fresh()))->toContain('avatar-'.SeoImage::CONVERSIONS[0]);
});

it('prefers the 1200×630 og conversion and falls back to thumb while it is queued', function (): void {
    $animal = Animal::factory()->create();
    $media = $animal->addMedia(UploadedFile::fake()->image('argo.jpg', 1600, 1200))->toMediaCollection('photos');

    $media->generated_conversions = ['thumb' => true, 'og' => true];
    $media->save();
    expect(app(SeoImage::class)->forAnimal($animal->fresh()))->toContain('argo-og');

    $media->generated_conversions = ['thumb' => true];
    $media->save();
    expect(app(SeoImage::class)->forAnimal($animal->fresh()))->toContain('argo-thumb');
});

it('never points to a conversion that has not been generated yet', function (): void {
    $animal = Animal::factory()->create();
    $media = $animal->addMedia(UploadedFile::fake()->image('argo.jpg'))->toMediaCollection('photos');
    $media->generated_conversions = [];
    $media->save();

    expect(app(SeoImage::class)->forAnimal($animal->fresh()))->toBeNull();
});

it('percent-encodes non-ASCII file names instead of breaking the page', function (): void {
    $animal = Animal::factory()->create();
    $animal->addMedia(UploadedFile::fake()->image('perché.jpg'))->usingFileName('perché.jpg')->toMediaCollection('photos');

    $url = app(SeoImage::class)->forAnimal($animal->fresh());

    expect($url)->toContain('perch%C3%A9-'.SeoImage::CONVERSIONS[0])
        ->and(filter_var($url, FILTER_VALIDATE_URL))->not->toBeFalse();
});

it('keeps only absolute, valid URIs', function (string $url, ?string $expected): void {
    expect(SeoImage::absoluteUri($url))->toBe($expected);
})->with([
    'already valid' => ['https://arkvel.test/storage/1/argo-thumb.jpg', 'https://arkvel.test/storage/1/argo-thumb.jpg'],
    'accents and spaces' => ['https://arkvel.test/storage/1/città bella.jpg', 'https://arkvel.test/storage/1/citt%C3%A0%20bella.jpg'],
    'relative' => ['/storage/1/argo.jpg', null],
    'not a URL' => ['argo.jpg', null],
]);
