<?php

declare(strict_types=1);

namespace App\Support\Seo;

/**
 * Una voce del percorso di navigazione: nome visibile e URL assoluto della pagina.
 * L'ultima voce di un percorso è la pagina corrente.
 */
final readonly class Breadcrumb
{
    public function __construct(
        public string $name,
        public string $url,
    ) {}

    /**
     * @return array{name: string, url: string}
     */
    public function toArray(): array
    {
        return ['name' => $this->name, 'url' => $this->url];
    }
}
