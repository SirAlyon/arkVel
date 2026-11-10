<?php

declare(strict_types=1);

use App\Support\Site\LegalDocument;
use Carbon\CarbonInterface;

it('keeps the version string as given', function (): void {
    expect(LegalDocument::fromVersion('2026-11')->version)->toBe('2026-11');
});

it('derives the first day of the month from a YYYY-MM version', function (): void {
    $updatedOn = LegalDocument::fromVersion('2026-11')->updatedOn();

    expect($updatedOn)->toBeInstanceOf(CarbonInterface::class)
        ->and($updatedOn?->format('Y-m-d'))->toBe('2026-11-01');
});

it('has no date when the version is not a year-month', function (string $version): void {
    expect(LegalDocument::fromVersion($version)->updatedOn())->toBeNull();
})->with([
    'free text' => ['v2'],
    'full date' => ['2026-11-05'],
    'month out of range' => ['2026-13'],
    'zero month' => ['2026-00'],
    'empty' => [''],
]);
