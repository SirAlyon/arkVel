<?php

declare(strict_types=1);

namespace App\Actions\Organizations;

use App\Data\Organizations\OrganizationDirectoryFilters;
use App\Models\Animal;
use App\Models\Organization;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Indice pubblico degli enti (`/enti`): solo enti `Verified` (nessuna fuga di enti in
 * attesa o sospesi), filtrati per nome (i jolly di LIKE valgono come testo), regione e tipo, in ordine alfabetico, con il
 * conteggio degli animali visibili nel catalogo ({@see Animal::publiclyVisible()}) in
 * `available_animals_count`.
 */
final class ListPublicOrganizations
{
    public const int PER_PAGE = 12;

    /** Carattere di escape della ricerca per nome (non è un carattere speciale per LIKE). */
    private const string LIKE_ESCAPE = '!';

    /**
     * @return LengthAwarePaginator<int, Organization>
     */
    public function handle(OrganizationDirectoryFilters $filters, int $perPage = self::PER_PAGE): LengthAwarePaginator
    {
        return Organization::query()
            ->verified()
            ->when($filters->search, function (Builder $query, string $search): void {
                // LOWER + LIKE con ESCAPE esplicito: insensibile alle maiuscole e con lo
                // stesso comportamento su PostgreSQL e SQLite (whereLike non permette di
                // dichiarare il carattere di escape, e SQLite non ne ha uno predefinito).
                $query->whereRaw("LOWER(name) LIKE ? ESCAPE '".self::LIKE_ESCAPE."'", ['%'.self::escapeLike(mb_strtolower($search)).'%']);
            })
            ->when($filters->region, function (Builder $query, string $region): void {
                $query->where('region', $region);
            })
            ->when($filters->type, function (Builder $query, $type): void {
                $query->where('type', $type);
            })
            ->withCount(['animals as available_animals_count' => function (Builder $query): void {
                /** @var Builder<Animal> $query */
                $query->publiclyVisible();
            }])
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($perPage);
    }

    /**
     * I caratteri jolly di LIKE (`%`, `_`) e l'escape stesso diventano letterali: cercare
     * «100%» non elenca tutti gli enti.
     */
    private static function escapeLike(string $value): string
    {
        return str_replace(
            [self::LIKE_ESCAPE, '%', '_'],
            [self::LIKE_ESCAPE.self::LIKE_ESCAPE, self::LIKE_ESCAPE.'%', self::LIKE_ESCAPE.'_'],
            $value,
        );
    }
}
