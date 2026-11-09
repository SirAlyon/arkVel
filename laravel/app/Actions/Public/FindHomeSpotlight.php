<?php

declare(strict_types=1);

namespace App\Actions\Public;

use App\Models\Animal;
use Illuminate\Database\Eloquent\Builder;

/**
 * L'animale del blocco foto della hero: il più recente tra quelli pubblicamente visibili
 * ({@see Animal::publiclyVisible()}) che hanno una foto (galleria o avatar), preferendo
 * quelli in evidenza. Null se nessun animale visibile ha una foto: la hero mostra allora
 * il blocco colorato senza foto.
 *
 * Carica `media` e `organization`, così la vista non fa altre query.
 */
final readonly class FindHomeSpotlight
{
    /** Collezioni medialibrary da cui può arrivare la foto (vedi Animal::registerMediaCollections). */
    public const array PHOTO_COLLECTIONS = ['photos', 'avatar'];

    public function handle(): ?Animal
    {
        return Animal::query()
            ->publiclyVisible()
            ->whereHas('media', static function (Builder $media): void {
                $media->whereIn('collection_name', self::PHOTO_COLLECTIONS);
            })
            ->with(['media', 'organization'])
            ->orderByDesc('featured')
            ->latest()
            ->orderByDesc('id')
            ->first();
    }
}
