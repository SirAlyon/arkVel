<?php

declare(strict_types=1);

use App\Support\DesignTokens\ContrastKind;
use App\Support\DesignTokens\ContrastPair;
use App\Support\DesignTokens\ContrastPairs;
use Tests\Support\DesignTokens\TokenSheet;

/*
 | Contrasti dei token colore (direzione "B · Pop affettuoso", piano UI §1-bis).
 | Legge resources/css/app.css: cambiare un colore che rompe una coppia AA fa
 | fallire la suite, in tema chiaro e in tema scuro.
 */

function designTokenSheet(): TokenSheet
{
    static $sheet = null;

    return $sheet ??= TokenSheet::fromFile(dirname(__DIR__, 3).'/resources/css/app.css');
}

/**
 * @return array<string, array{ContrastPair, string}>
 */
function designTokenContrastCases(): array
{
    $cases = [];

    foreach (['light', 'dark'] as $theme) {
        foreach (ContrastPairs::all() as $pair) {
            $cases["{$theme}: {$pair->foreground} su {$pair->background}"] = [$pair, $theme];
        }
    }

    return $cases;
}

it('meets the WCAG minimum for every semantic pair', function (ContrastPair $pair, string $theme): void {
    $ratio = designTokenSheet()->contrast($pair->foreground, $pair->background, $theme);

    expect($ratio)->toBeGreaterThanOrEqual($pair->minimum());
})->with(designTokenContrastCases());

it('keeps the mockup colours exact in the palette', function (string $token, string $hex): void {
    expect(designTokenSheet()->hex($token))->toBe($hex);
})->with([
    'fondo' => ['canvas', '#FFFFFF'],
    'superficie' => ['surface', '#F3F1EA'],
    'bordo decorativo' => ['line', '#E4E1D6'],
    'inchiostro' => ['fg', '#1B1B3A'],
    'hover primario' => ['primary-hover', '#3A3A70'],
    'testo secondario' => ['fg-muted', '#4A4A68'],
    'giallo' => ['highlight', '#FFD23F'],
    'pesca' => ['block-peach', '#FFB4A2'],
    'testo su pesca' => ['peach-800', '#5A2A20'],
    'verde' => ['block-leaf', '#2D6A4F'],
    'menta' => ['block-mint', '#B7E4C7'],
]);

it('derives the dark theme from the values in the plan', function (string $token, string $hex): void {
    expect(designTokenSheet()->hex($token, 'dark'))->toBe($hex);
})->with([
    'fondo' => ['canvas', '#13132B'],
    'testo' => ['fg', '#F7F5EE'],
    'testo secondario' => ['fg-muted', '#B8B6C8'],
    'giallo come accento' => ['primary', '#FFD23F'],
]);

it('never uses yellow as a large dark-mode surface', function (): void {
    $sheet = designTokenSheet();

    foreach (['canvas', 'surface', 'surface-raised', 'block-sun'] as $surface) {
        expect($sheet->resolve($surface, 'dark'))->not->toBe($sheet->resolve('highlight', 'dark'));
    }
});

it('redefines every semantic colour token for the dark theme', function (): void {
    $sheet = designTokenSheet();
    $semantic = array_filter(
        array_keys($sheet->light),
        static fn (string $token): bool => ! preg_match('/^(brand|accent)-\d+$/', $token),
    );

    expect(array_diff($semantic, array_keys($sheet->dark)))->toBe([]);
});

it('flags the known failing pairs of the plan so they stay out of the pair list', function (): void {
    $sheet = designTokenSheet();

    // Giallo su verde 4,43:1 e bordo decorativo 1,31:1: vietati come testo / bordo di controllo.
    expect($sheet->contrast('highlight', 'block-leaf', 'light'))->toBeLessThan(ContrastKind::Text->minimum())
        ->and($sheet->contrast('line', 'canvas', 'light'))->toBeLessThan(ContrastKind::NonText->minimum());

    $listed = array_map(static fn (ContrastPair $pair): string => "{$pair->foreground}/{$pair->background}", ContrastPairs::all());

    expect($listed)->not->toContain('highlight/block-leaf')->not->toContain('line/canvas');
});

it('scopes the focus colour inside every coloured block, as listed in ContrastPairs', function (): void {
    $sheet = designTokenSheet();

    expect($sheet->focusScopes)->toBe(ContrastPairs::focusScopes());

    // Senza override il focus di default sparirebbe su almeno uno di questi fondi.
    expect($sheet->contrast('focus', 'inverse', 'light'))->toBeLessThan(ContrastKind::NonText->minimum());
});
