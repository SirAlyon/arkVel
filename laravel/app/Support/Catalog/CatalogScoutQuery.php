<?php

declare(strict_types=1);

namespace App\Support\Catalog;

use App\Enums\CatalogSort;
use App\Models\Animal;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Laravel\Scout\Builder;
use Meilisearch\Exceptions\ApiException;

/**
 * Ricerca Scout del catalogo: testo, vincoli dei filtri (`whereIn`), ordinamento.
 *
 * La VISIBILITÀ è imposta a livello Eloquent con lo scope {@see Animal::publiclyVisible()}
 * nella callback `->query()`: vale per Meilisearch (dove l'indice contiene già solo
 * animali pubblici, ma può restare indietro, es. un ente sospeso dopo l'indicizzazione)
 * e per i driver collection/database dei test. Mai animali in bozza, adottati o di enti
 * non verificati. La stessa callback carica foto ed ente (niente N+1 nella griglia).
 *
 * Ordinamento robusto ({@see self::paginate()}): se Meilisearch rifiuta un attributo
 * perché non è tra i `sortableAttributes` dell'indice (impostazioni di config/scout.php non
 * ancora sincronizzate con `php artisan scout:sync-index-settings`), la pagina non va in
 * errore: si ripete la ricerca senza `featured` e, se serve, senza ordinamento (pertinenza
 * del motore), con un warning nel log che indica il comando da lanciare.
 */
final readonly class CatalogScoutQuery
{
    /** Codice d'errore di Meilisearch per un attributo non ordinabile. */
    public const string UNSORTABLE_ERROR = 'invalid_search_sort';

    /**
     * @return Builder<Animal>
     */
    public function make(CatalogFilters $filters, CatalogSort $sort = CatalogSort::Relevance): Builder
    {
        return $this->build($filters, $sort->orders($filters->search));
    }

    /**
     * Una pagina di risultati, degradando l'ordinamento se l'indice non lo supporta.
     *
     * @return LengthAwarePaginator<int, Animal>
     */
    public function paginate(CatalogFilters $filters, CatalogSort $sort, int $perPage, int $page): LengthAwarePaginator
    {
        $attempts = self::degradedOrders($sort->orders($filters->search));

        foreach ($attempts as $index => $orders) {
            try {
                /** @var LengthAwarePaginator<int, Animal> $paginator */
                $paginator = $this->build($filters, $orders)->paginate($perPage, 'page', $page);

                return $paginator;
            } catch (ApiException $exception) {
                if ($exception->errorCode !== self::UNSORTABLE_ERROR || ! array_key_exists($index + 1, $attempts)) {
                    throw $exception;
                }

                Log::warning('Catalog sort not supported by the search index: falling back. Run `php artisan scout:sync-index-settings`.', [
                    'sort' => $sort->value,
                    'orders' => $orders,
                    'fallback' => $attempts[$index + 1],
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        // Irraggiungibile: l'ultimo tentativo restituisce o rilancia.
        throw new \LogicException('No catalog sort attempt left.');
    }

    /**
     * Ordinamenti da provare in sequenza: quello richiesto, lo stesso senza `featured`
     * (l'attributo aggiunto per ultimo agli ordinabili), nessun ordinamento.
     *
     * @param  array<string, 'asc'|'desc'>  $orders
     * @return list<array<string, 'asc'|'desc'>>
     */
    public static function degradedOrders(array $orders): array
    {
        $attempts = [$orders];
        $withoutFeatured = array_diff_key($orders, ['featured' => true]);

        if ($withoutFeatured !== $orders) {
            $attempts[] = $withoutFeatured;
        }

        if (end($attempts) !== []) {
            $attempts[] = [];
        }

        return $attempts;
    }

    /**
     * @param  array<string, 'asc'|'desc'>  $orders
     * @return Builder<Animal>
     */
    private function build(CatalogFilters $filters, array $orders): Builder
    {
        $builder = Animal::search($filters->search)
            ->query(static function (EloquentBuilder $query): EloquentBuilder {
                /** @var EloquentBuilder<Animal> $query */
                return $query->publiclyVisible()->with(['media', 'organization']);
            });

        foreach ($filters->constraints() as $constraint) {
            $builder->whereIn($constraint->field, $constraint->values);
        }

        foreach ($orders as $column => $direction) {
            $builder->orderBy($column, $direction);
        }

        return $builder;
    }
}
