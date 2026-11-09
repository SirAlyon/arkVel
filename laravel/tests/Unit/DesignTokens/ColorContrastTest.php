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

it('keeps the subtle status backgrounds distinct from the surfaces they sit on', function (string $theme): void {
    // Badge e icone dei toast (bg-*-subtle) stanno su canvas, surface e surface-raised: con
    // lo stesso colore del fondo il badge sparirebbe (es. info-subtle = surface in dark).
    $sheet = designTokenSheet();

    foreach (['success', 'warning', 'danger', 'info'] as $status) {
        foreach (['canvas', 'surface', 'surface-raised'] as $surface) {
            expect($sheet->hex("{$status}-subtle", $theme))->not->toBe($sheet->hex($surface, $theme), "{$status}-subtle su {$surface}");
        }
    }
})->with(['light', 'dark']);

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

/*
 | Fondi che non devono confondersi (ΔE OKLab, TokenSheet::difference()). Il contrasto WCAG
 | non basta: due fondi scuri di luminanza simile hanno contrasto ~1 anche con tinte diverse.
 | 0,10 è circa cinque volte la soglia del percettibile (~0,02).
 */
const DESIGN_TOKEN_MIN_DIFFERENCE = 0.10;

it('keeps the dark peach block apart from the reds of the errors', function (string $red): void {
    // Con peach-700 (#914234) il blocco pesca era quasi identico al rosso cherry-700 (1,04:1,
    // ΔE 0,06) e sembrava un errore. Si confronta con i rossi dei token danger del tema
    // scuro e con cherry-700, il rosso «errore» del tema chiaro che l'occhio associa.
    $sheet = designTokenSheet();

    expect($sheet->difference('block-peach', $red, 'dark'))->toBeGreaterThanOrEqual(DESIGN_TOKEN_MIN_DIFFERENCE);
})->with(['danger', 'danger-hover', 'danger-subtle', 'cherry-700']);

it('keeps the dark mint block apart from the surfaces and from the leaf block', function (string $background): void {
    // Con leaf-900 la menta sulla card surface (navy-800) era 1,12:1, ΔE 0,11: quasi invisibile.
    $sheet = designTokenSheet();

    expect($sheet->difference('block-mint', $background, 'dark'))->toBeGreaterThanOrEqual(DESIGN_TOKEN_MIN_DIFFERENCE)
        ->and($sheet->contrast('block-mint', $background, 'dark'))->toBeGreaterThanOrEqual(1.5);
})->with(['canvas', 'surface', 'block-leaf']);

it('keeps the dark coloured blocks distinct from one another', function (): void {
    $sheet = designTokenSheet();
    $blocks = ['block-sun', 'block-peach', 'block-mint', 'block-leaf'];

    foreach ($blocks as $index => $first) {
        foreach (array_slice($blocks, $index + 1) as $second) {
            expect($sheet->difference($first, $second, 'dark'))->toBeGreaterThanOrEqual(DESIGN_TOKEN_MIN_DIFFERENCE, "{$first} / {$second}");
        }
    }
});

it('pins the dark block colours chosen after the UI-6 review', function (string $token, string $palette): void {
    expect(designTokenSheet()->resolve($token, 'dark'))->toBe(designTokenSheet()->resolve($palette, 'dark'));
})->with([
    'pesca' => ['block-peach', 'peach-800'],
    'testo su pesca' => ['on-block-peach', 'peach-100'],
    'menta' => ['block-mint', 'leaf-800'],
    'testo su menta' => ['on-block-mint', 'leaf-100'],
    'verde' => ['block-leaf', 'leaf-600'],
]);
