<?php

declare(strict_types=1);

namespace App\Support\Catalog;

use App\Models\Organization;

/**
 * Gli id degli enti verificati, cioè gli unici i cui animali possono comparire nel
 * catalogo pubblico: il vincolo di visibilità che {@see MeilisearchFacetCounter} aggiunge
 * a ogni richiesta, perché l'indice può restare indietro (un ente sospeso dopo
 * l'indicizzazione dei suoi animali).
 *
 * Una sola query per richiesta: l'istanza è `scoped` (App\Providers\CatalogServiceProvider)
 * e ricorda il risultato. Il punto unico rende facile metterlo in cache in futuro.
 */
final class VerifiedOrganizationIds
{
    /** @var list<int>|null */
    private ?array $ids = null;

    /**
     * @return list<int> in ordine crescente
     */
    public function all(): array
    {
        return $this->ids ??= array_values(array_map(
            intval(...),
            Organization::query()->verified()->orderBy('id')->pluck('id')->all(),
        ));
    }
}
