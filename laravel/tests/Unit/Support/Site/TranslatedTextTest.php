<?php

declare(strict_types=1);

use App\Models\Animal;
use App\Models\User;
use App\Support\Site\TranslatedText;

it('picks the current locale first', function (): void {
    $animal = new Animal(['description' => ['it' => 'Dolcissimo', 'en' => 'Very sweet']]);

    $text = TranslatedText::pick($animal, 'description', 'it', 'en');

    expect($text?->text)->toBe('Dolcissimo')
        ->and($text?->locale)->toBe('it')
        ->and($text?->differsFrom('it'))->toBeFalse();
});

it('falls back to the fallback locale, then to any translation, skipping blank ones', function (): void {
    $toFallback = TranslatedText::pick(new Animal(['description' => ['it' => '  ', 'en' => 'Very sweet', 'de' => 'Sehr süß']]), 'description', 'it', 'en');
    $toAny = TranslatedText::pick(new Animal(['description' => ['de' => ' Sehr süß ']]), 'description', 'it', 'en');

    expect($toFallback?->text)->toBe('Very sweet')
        ->and($toFallback?->differsFrom('it'))->toBeTrue()
        ->and($toAny?->text)->toBe('Sehr süß')
        ->and($toAny?->locale)->toBe('de');
});

it('uses the app fallback locale by default', function (): void {
    config(['app.fallback_locale' => 'en']);

    expect(TranslatedText::pick(new Animal(['description' => ['de' => 'Sehr süß', 'en' => 'Very sweet']]), 'description', 'it')?->locale)->toBe('en');
});

it('returns nothing without a translation or on a model that is not translatable', function (): void {
    expect(TranslatedText::pick(new Animal, 'description', 'it'))->toBeNull()
        ->and(TranslatedText::pick(new User(['name' => 'Giulia']), 'name', 'it'))->toBeNull();
});
