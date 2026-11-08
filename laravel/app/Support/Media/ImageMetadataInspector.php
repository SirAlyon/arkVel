<?php

declare(strict_types=1);

namespace App\Support\Media;

/**
 * Dice se un file immagine porta metadati da togliere (EXIF, GPS, XMP, IPTC, testo), senza
 * decodificarlo: legge solo la struttura del contenitore.
 *
 * - JPEG: i segmenti APP1 (Exif e XMP) e APP13 (IPTC/Photoshop), via getimagesize();
 * - PNG: i chunk eXIf, tEXt, zTXt, iTXt;
 * - WebP: i chunk RIFF EXIF e «XMP ».
 *
 * GD non scrive nessuno di questi, quindi un file già ripulito risulta pulito: è ciò che
 * rende idempotente App\Actions\Media\StripImageMetadata (nessuna ricodifica inutile).
 */
final class ImageMetadataInspector
{
    private const array PNG_METADATA_CHUNKS = ['eXIf', 'tEXt', 'zTXt', 'iTXt'];

    private const array WEBP_METADATA_CHUNKS = ['EXIF', 'XMP '];

    public function hasMetadata(string $path, string $mimeType): bool
    {
        return match ($mimeType) {
            'image/jpeg' => $this->jpegHasMetadata($path),
            'image/png' => $this->pngHasMetadata($path),
            'image/webp' => $this->webpHasMetadata($path),
            default => false,
        };
    }

    private function jpegHasMetadata(string $path): bool
    {
        $segments = [];

        if (@getimagesize($path, $segments) === false) {
            return false;
        }

        return isset($segments['APP1']) || isset($segments['APP13']);
    }

    private function pngHasMetadata(string $path): bool
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return false;
        }

        try {
            if (fread($handle, 8) !== "\x89PNG\r\n\x1a\n") {
                return false;
            }

            while (strlen($header = (string) fread($handle, 8)) === 8) {
                /** @var array{length: int} $length */
                $length = unpack('Nlength', substr($header, 0, 4));
                $type = substr($header, 4, 4);

                if (in_array($type, self::PNG_METADATA_CHUNKS, true)) {
                    return true;
                }

                if ($type === 'IEND' || fseek($handle, $length['length'] + 4, SEEK_CUR) !== 0) {
                    return false;
                }
            }

            return false;
        } finally {
            fclose($handle);
        }
    }

    private function webpHasMetadata(string $path): bool
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return false;
        }

        try {
            $header = (string) fread($handle, 12);

            if (! str_starts_with($header, 'RIFF') || substr($header, 8, 4) !== 'WEBP') {
                return false;
            }

            while (strlen($chunk = (string) fread($handle, 8)) === 8) {
                /** @var array{size: int} $size */
                $size = unpack('Vsize', substr($chunk, 4, 4));

                if (in_array(substr($chunk, 0, 4), self::WEBP_METADATA_CHUNKS, true)) {
                    return true;
                }

                // I chunk RIFF hanno lunghezza pari: un byte di riempimento se è dispari.
                if (fseek($handle, $size['size'] + ($size['size'] % 2), SEEK_CUR) !== 0) {
                    return false;
                }
            }

            return false;
        } finally {
            fclose($handle);
        }
    }
}
