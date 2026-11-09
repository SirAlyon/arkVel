<?php

declare(strict_types=1);

namespace App\Support\Ui;

/**
 * Id HTML univoci nella pagina per i componenti x-ui che li ricavano da un testo (es.
 * x-ui.map dalla sua etichetta): due componenti con lo stesso testo ricevono `base` e
 * `base-2`, così aria-describedby e aria-labelledby non puntano all'elemento sbagliato.
 *
 * L'istanza è `scoped` (una per richiesta, App\Providers\AppServiceProvider): stesso ordine
 * di rendering, stessi id, anche a ogni richiesta di Livewire che rifà il componente.
 */
final class ElementIds
{
    /** @var array<string, int> */
    private array $used = [];

    public function unique(string $base): string
    {
        $count = $this->used[$base] = ($this->used[$base] ?? 0) + 1;

        return $count === 1 ? $base : $base.'-'.$count;
    }
}
