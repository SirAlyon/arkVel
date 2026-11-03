<?php

declare(strict_types=1);

use App\Enums\AppLocale;

it('exposes the supported locale codes, Italian first', function () {
    expect(AppLocale::codes())->toBe(['it', 'en']);
});

it('labels each locale with its endonym', function (AppLocale $locale, string $label) {
    expect($locale->getLabel())->toBe($label);
})->with([
    [AppLocale::Italian, 'Italiano'],
    [AppLocale::English, 'English'],
]);

it('has a translation directory for every supported locale', function (AppLocale $locale) {
    expect(lang_path($locale->value.'/public.php'))->toBeFile();
})->with(AppLocale::cases());
