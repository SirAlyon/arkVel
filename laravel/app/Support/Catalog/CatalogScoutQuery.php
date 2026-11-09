<?php

declare(strict_types=1);

namespace App\Support\Catalog;

use App\Enums\CatalogFacet;
use App\Enums\CatalogSort;
use App\Models\Animal;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Laravel\Scout\Builder;
use Meilisearch\Exceptions\ApiException;

/**
 * Ricerca Scout del catalogo: testo, vincoli dei filtri (`whereIn`), ordinamento.
 *
 * La VISIBILITÀ è imposta due volte:
 * - nel motore, con il vincolo `organization_id IN [enti verificati]`
 *   ({@see VerifiedOrganizationIds}, lo stesso dei conteggi di {@see MeilisearchFacetCounter}):
 *   un indice rimasto indietro (ente sospeso o cancellato dopo l'indicizzazione, documenti
 *   di animali che non esistono più) non riempie le pagine di risultati che poi spariscono;
 * - a livello Eloquent con lo scope {@see Animal::publiclyVisible()} nella callback
 *   `->query()`: mai animali in bozza, adottati o di enti non verificati. La stessa
 *   callback carica foto ed ente (niente N+1 nella griglia).
 *
 * Il TOTALE non è quello dell'indice: con la callback `->query()` Scout conta sul database
 * gli animali visibili tra tutti quelli trovati dal motore, così «N trovati» non conta mai
 * documenti stantii. {@see self::total()} dà solo quel numero, senza caricare animali (vista
 * «Mappa» del catalogo).
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

    public function __construct(
        private VerifiedOrganizationIds $verifiedOrganizations,
    ) {}

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
        if ($this->matchesNothing($filters)) {
            return new LengthAwarePaginator(new EloquentCollection, 0, $perPage, $page);
        }

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
     * Solo il numero degli animali visibili che corrispondono ai filtri (contato sul
     * database, vedi l'intestazione): nessun animale caricato, nessun ordinamento.
     */
    public function total(CatalogFilters $filters): int
    {
        if ($this->matchesNothing($filters)) {
            return 0;
        }

        return $this->build($filters, [], withRelations: false)->paginateRaw(1, 'page', 1)->total();
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
     * @param  bool  $withRelations  foto ed ente per le card (non per un conteggio)
     * @return Builder<Animal>
     */
    private function build(CatalogFilters $filters, array $orders, bool $withRelations = true): Builder
    {
        $builder = Animal::search($filters->search)
            ->query(static function (EloquentBuilder $query) use ($withRelations): EloquentBuilder {
                /** @var EloquentBuilder<Animal> $query */
                $query->publiclyVisible();

                return $withRelations ? $query->with(['media', 'organization']) : $query;
            });

        foreach ($this->constraints($filters) as $constraint) {
            $builder->whereIn($constraint->field, $constraint->values);
        }

        foreach ($orders as $column => $direction) {
            $builder->orderBy($column, $direction);
        }

        return $builder;
    }

    /**
     * I vincoli dei filtri con quello di visibilità: gli enti scelti (ente o regione)
     * ristretti ai verificati, o tutti i verificati. Un solo vincolo per campo, perché
     * Scout tiene un solo `whereIn` per attributo.
     *
     * @return list<CatalogConstraint>
     */
    private function constraints(CatalogFilters $filters): array
    {
        $field = CatalogFacet::Organization->indexField();
        $organizations = $this->verifiedOrganizations->all();
        $constraints = [];

        foreach ($filters->constraints() as $constraint) {
            if ($constraint->field === $field) {
                $organizations = array_values(array_intersect($constraint->values, $organizations));
            } else {
                $constraints[] = $constraint;
            }
        }

        $constraints[] = new CatalogConstraint($field, $organizations);

        return $constraints;
    }

    private function matchesNothing(CatalogFilters $filters): bool
    {
        foreach ($this->constraints($filters) as $constraint) {
            if ($constraint->matchesNothing()) {
                return true;
            }
        }

        return false;
    }
}
