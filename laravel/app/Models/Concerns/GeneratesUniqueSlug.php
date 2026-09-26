<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

/**
 * Genera uno slug univoco a partire da una colonna sorgente, con deduplica
 * incrementale (`-2`, `-3`, ...) all'interno di uno scope opzionale.
 *
 * E' una preoccupazione di PERSISTENZA (URL/lookup), non di dominio: la teniamo
 * piccola e riusabile. Uno slug fornito esplicitamente viene sempre rispettato;
 * la generazione avviene solo quando lo slug e vuoto, alla creazione.
 *
 * Strategia di unicita a due livelli (pre-calcolo + retry idempotente):
 *
 *  1. All'evento `creating` pre-calcoliamo uno slug candidato interrogando la
 *     tabella (`slugExists()`), scegliendo il primo suffisso libero. Copre il
 *     caso comune senza sprecare INSERT.
 *  2. Il check-then-insert del punto 1 e' pero' soggetto a RACE: due creazioni
 *     concorrenti dello stesso nome nello stesso scope calcolano lo stesso slug
 *     e la seconda INSERT viola l'indice unique. Per questo avvolgiamo il
 *     salvataggio in un retry che cattura UniqueConstraintViolationException,
 *     rigenera lo slug (suffisso successivo) e ritenta, fino a N tentativi.
 *     Sotto concorrenza il sistema converge deterministicamente su `rex-2`,
 *     `rex-3`, ... senza propagare l'eccezione al chiamante.
 *
 * Il retry copre anche la collisione con record SOFT-DELETED che il check
 * applicativo potrebbe non vedere in una finestra di race: l'indice unique in DB
 * non guarda `deleted_at`, quindi resta l'ultima linea di difesa.
 *
 * Il model che la usa configura:
 * - sluggableSourceColumn(): colonna da cui derivare lo slug (default `name`);
 * - sluggableSlugColumn(): colonna che contiene lo slug (default `slug`);
 * - sluggableScopeColumns(): colonne che delimitano l'unicita (es. `organization_id`
 *   per uno slug univoco per-tenant). Vuoto = unicita globale.
 *
 * @mixin Model
 */
trait GeneratesUniqueSlug
{
    /**
     * Numero massimo di tentativi di INSERT sotto collisione di slug prima di
     * lasciar propagare l'eccezione (evita loop infiniti su vincoli non-slug).
     */
    protected int $slugMaxSaveAttempts = 5;

    public static function bootGeneratesUniqueSlug(): void
    {
        static::creating(function (Model $model): void {
            /** @var Model&self $model */
            $model->generateUniqueSlugIfMissing();
        });
    }

    /**
     * Salvataggio resiliente alle race sullo slug: se l'INSERT viola l'indice
     * unique perche' un'altra scrittura concorrente ha occupato lo slug appena
     * pre-calcolato, rigeneriamo e ritentiamo. Sovrascrive Model::save() solo
     * per i model che generano lo slug automaticamente (slug mancante alla
     * creazione); negli altri casi delega direttamente al parent.
     *
     * @param  array<string, mixed>  $options
     */
    public function save(array $options = []): bool
    {
        if ($this->exists || filled($this->getAttribute($this->sluggableSlugColumn()))) {
            return parent::save($options);
        }

        for ($attempt = 1; ; $attempt++) {
            try {
                return parent::save($options);
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt >= $this->slugMaxSaveAttempts) {
                    throw $exception;
                }

                $this->regenerateSlugAfterCollision();
            }
        }
    }

    /**
     * Rigenera lo slug dopo una collisione: ripartiamo dalla base e lasciamo che
     * uniqueSlug() scelga il prossimo suffisso libero visto lo stato attuale del
     * DB (che ora include il record concorrente che ha causato la violazione).
     */
    protected function regenerateSlugAfterCollision(): void
    {
        $source = (string) $this->getAttribute($this->sluggableSourceColumn());
        $base = Str::slug($source);

        $this->setAttribute($this->sluggableSlugColumn(), $this->uniqueSlug($base));
    }

    protected function sluggableSourceColumn(): string
    {
        return 'name';
    }

    protected function sluggableSlugColumn(): string
    {
        return 'slug';
    }

    /**
     * @return list<string>
     */
    protected function sluggableScopeColumns(): array
    {
        return [];
    }

    protected function generateUniqueSlugIfMissing(): void
    {
        $slugColumn = $this->sluggableSlugColumn();

        $current = $this->getAttribute($slugColumn);

        if (filled($current)) {
            return;
        }

        $source = (string) $this->getAttribute($this->sluggableSourceColumn());
        $base = Str::slug($source);

        $this->setAttribute($slugColumn, $this->uniqueSlug($base));
    }

    protected function uniqueSlug(string $base): string
    {
        $slug = $base;
        $suffix = 2;

        while ($this->slugExists($slug)) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    protected function slugExists(string $slug): bool
    {
        $query = static::query()->where($this->sluggableSlugColumn(), $slug);

        foreach ($this->sluggableScopeColumns() as $column) {
            $query->where($column, $this->getAttribute($column));
        }

        // Se il model usa soft delete, includiamo anche i record cancellati:
        // lo slug e vincolato in DB anche sui soft-deleted (l'indice unique non
        // guarda deleted_at), quindi la deduplica deve tenerne conto. Rimuoviamo
        // il global scope invece di withTrashed() cosi il tipo resta Builder.
        if (in_array(SoftDeletes::class, class_uses_recursive(static::class), true)) {
            $query->withoutGlobalScope(SoftDeletingScope::class);
        }

        return $query->exists();
    }
}
