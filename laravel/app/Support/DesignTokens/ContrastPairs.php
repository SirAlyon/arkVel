<?php

declare(strict_types=1);

namespace App\Support\DesignTokens;

/**
 * Le coppie di token semantici che i componenti del sito pubblico possono
 * accostare (resources/css/app.css). Unica fonte per il test dei contrasti
 * (tests/Unit/DesignTokens/ColorContrastTest.php) e per la styleguide.
 *
 * Testo >= 4.5:1 (WCAG 1.4.3); bordi dei controlli e focus >= 3:1 (WCAG 1.4.11).
 * Il bordo `line` non compare: è solo decorativo (1,31:1 sul bianco).
 *
 * Il focus sui blocchi colorati usa il colore "on" del blocco (vedi focusScopes()):
 * l'anello, con outline-offset, è disegnato sul fondo del contenitore.
 */
final class ContrastPairs
{
    /**
     * @return list<ContrastPair>
     */
    public static function all(): array
    {
        return [...self::text(), ...self::nonText()];
    }

    /**
     * @return list<ContrastPair>
     */
    public static function text(): array
    {
        return array_map(
            static fn (array $pair): ContrastPair => new ContrastPair($pair[0], $pair[1], ContrastKind::Text),
            [
                ['fg', 'canvas'],
                ['fg', 'surface'],
                ['fg', 'surface-raised'],
                ['fg-muted', 'canvas'],
                ['fg-muted', 'surface'],
                ['fg-muted', 'surface-raised'],
                ['link', 'canvas'],
                ['link', 'surface'],
                ['link-hover', 'canvas'],
                ['on-primary', 'primary'],
                ['on-primary', 'primary-hover'],
                ['on-highlight', 'highlight'],
                ['on-highlight', 'highlight-hover'],
                ['on-stamp', 'stamp'],
                ['on-inverse', 'inverse'],
                ['on-block-sun', 'block-sun'],
                ['on-block-peach', 'block-peach'],
                ['on-block-mint', 'block-mint'],
                ['on-block-leaf', 'block-leaf'],
                ['success', 'canvas'],
                ['on-success-subtle', 'success-subtle'],
                ['warning', 'canvas'],
                ['on-warning-subtle', 'warning-subtle'],
                ['danger', 'canvas'],
                ['danger', 'surface'],
                ['on-danger', 'danger'],
                ['on-danger-subtle', 'danger-subtle'],
                ['info', 'canvas'],
                ['on-info-subtle', 'info-subtle'],
            ],
        );
    }

    /**
     * Fondi di contenitore che ridefiniscono `--color-focus` per i figli
     * (app.css, `.bg-<fondo> { --color-focus: var(--color-<token>) }`).
     *
     * @return array<string, string> fondo → token usato per il focus
     */
    public static function focusScopes(): array
    {
        return [
            'inverse' => 'on-inverse',
            'stamp' => 'on-stamp',
            'block-sun' => 'on-block-sun',
            'block-peach' => 'on-block-peach',
            'block-mint' => 'on-block-mint',
            'block-leaf' => 'on-block-leaf',
        ];
    }

    /**
     * @return list<ContrastPair>
     */
    public static function nonText(): array
    {
        return array_map(
            static fn (array $pair): ContrastPair => new ContrastPair($pair[0], $pair[1], ContrastKind::NonText),
            [
                ['line-strong', 'canvas'],
                ['line-strong', 'surface'],
                ['line-strong', 'surface-raised'],
                ['focus', 'canvas'],
                ['focus', 'surface'],
                ['focus', 'surface-raised'],
                ...array_map(
                    static fn (string $background, string $focus): array => [$focus, $background],
                    array_keys(self::focusScopes()),
                    self::focusScopes(),
                ),
            ],
        );
    }
}
