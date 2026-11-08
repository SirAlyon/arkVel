<?php

declare(strict_types=1);

namespace App\Support\Site;

/**
 * Voce della navigazione principale del sito pubblico (header, menu mobile, footer).
 *
 * `current` è il valore di aria-current: `page` sulla pagina della voce, `true` dentro la
 * sua sezione (es. la scheda di un animale per "Catalogo"), null altrove.
 */
final readonly class NavItem
{
    public function __construct(
        public string $key,
        public string $url,
        public string $label,
        public ?string $current = null,
    ) {}

    public function isActive(): bool
    {
        return $this->current !== null;
    }
}
