<?php

declare(strict_types=1);

use App\Actions\Media\StripImageMetadata;
use App\Listeners\Media\StripUploadedImageMetadata;
use App\Models\Animal;
use App\Models\MedicalRecord;
use App\Support\Media\ImageMetadataInspector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAddedEvent;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\Support\Media\ExifJpeg;

/*
 | Privacy (piano UI §4.6): le foto caricate perdono EXIF/GPS già nell'originale, prima
 | che thumb e conversioni lo leggano (App\Listeners\Media\StripUploadedImageMetadata).
 */

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('public');
    $this->exifPhoto = ExifJpeg::create(sys_get_temp_dir().'/arkvel-exif-'.uniqid().'.jpg', 64, 32, orientation: 6);
});

afterEach(function (): void {
    @unlink($this->exifPhoto);
});

/**
 * I file generati per un media (conversioni e responsive images) sul disco finto.
 *
 * @return list<string>
 */
function generatedFiles(Media $media): array
{
    $directory = dirname($media->getPathRelativeToRoot());

    return array_values(array_filter(
        Storage::disk('public')->allFiles($directory),
        static fn (string $path): bool => $path !== $media->getPathRelativeToRoot(),
    ));
}

it('starts from a photo that really carries GPS coordinates', function (): void {
    expect(ExifJpeg::gpsKeys($this->exifPhoto))->toContain('GPSLatitude', 'GPSLongitude');
});

it('strips GPS from the original and from every conversion, keeping the orientation', function (): void {
    $media = Animal::factory()->create()
        ->addMedia($this->exifPhoto)
        ->preservingOriginal()
        ->toMediaCollection('photos')
        ->refresh();

    $original = $media->getPath();
    $files = generatedFiles($media);

    expect(ExifJpeg::gpsKeys($original))->toBe([])
        ->and(app(ImageMetadataInspector::class)->hasMetadata($original, 'image/jpeg'))->toBeFalse()
        // Orientation 6 applicata ai pixel: 64×32 salvato, 32×64 mostrato.
        ->and(array_slice((array) getimagesize($original), 0, 2))->toBe([32, 64])
        ->and($media->size)->toBe(filesize($original))
        ->and($media->hasGeneratedConversion('card'))->toBeTrue()
        ->and($files)->not->toBe([]);

    foreach ($files as $file) {
        $path = Storage::disk('public')->path($file);

        expect(str_contains((string) file_get_contents($path), "Exif\x00\x00"))->toBeFalse("{$file} still has an EXIF block")
            ->and(ExifJpeg::gpsKeys($path))->toBe([]);
    }
});

it('cleans the original before the synchronous thumb and the queued conversions read it', function (): void {
    Queue::fake();

    $media = Animal::factory()->create()
        ->addMedia($this->exifPhoto)
        ->preservingOriginal()
        ->toMediaCollection('avatar')
        ->refresh();

    // La thumb nasce dal file già ruotato (verticale): il listener gira prima di createDerivedFiles.
    expect(ExifJpeg::gpsKeys($media->getPath()))->toBe([])
        ->and(ExifJpeg::gpsKeys($media->getPath('thumb')))->toBe([])
        ->and(array_slice((array) getimagesize($media->getPath('thumb')), 0, 2))->toBe([32, 64]);
});

it('strips the text chunks of a PNG without loss', function (): void {
    $image = imagecreatetruecolor(8, 8);
    ob_start();
    imagepng($image);
    $png = (string) ob_get_clean();
    $text = 'Comment'."\x00".'45.46 N 9.19 E';
    $path = sys_get_temp_dir().'/arkvel-text-'.uniqid().'.png';
    file_put_contents($path, substr($png, 0, 33).pack('N', strlen($text)).'tEXt'.$text.pack('N', crc32('tEXt'.$text)).substr($png, 33));

    $media = Animal::factory()->create()->addMedia($path)->toMediaCollection('photos');

    expect(app(ImageMetadataInspector::class)->hasMetadata($media->getPath(), 'image/png'))->toBeFalse()
        ->and(mime_content_type($media->getPath()))->toBe('image/png')
        ->and(str_contains((string) file_get_contents($media->getPath()), '45.46'))->toBeFalse();
});

it('leaves an already clean photo untouched', function (): void {
    $media = Animal::factory()->create()
        ->addMedia($this->exifPhoto)
        ->preservingOriginal()
        ->toMediaCollection('photos');

    $checksum = md5_file($media->getPath());

    expect(app(StripImageMetadata::class)->handle($media))->toBeFalse()
        ->and(md5_file($media->getPath()))->toBe($checksum);
});

it('only cleans the collections the model asks for', function (): void {
    $record = MedicalRecord::factory()->create();
    $media = $record->addMedia($this->exifPhoto)->preservingOriginal()->toMediaCollection('attachments');

    expect(ExifJpeg::gpsKeys($media->getPath()))->toContain('GPSLatitude');
});

it('logs a failed cleaning instead of throwing, so the upload goes on', function (): void {
    $media = Animal::factory()->create()
        ->addMedia($this->exifPhoto)
        ->preservingOriginal()
        ->toMediaCollection('photos');

    // Un WebP con un chunk EXIF ma senza immagine: da ripulire, ma GD non lo decodifica.
    $exif = 'EXIF'.pack('V', 4).'GPS!';
    Storage::disk('public')->put($media->getPathRelativeToRoot(), 'RIFF'.pack('V', 4 + strlen($exif)).'WEBP'.$exif);
    $media->forceFill(['mime_type' => 'image/webp'])->saveQuietly();

    Log::spy();

    app(StripUploadedImageMetadata::class)->handle(new MediaHasBeenAddedEvent($media));

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(static fn (string $message, array $context): bool => $context['media_id'] === $media->getKey()
            && $context['exception'] instanceof Throwable);
});
