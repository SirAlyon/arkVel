<?php

declare(strict_types=1);

namespace App\Actions\Media;

use App\Support\Media\ImageMetadataInspector;
use Illuminate\Support\Str;
use Spatie\Image\Enums\ImageDriver;
use Spatie\Image\Image;
use Spatie\MediaLibrary\MediaCollections\Filesystem;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\TemporaryDirectory;

/**
 * Ripulisce dai metadati (EXIF con GPS, XMP, IPTC, testo) il file ORIGINALE di un media
 * immagine, ricodificandolo con GD nello stesso formato: GD non riscrive nessun metadato.
 *
 * - l'orientamento EXIF viene applicato ai pixel prima (spatie/image ruota in fase di
 *   caricamento), così la foto non si gira quando il tag sparisce;
 * - qualità alta (92) per i formati con perdita, PNG senza perdita: è una sola
 *   ricodifica, e solo se servono (App\Support\Media\ImageMetadataInspector). Un file già
 *   pulito non si tocca: l'azione è idempotente;
 * - si sostituisce il file sul disco del media (anche remoto: lo scarica in una cartella
 *   temporanea e lo ricarica con lo stesso nome) e se ne aggiorna la dimensione. Il
 *   profilo colore ICC si perde, come in tutte le conversioni GD.
 *
 * Restituisce true se il file è stato riscritto. Gli errori (file non decodificabile,
 * disco irraggiungibile) risalgono al chiamante.
 */
final readonly class StripImageMetadata
{
    /**
     * Formati ricodificabili, per tipo MIME (quelli accettati dalle collection foto).
     */
    private const array FORMATS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    private const int QUALITY = 92;

    public function __construct(
        private Filesystem $filesystem,
        private ImageMetadataInspector $inspector,
    ) {}

    public function handle(Media $media): bool
    {
        $format = self::FORMATS[$media->mime_type] ?? null;

        if ($format === null) {
            return false;
        }

        $temporaryDirectory = TemporaryDirectory::create();

        try {
            $path = $this->filesystem->copyFromMediaLibrary(
                $media,
                $temporaryDirectory->path(Str::random(32).'.'.$format),
            );

            if (! $this->inspector->hasMetadata($path, $media->mime_type)) {
                return false;
            }

            $image = Image::useImageDriver(ImageDriver::Gd)->loadFile($path)->format($format);

            if ($format !== 'png') {
                $image->quality(self::QUALITY);
            }

            $image->save($path);
            clearstatcache(true, $path);

            $this->filesystem->copyToMediaLibrary($path, $media, null, $media->file_name);

            $media->size = (int) filesize($path);
            $media->saveQuietly();

            return true;
        } finally {
            $temporaryDirectory->delete();
        }
    }
}
