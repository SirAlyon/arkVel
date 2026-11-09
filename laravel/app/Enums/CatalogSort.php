<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Ordinamenti del catalogo pubblico (`?sort=`). `Relevance` è il predefinito e non
 * compare nell'URL: con una ricerca testuale vale la pertinenza del motore, senza
 * ricerca prima gli animali in evidenza e poi i più recenti.
 *
 * Gli attributi usati (`featured`, `created_at`, `name`) devono essere tra i
 * `sortableAttributes` dell'indice in config/scout.php.
 */
enum CatalogSort: string
{
    case Relevance = 'relevance';
    case Newest = 'newest';
    case LongestWaiting = 'waiting';
    case Name = 'name';

    /**
     * Valore della query string; vuoto o sconosciuto vale il predefinito.
     */
    public static function fromQuery(string $value): self
    {
        return self::tryFrom($value) ?? self::Relevance;
    }

    public function label(): string
    {
        return __('page_catalog.sort.'.$this->value);
    }

    /**
     * Ordinamento per il motore di ricerca: colonna => direzione, nell'ordine.
     *
     * @return array<string, 'asc'|'desc'>
     */
    public function orders(string $search): array
    {
        return match ($this) {
            self::Relevance => trim($search) === '' ? ['featured' => 'desc', 'created_at' => 'desc'] : [],
            self::Newest => ['created_at' => 'desc'],
            self::LongestWaiting => ['created_at' => 'asc'],
            self::Name => ['name' => 'asc'],
        };
    }
}
