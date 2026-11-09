<?php

declare(strict_types=1);

use App\Models\Animal;
use App\Support\Site\AnimalAge;
use Illuminate\Support\Carbon;

beforeEach(fn () => app()->setLocale('it'));

it('reads the estimated age in months below two years and in whole years from then on', function (?int $months, ?string $expected): void {
    expect(AnimalAge::label($months, null))->toBe($expected);
})->with([
    'unknown' => [null, null],
    'newborn' => [0, '1 mese'],
    'one month' => [1, '1 mese'],
    'eight months' => [8, '8 mesi'],
    'just under two years' => [23, '23 mesi'],
    'two years' => [24, '2 anni'],
    'three and a half years' => [42, '3 anni'],
    'negative' => [-3, null],
]);

it('derives the age from the birthdate when there is no estimate', function (): void {
    $today = Carbon::parse('2026-11-08');

    expect(AnimalAge::label(null, Carbon::parse('2026-03-01'), $today))->toBe('8 mesi')
        ->and(AnimalAge::label(null, Carbon::parse('2021-11-08'), $today))->toBe('5 anni')
        ->and(AnimalAge::label(null, Carbon::parse('2027-01-01'), $today))->toBeNull()
        ->and(AnimalAge::label(12, Carbon::parse('2010-01-01'), $today))->toBe('12 mesi');
});

it('reads the age of an animal and translates it', function (): void {
    $animal = new Animal(['estimated_age_months' => 1]);

    expect(AnimalAge::of($animal))->toBe('1 mese');

    app()->setLocale('en');

    expect(AnimalAge::of(new Animal(['estimated_age_months' => 36])))->toBe('3 years')
        ->and(AnimalAge::of(new Animal(['estimated_age_months' => 1])))->toBe('1 month');
});
