<?php

declare(strict_types=1);

namespace App\Data\Media;

use Spatie\LaravelData\Data;

/**
 * Filtri della rigenerazione delle conversioni (App\Actions\Media\RegenerateImageConversions).
 * Un elenco vuoto vuol dire «nessun filtro».
 */
final class RegenerateConversionsData extends Data
{
    /**
     * @param  list<class-string>  $modelTypes  model proprietari (es. App\Models\Animal)
     * @param  list<string>  $collections  media collection (es. photos, avatar)
     * @param  list<string>  $conversions  conversioni: un nome esatto (`card-webp`, `thumb`)
     *                                     o un nome base che le comprende tutte (`card`)
     * @param  bool  $onlyMissing  solo le conversioni non ancora generate
     * @param  bool  $dryRun  conta senza generare né accodare nulla
     */
    public function __construct(
        public readonly array $modelTypes = [],
        public readonly array $collections = [],
        public readonly array $conversions = [],
        public readonly bool $onlyMissing = false,
        public readonly bool $dryRun = false,
    ) {}
}
