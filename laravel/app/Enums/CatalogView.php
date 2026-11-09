<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Viste del catalogo pubblico (`?view=`): la griglia degli animali (predefinita, non
 * compare nell'URL) o la mappa degli enti con il suo elenco alternativo (UI-7).
 *
 * Non implementa HasLabel: le etichette sono testi della pagina (`page_catalog.view.*`).
 */
enum CatalogView: string
{
    case List = 'list';
    case Map = 'map';

    /**
     * Valore della query string; vuoto o sconosciuto vale la griglia.
     */
    public static function fromQuery(string $value): self
    {
        return self::tryFrom($value) ?? self::List;
    }

    public function label(): string
    {
        return __('page_catalog.view.'.$this->value);
    }

    /** Icona Lucide del selettore della vista. */
    public function icon(): string
    {
        return match ($this) {
            self::List => 'layout-grid',
            self::Map => 'map',
        };
    }

    /**
     * Parametri della query string per questa vista (nessuno per la predefinita).
     *
     * @return array<string, string>
     */
    public function queryParameters(): array
    {
        return $this === self::List ? [] : ['view' => $this->value];
    }
}
