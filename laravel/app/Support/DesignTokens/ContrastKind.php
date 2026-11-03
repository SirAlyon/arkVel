<?php

declare(strict_types=1);

namespace App\Support\DesignTokens;

/**
 * Tipo di coppia colore e relativa soglia WCAG 2.x (livello AA).
 */
enum ContrastKind: string
{
    /** Testo normale (WCAG 1.4.3). */
    case Text = 'text';

    /** Bordi dei controlli, indicatori di focus e altri elementi grafici (WCAG 1.4.11). */
    case NonText = 'non_text';

    public function minimum(): float
    {
        return match ($this) {
            self::Text => 4.5,
            self::NonText => 3.0,
        };
    }
}
