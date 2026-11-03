<?php

declare(strict_types=1);

use Illuminate\Support\Facades\App;

/*
 | <x-ui.theme-toggle>: tre opzioni (chiaro / scuro / automatico) con nome
 | accessibile tradotto e stato esposto via aria-pressed.
 */

it('renders the three theme options as pressable buttons', function (): void {
    $html = (string) $this->blade('<x-ui.theme-toggle />');

    expect($html)
        ->toContain('role="group"')
        ->toContain('aria-label="Tema"')
        ->toContain('data-theme-option="light"')
        ->toContain('data-theme-option="dark"')
        ->toContain('data-theme-option="system"')
        ->toContain('window.arkvelTheme?.set(mode)')
        ->and(substr_count($html, 'aria-pressed="false"'))->toBe(3)
        ->and(substr_count($html, '<button'))->toBe(3);
});

it('translates the option labels', function (string $locale, array $labels): void {
    App::setLocale($locale);

    $view = $this->blade('<x-ui.theme-toggle />');

    foreach ($labels as $label) {
        $view->assertSee($label);
    }
})->with([
    'it' => ['it', ['Tema', 'Chiaro', 'Scuro', 'Automatico']],
    'en' => ['en', ['Theme', 'Light', 'Dark', 'Automatic']],
]);

it('merges extra classes from the caller', function (): void {
    $this->blade('<x-ui.theme-toggle class="mt-4" />')->assertSee('mt-4', escape: false);
});
