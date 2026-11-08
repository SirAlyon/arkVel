<?php

declare(strict_types=1);

namespace App\Support\Ui;

use Illuminate\Support\ViewErrorBag;
use Illuminate\View\ComponentAttributeBag;
use Stringable;

/**
 * Regole pure (senza stato) sugli attributi dei controlli di modulo dei gruppi "forms" e
 * "pickers": id, chiave e messaggio d'errore, aria-describedby.
 *
 * Forma delle classi di App\Support\Ui:
 * - {@see ControlAttributes}: queste regole, funzioni statiche pure;
 * - {@see FieldControl}: value object con il contesto di un x-ui.field (lo costruisce
 *   App\View\Components\Ui\Field e lo ereditano i controlli nello slot);
 * - {@see ResolvedControl}: value object con id, name, obbligo ed errore di UN controllo,
 *   risolti in un solo punto ({@see ResolvedControl::resolve()}) da input, textarea,
 *   select, combobox e date-picker.
 * Checkbox, radio e switch hanno regole d'id loro (valore nell'id, gruppi) e usano
 * direttamente queste funzioni.
 */
final class ControlAttributes
{
    /**
     * Id del controllo di un campo: `for` se c'è, altrimenti `field-<name>` (punti e
     * parentesi diventano trattini), altrimenti `field-` + hash della label.
     */
    public static function fieldId(?string $for, ?string $name, ?string $label): string
    {
        if (filled($for)) {
            return $for;
        }

        return filled($name) ? 'field-'.self::slug($name) : self::hashedId('field', (string) $label);
    }

    /** Slug di un name per gli id: `form.items[0]` diventa `form-items-0`. */
    public static function slug(string $text): string
    {
        return (string) str($text)->replace(['.', '[', ']'], '-')->slug();
    }

    /** Id stabile per un controllo senza name: prefisso + 8 caratteri di hash del seme. */
    public static function hashedId(string $prefix, string $seed): string
    {
        return $prefix.'-'.substr(md5($seed), 0, 8);
    }

    /**
     * Chiave dell'errore di un controllo da solo: il suo name, altrimenti il valore del
     * primo attributo wire:model* (wire:model, wire:model.live...).
     */
    public static function errorKey(?string $name, ComponentAttributeBag $attributes): ?string
    {
        if (filled($name)) {
            return $name;
        }

        return self::text($attributes->whereStartsWith('wire:model')->first());
    }

    /** Messaggio d'errore: quello esplicito se pieno, altrimenti il primo di $errors per la chiave. */
    public static function message(?string $error, ?string $key, ?ViewErrorBag $errors): ?string
    {
        if (filled($error)) {
            return $error;
        }

        $message = $key !== null && $errors !== null ? $errors->getBag('default')->first($key) : '';

        return $message !== '' ? $message : null;
    }

    /** Valore di aria-describedby: gli id pieni separati da spazio, null se non ce n'è nessuno. */
    public static function describedBy(?string ...$ids): ?string
    {
        $joined = implode(' ', array_filter($ids, filled(...)));

        return $joined !== '' ? $joined : null;
    }

    /** Testo da un valore di attributo (stringa, numero o Stringable), null se vuoto o non testuale. */
    public static function text(mixed $value): ?string
    {
        if (is_string($value) || is_int($value) || is_float($value) || $value instanceof Stringable) {
            $text = (string) $value;

            return $text !== '' ? $text : null;
        }

        return null;
    }
}
