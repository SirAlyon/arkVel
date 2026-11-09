<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

/*
 | x-ui.error-summary (gruppo "forms"): riepilogo degli errori in cima a un modulo, con i
 | link ai campi (id `field-<chiave>` di x-ui.field) e il focus quando compare.
 */

it('renders nothing without errors', function (): void {
    expect(trim(Blade::render('<x-ui.error-summary :errors="$errors" :fields="[\'email\']" />', ['errors' => new MessageBag])))->toBe('');
});

it('lists the field errors in form order with links, then the form errors', function (): void {
    $bag = new MessageBag([
        'form' => ['Errore generale'],
        'password' => ['Password mancante'],
        'email' => ['Email non valida', 'Secondo messaggio'],
    ]);

    $html = Blade::render('<x-ui.error-summary :errors="$errors" :fields="[\'email\', \'password\']" />', ['errors' => $bag]);

    expect($html)
        ->toContain('id="error-summary"')
        ->toContain('tabindex="-1"')
        ->toContain('aria-labelledby="error-summary-title"')
        ->toContain('x-init="$nextTick(() => $el.focus())"')
        ->toContain('Ci sono 3 problemi da correggere')
        ->toContain('href="#field-email"')
        ->toContain('href="#field-password"')
        ->not->toContain('Secondo messaggio')
        ->and(strpos($html, 'Email non valida'))->toBeLessThan(strpos($html, 'Password mancante'))
        ->and(strpos($html, 'Password mancante'))->toBeLessThan(strpos($html, 'Errore generale'))
        ->and($html)->not->toContain('href="#field-form"');
});

it('reads the default bag of a ViewErrorBag and uses the singular title', function (): void {
    $errors = (new ViewErrorBag)->put('default', new MessageBag(['email' => ['Email non valida']]));

    expect(Blade::render('<x-ui.error-summary :errors="$errors" :fields="[\'email\']" />', ['errors' => $errors]))
        ->toContain('C’è un problema da correggere')
        ->toContain('<h2 id="error-summary-title"');
});

it('accepts explicit messages, a custom id, title and heading level', function (): void {
    $html = Blade::render('<x-ui.error-summary id="demo-errors" :heading-level="3" title="Controlla" :messages="[\'apply-email\' => \'Email\', \'\' => \'\']" />');

    expect($html)
        ->toContain('id="demo-errors"')
        ->toContain('<h3 id="demo-errors-title"')
        ->toContain('Controlla')
        ->toContain('href="#apply-email"')
        ->and(substr_count($html, '<li>'))->toBe(1);
});
