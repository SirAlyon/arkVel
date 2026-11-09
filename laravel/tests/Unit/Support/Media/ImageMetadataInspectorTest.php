<?php

declare(strict_types=1);

use App\Support\Media\ImageMetadataInspector;
use Tests\Support\Media\ExifJpeg;

/*
 | Rilevamento dei metadati senza decodificare l'immagine: è ciò che rende idempotente la
 | ripulitura (App\Actions\Media\StripImageMetadata).
 */

beforeEach(function (): void {
    $this->directory = sys_get_temp_dir().'/arkvel-inspector-'.uniqid();
    mkdir($this->directory);
});

afterEach(function (): void {
    array_map(unlink(...), glob($this->directory.'/*') ?: []);
    rmdir($this->directory);
});

/**
 * Salva un'immagine 4×4 generata da GD nel formato richiesto e ne restituisce i byte.
 */
function gdImageBytes(string $format): string
{
    $image = imagecreatetruecolor(4, 4);

    ob_start();
    match ($format) {
        'png' => imagepng($image),
        'webp' => imagewebp($image),
        default => imagejpeg($image),
    };

    return (string) ob_get_clean();
}

/**
 * Un chunk PNG valido (lunghezza, tipo, dati, CRC).
 */
function pngChunk(string $type, string $data): string
{
    return pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
}

function inspect(string $directory, string $name, string $bytes, string $mimeType): bool
{
    file_put_contents($directory.'/'.$name, $bytes);

    return (new ImageMetadataInspector)->hasMetadata($directory.'/'.$name, $mimeType);
}

it('finds EXIF in a JPEG and nothing in one written by GD', function (): void {
    $exif = ExifJpeg::create($this->directory.'/exif.jpg');

    expect((new ImageMetadataInspector)->hasMetadata($exif, 'image/jpeg'))->toBeTrue()
        ->and(inspect($this->directory, 'clean.jpg', gdImageBytes('jpg'), 'image/jpeg'))->toBeFalse();
});

it('finds text and EXIF chunks in a PNG', function (string $chunk, bool $expected): void {
    $png = gdImageBytes('png');
    // Dopo la firma (8 byte) e l'IHDR (8 + 13 + 4 byte).
    $withChunk = substr($png, 0, 33).pngChunk($chunk, 'GPS 45.46 9.19').substr($png, 33);

    expect(inspect($this->directory, 'image.png', $withChunk, 'image/png'))->toBe($expected);
})->with([
    'eXIf' => ['eXIf', true],
    'tEXt' => ['tEXt', true],
    'iTXt' => ['iTXt', true],
    'zTXt' => ['zTXt', true],
    'gAMA (not metadata)' => ['gAMA', false],
]);

it('finds EXIF and XMP chunks in a WebP', function (string $chunk, bool $expected): void {
    $webp = gdImageBytes('webp');
    $body = substr($webp, 12).$chunk.pack('V', 5)."GPS!!\x00";
    $withChunk = 'RIFF'.pack('V', 4 + strlen($body)).'WEBP'.$body;

    expect(inspect($this->directory, 'image.webp', $withChunk, 'image/webp'))->toBe($expected)
        ->and(inspect($this->directory, 'clean.webp', $webp, 'image/webp'))->toBeFalse();
})->with([
    'EXIF' => ['EXIF', true],
    'XMP' => ['XMP ', true],
    'ICCP (not metadata)' => ['ICCP', false],
]);

it('ignores formats it does not know and files that are not images', function (): void {
    expect(inspect($this->directory, 'doc.pdf', '%PDF-1.4 Exif GPS', 'application/pdf'))->toBeFalse()
        ->and(inspect($this->directory, 'fake.png', 'not a png', 'image/png'))->toBeFalse()
        ->and(inspect($this->directory, 'fake.webp', 'RIFF', 'image/webp'))->toBeFalse()
        ->and(inspect($this->directory, 'fake.jpg', 'nope', 'image/jpeg'))->toBeFalse();
});
