<?php

declare(strict_types=1);

namespace App\Support\DesignTokens;

/**
 * Coppia di token colore (senza il prefisso `--color-`) che deve rispettare la
 * soglia WCAG del suo tipo, sia in tema chiaro sia in tema scuro.
 */
final readonly class ContrastPair
{
    public function __construct(
        public string $foreground,
        public string $background,
        public ContrastKind $kind,
    ) {}

    public function minimum(): float
    {
        return $this->kind->minimum();
    }
}
