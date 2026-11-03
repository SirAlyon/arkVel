<?php

declare(strict_types=1);

namespace Tests\Support\DesignTokens;

use RuntimeException;

/**
 * Legge i token colore di resources/css/app.css e calcola i contrasti WCAG.
 *
 * Formato atteso (quello del file): palette come `--color-<nome>-<step>: oklch(L% C H);`
 * nel blocco `@theme`, token semantici come `--color-<nome>: var(--color-<altro>);`
 * nel blocco `@theme` (tema chiaro) e ridefiniti nel blocco `.dark { … }`; override
 * del focus come `.bg-<fondo> { --color-focus: var(--color-<token>); }`.
 */
final readonly class TokenSheet
{
    /**
     * @param  array<string, array{float, float, float}>  $palette  nome → [L 0-1, C, H]
     * @param  array<string, string>  $light  token semantico → token referenziato
     * @param  array<string, string>  $dark  token semantico → token referenziato
     * @param  array<string, string>  $focusScopes  fondo → token usato per il focus al suo interno
     */
    private function __construct(
        public array $palette,
        public array $light,
        public array $dark,
        public array $focusScopes,
    ) {}

    public static function fromFile(string $path): self
    {
        $css = (string) file_get_contents($path);

        preg_match_all('/--color-([a-z]+-\d+):\s*oklch\(([\d.]+)%\s+([\d.]+)\s+([\d.]+)\)/', $css, $matches, PREG_SET_ORDER);

        $palette = [];

        foreach ($matches as [, $name, $lightness, $chroma, $hue]) {
            $palette[$name] = [(float) $lightness / 100, (float) $chroma, (float) $hue];
        }

        // Tailwind definisce --color-white; il file lo referenzia senza ridefinirlo.
        $palette['white'] ??= [1.0, 0.0, 0.0];

        preg_match_all('/\.bg-([a-z][a-z0-9-]*)\s*\{\s*--color-focus:\s*var\(--color-([a-z0-9-]+)\);\s*\}/', $css, $scopes, PREG_SET_ORDER);

        $focusScopes = [];

        foreach ($scopes as [, $background, $token]) {
            $focusScopes[$background] = $token;
        }

        return new self(
            $palette,
            self::references(self::block($css, '@theme static {')),
            self::references(self::block($css, '.dark {')),
            $focusScopes,
        );
    }

    /**
     * @return array{float, float, float}
     */
    public function resolve(string $token, string $theme): array
    {
        $map = $theme === 'dark' ? [...$this->light, ...$this->dark] : $this->light;
        $seen = [];

        while (isset($map[$token])) {
            if (isset($seen[$token])) {
                throw new RuntimeException("Riferimento circolare sul token {$token}.");
            }

            $seen[$token] = true;
            $token = $map[$token];
        }

        return $this->palette[$token] ?? throw new RuntimeException("Token colore sconosciuto: {$token} ({$theme}).");
    }

    public function contrast(string $foreground, string $background, string $theme): float
    {
        $first = self::luminance($this->resolve($foreground, $theme));
        $second = self::luminance($this->resolve($background, $theme));

        return (max($first, $second) + 0.05) / (min($first, $second) + 0.05);
    }

    public function hex(string $token, string $theme = 'light'): string
    {
        return '#'.implode('', array_map(
            static fn (float $channel): string => sprintf('%02X', (int) round(self::encode($channel) * 255)),
            self::linearRgb($this->resolve($token, $theme)),
        ));
    }

    /**
     * @param  array{float, float, float}  $oklch
     */
    private static function luminance(array $oklch): float
    {
        [$red, $green, $blue] = self::linearRgb($oklch);

        return 0.2126 * $red + 0.7152 * $green + 0.0722 * $blue;
    }

    /**
     * OKLCH → sRGB lineare (matrici di Björn Ottosson), con clamp nel gamut.
     *
     * @param  array{float, float, float}  $oklch
     * @return array{float, float, float}
     */
    private static function linearRgb(array $oklch): array
    {
        [$lightness, $chroma, $hue] = $oklch;
        $a = $chroma * cos(deg2rad($hue));
        $b = $chroma * sin(deg2rad($hue));

        $l = ($lightness + 0.3963377774 * $a + 0.2158037573 * $b) ** 3;
        $m = ($lightness - 0.1055613458 * $a - 0.0638541728 * $b) ** 3;
        $s = ($lightness - 0.0894841775 * $a - 1.2914855480 * $b) ** 3;

        $clamp = static fn (float $value): float => min(1.0, max(0.0, $value));

        return [
            $clamp(4.0767416621 * $l - 3.3077115913 * $m + 0.2309699292 * $s),
            $clamp(-1.2684380046 * $l + 2.6097574011 * $m - 0.3413193965 * $s),
            $clamp(-0.0041960863 * $l - 0.7034186147 * $m + 1.7076147010 * $s),
        ];
    }

    private static function encode(float $linear): float
    {
        return $linear <= 0.0031308 ? 12.92 * $linear : 1.055 * $linear ** (1 / 2.4) - 0.055;
    }

    private static function block(string $css, string $opening): string
    {
        $start = strpos($css, $opening);

        if ($start === false) {
            throw new RuntimeException("Blocco «{$opening}» non trovato in app.css.");
        }

        $depth = 0;
        $length = strlen($css);

        for ($i = $start + strlen($opening) - 1; $i < $length; $i++) {
            $depth += match ($css[$i]) {
                '{' => 1,
                '}' => -1,
                default => 0,
            };

            if ($depth === 0) {
                return substr($css, $start, $i - $start + 1);
            }
        }

        throw new RuntimeException("Blocco «{$opening}» non chiuso in app.css.");
    }

    /**
     * @return array<string, string>
     */
    private static function references(string $block): array
    {
        preg_match_all('/--color-([a-z][a-z0-9-]*):\s*var\(--color-([a-z0-9-]+)\)/', $block, $matches, PREG_SET_ORDER);

        $references = [];

        foreach ($matches as [, $token, $target]) {
            $references[$token] = $target;
        }

        return $references;
    }
}
