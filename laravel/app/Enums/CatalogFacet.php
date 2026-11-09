<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Faccette del catalogo pubblico (UI-6b). Il valore è il nome del parametro in query
 * string (`?species=dog&region=Lombardia`), stabile per i link della home e della SEO.
 *
 * Non implementa HasLabel di proposito: l'etichetta vive nei testi della pagina
 * (`page_catalog.facets.*`), non in `enums.php`.
 */
enum CatalogFacet: string
{
    case Species = 'species';
    case Sex = 'sex';
    case Size = 'size';
    case Region = 'region';
    case Organization = 'organization';

    public function label(): string
    {
        return __('page_catalog.facets.'.$this->value);
    }

    /**
     * Attributo dell'indice (Meilisearch) e colonna di `animals` su cui si contano i
     * valori. La regione è un attributo copiato dall'ente nell'indice: nel database si
     * ricava dagli enti (vedi DatabaseFacetCounter).
     */
    public function indexField(): string
    {
        return match ($this) {
            self::Species => 'species',
            self::Sex => 'sex',
            self::Size => 'size',
            self::Region => 'region',
            self::Organization => 'organization_id',
        };
    }
}
