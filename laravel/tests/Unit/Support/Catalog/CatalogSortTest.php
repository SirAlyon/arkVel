<?php

declare(strict_types=1);

use App\Enums\CatalogSort;

it('falls back to the recommended order for empty or unknown values', function (string $value): void {
    expect(CatalogSort::fromQuery($value))->toBe(CatalogSort::Relevance);
})->with(['', 'price', 'RELEVANCE']);

it('reads the known values from the query string', function (): void {
    expect(CatalogSort::fromQuery('newest'))->toBe(CatalogSort::Newest)
        ->and(CatalogSort::fromQuery('waiting'))->toBe(CatalogSort::LongestWaiting)
        ->and(CatalogSort::fromQuery('name'))->toBe(CatalogSort::Name);
});

it('leaves a text search to the relevance of the engine', function (): void {
    expect(CatalogSort::Relevance->orders('rex'))->toBe([])
        ->and(CatalogSort::Relevance->orders('  '))->toBe(['featured' => 'desc', 'created_at' => 'desc']);
});

it('orders by date or name', function (): void {
    expect(CatalogSort::Newest->orders('rex'))->toBe(['created_at' => 'desc'])
        ->and(CatalogSort::LongestWaiting->orders(''))->toBe(['created_at' => 'asc'])
        ->and(CatalogSort::Name->orders(''))->toBe(['name' => 'asc']);
});

it('has a translated label for every order', function (): void {
    foreach (['it', 'en'] as $locale) {
        app()->setLocale($locale);

        foreach (CatalogSort::cases() as $sort) {
            expect($sort->label())->not->toStartWith('page_catalog.');
        }
    }

    app()->setLocale('en');

    expect(CatalogSort::LongestWaiting->label())->toBe('Waiting the longest');
});
