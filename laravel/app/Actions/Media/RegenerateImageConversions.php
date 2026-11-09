<?php

declare(strict_types=1);

namespace App\Actions\Media;

use App\Data\Media\RegenerateConversionsData;
use App\Data\Media\RegenerationSummary;
use Illuminate\Database\Eloquent\Builder;
use Spatie\MediaLibrary\Conversions\Conversion;
use Spatie\MediaLibrary\Conversions\ConversionCollection;
use Spatie\MediaLibrary\Conversions\FileManipulator;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

/**
 * Rigenera le conversioni dei media esistenti, per esempio dopo aver aggiunto `card`,
 * `hero`, `og` e `zoom` (UI-4) o dopo aver abilitato AVIF/WebP nel driver immagine.
 *
 * Usa lo stesso motore di `media-library:regenerate` (FileManipulator::createDerivedFiles:
 * le conversioni in coda restano in coda, le altre girano subito, le responsive images
 * seguono la loro conversione) ma aggiunge ciò che il comando di medialibrary non ha:
 * il filtro per collection, i nomi base (`card` = `card`, `card-webp`, `card-avif`) e un
 * `onlyMissing` che legge `generated_conversions` e salta del tutto i media già completi,
 * invece di accodare un job per ciascuno. Un errore su un media non ferma gli altri.
 */
final class RegenerateImageConversions
{
    public function __construct(private readonly FileManipulator $fileManipulator) {}

    public function handle(RegenerateConversionsData $data): RegenerationSummary
    {
        $media = 0;
        $conversions = 0;
        $failures = [];

        foreach ($this->query($data)->lazyById(100) as $item) {
            $names = $this->conversionNames($item, $data);

            if ($names === []) {
                continue;
            }

            $media++;
            $conversions += count($names);

            if ($data->dryRun) {
                continue;
            }

            try {
                $this->fileManipulator->createDerivedFiles($item, $names);
            } catch (Throwable $exception) {
                $failures[(int) $item->getKey()] = $exception->getMessage();
            }
        }

        return new RegenerationSummary($media, $conversions, $failures);
    }

    /**
     * @return Builder<Media>
     */
    private function query(RegenerateConversionsData $data): Builder
    {
        return Media::query()
            ->when($data->modelTypes !== [], fn (Builder $query): Builder => $query->whereIn('model_type', $data->modelTypes))
            ->when($data->collections !== [], fn (Builder $query): Builder => $query->whereIn('collection_name', $data->collections));
    }

    /**
     * Le conversioni registrate per il model e la collection del media che rispondono ai
     * filtri.
     *
     * @return list<string>
     */
    private function conversionNames(Media $media, RegenerateConversionsData $data): array
    {
        return ConversionCollection::createForMedia($media)
            ->getConversions($media->collection_name)
            ->map(static fn (Conversion $conversion): string => $conversion->getName())
            ->filter(fn (string $name): bool => $this->matches($name, $data->conversions))
            ->reject(fn (string $name): bool => $data->onlyMissing && $media->hasGeneratedConversion($name))
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $requested
     */
    private function matches(string $name, array $requested): bool
    {
        if ($requested === []) {
            return true;
        }

        foreach ($requested as $wanted) {
            if ($name === $wanted || str_starts_with($name, $wanted.'-')) {
                return true;
            }
        }

        return false;
    }
}
