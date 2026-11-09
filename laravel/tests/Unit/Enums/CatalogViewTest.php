<?php

declare(strict_types=1);

use App\Enums\CatalogView;

it('falls back to the grid for empty or unknown values', function (string $value): void {
    expect(CatalogView::fromQuery($value))->toBe(CatalogView::List);
})->with(['', 'grid', 'MAP']);

it('reads the map view from the query string, which only the map adds to the URL', function (): void {
    expect(CatalogView::fromQuery('map'))->toBe(CatalogView::Map)
        ->and(CatalogView::Map->queryParameters())->toBe(['view' => 'map'])
        ->and(CatalogView::List->queryParameters())->toBe([]);
});

it('has an icon and a translated label for every view', function (): void {
    expect(CatalogView::List->icon())->toBe('layout-grid')
        ->and(CatalogView::Map->icon())->toBe('map');

    foreach (['it' => ['Elenco', 'Mappa'], 'en' => ['List', 'Map']] as $locale => $labels) {
        app()->setLocale($locale);

        expect(array_map(static fn (CatalogView $view): string => $view->label(), CatalogView::cases()))->toBe($labels);
    }
});
