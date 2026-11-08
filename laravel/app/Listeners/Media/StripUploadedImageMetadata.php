<?php

declare(strict_types=1);

namespace App\Listeners\Media;

use App\Actions\Media\StripImageMetadata;
use App\Support\Media\CleansImageMetadata;
use Psr\Log\LoggerInterface;
use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAddedEvent;
use Throwable;

/**
 * Privacy (piano UI §4.6, GDPR by design): appena una foto è caricata, ne ripulisce
 * l'originale da EXIF/GPS e dagli altri metadati (App\Actions\Media\StripImageMetadata).
 * Una foto scattata a casa di un affidatario porterebbe altrimenti la sua posizione.
 *
 * Solo per i model che lo chiedono (App\Support\Media\CleansImageMetadata, es. Animal:
 * photos e avatar). Vale per ogni via di caricamento (Filament, seeder, API) perché
 * ascolta medialibrary, non i form.
 *
 * SINCRONO, di proposito: medialibrary emette MediaHasBeenAddedEvent subito dopo aver
 * copiato l'originale e PRIMA di createDerivedFiles(), quindi la thumb sincrona e i job
 * delle conversioni in coda leggono già il file ripulito, e non c'è un intervallo in cui
 * l'originale con il GPS resta sul disco. In coda correrebbe in gara con le conversioni.
 * Costo: una decodifica + codifica GD nella richiesta di upload, che già decodifica la
 * foto per la thumb, e solo se il file ha metadati: misurati 0,4-0,6 s per ripulitura +
 * thumb su una foto da 12 MP (4000×3000) nel container.
 *
 * Fallisce in modo sicuro: un errore si registra nel log e non blocca il caricamento (il
 * sito pubblico non serve mai l'originale: App\Support\Media\MediaPictures).
 *
 * Registrato dall'event discovery di Laravel (app/Listeners), come gli altri listener.
 */
final readonly class StripUploadedImageMetadata
{
    public function __construct(
        private StripImageMetadata $strip,
        private LoggerInterface $logger,
    ) {}

    public function handle(MediaHasBeenAddedEvent $event): void
    {
        $media = $event->media;

        try {
            $model = $media->model;

            if (! $model instanceof CleansImageMetadata || ! in_array($media->collection_name, $model->imageMetadataCollections(), true)) {
                return;
            }

            $this->strip->handle($media);
        } catch (Throwable $exception) {
            $this->logger->warning('Could not strip the metadata of an uploaded image.', [
                'media_id' => $media->getKey(),
                'exception' => $exception,
            ]);
        }
    }
}
