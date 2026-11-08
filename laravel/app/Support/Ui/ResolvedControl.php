<?php

declare(strict_types=1);

namespace App\Support\Ui;

use Illuminate\Support\ViewErrorBag;
use Illuminate\View\ComponentAttributeBag;
use InvalidArgumentException;

/**
 * Id, name, obbligo ed errore di UN controllo di modulo (x-ui.input, x-ui.textarea,
 * x-ui.select, x-ui.combobox, x-ui.date-picker), risolti in un solo punto
 * ({@see resolve()}) dal contesto del field e dagli attributi propri. Ogni controllo lo
 * chiama in una riga e legge solo questo oggetto: le regole non sono ricopiate nelle viste.
 */
final readonly class ResolvedControl
{
    public function __construct(
        public ?string $id,
        public ?string $name,
        public bool $required,
        public ?string $message,
        public bool $invalid,
        /** Id della label del field (null fuori da un field): per aria-labelledby dove `for` non vale. */
        public ?string $labelId,
        private ?string $ownDescribedBy = null,
        private ?string $helpId = null,
        private ?string $errorId = null,
    ) {}

    /**
     * Risolve il controllo.
     *
     * - Dentro un field ($field non null): id, name, obbligo ed errore sono quelli del
     *   field; un id, un name o un `required` propri diversi lanciano un'eccezione
     *   ({@see FieldControl::ensureCompatible()}).
     * - Da solo: id e name propri; errore da $errors con il name o con la chiave di
     *   wire:model ({@see ControlAttributes::errorKey()}).
     * - Id, in ordine: del field > proprio > `<prefix>-<slug del name>` > `<prefix>-<hash>`.
     *   Senza $prefix gli ultimi due passi non ci sono (id null: i controlli nativi non ne
     *   hanno bisogno, il nome accessibile viene da aria-label). Il seme dell'hash è
     *   stabile tra un render e l'altro: chiave di wire:model > $seed > aria-label >
     *   aria-labelledby. MAI dati che cambiano con lo stato (opzioni, valore): con
     *   Livewire cambierebbero l'id del controllo focalizzato e i riferimenti ARIA.
     * - `invalid`: quello forzato dal controllo, altrimenti "c'è un messaggio d'errore".
     *
     * @throws InvalidArgumentException
     */
    public static function resolve(
        ?FieldControl $field,
        ComponentAttributeBag $attributes,
        ?ViewErrorBag $errors,
        ?bool $invalid = null,
        ?string $prefix = null,
        ?string $seed = null,
    ): self {
        $ownId = ControlAttributes::text($attributes->get('id'));
        $ownName = ControlAttributes::text($attributes->get('name'));
        $ownRequired = (bool) $attributes->get('required', false);
        $field?->ensureCompatible($ownId, $ownName, $ownRequired);

        $name = $field !== null ? $field->name : $ownName;
        $message = $field !== null
            ? $field->message
            : ControlAttributes::message(null, ControlAttributes::errorKey($name, $attributes), $errors);

        return new self(
            id: $field->id ?? $ownId ?? self::generatedId($prefix, $name, $attributes, $seed),
            name: $name,
            required: $field !== null ? $field->required : $ownRequired,
            message: $message,
            invalid: $invalid ?? $message !== null,
            labelId: $field?->labelId,
            ownDescribedBy: ControlAttributes::text($attributes->get('aria-describedby')),
            helpId: $field?->helpId,
            errorId: $field?->errorId,
        );
    }

    /**
     * Valore di aria-describedby: l'aria-describedby proprio, poi gli id in più del
     * controllo (prefisso, suffisso, limite di caratteri...), poi aiuto ed errore del field.
     */
    public function describedBy(?string ...$extra): ?string
    {
        return ControlAttributes::describedBy(...[$this->ownDescribedBy, ...array_values($extra), $this->helpId, $this->errorId]);
    }

    private static function generatedId(?string $prefix, ?string $name, ComponentAttributeBag $attributes, ?string $seed): ?string
    {
        if ($prefix === null) {
            return null;
        }

        if ($name !== null) {
            return $prefix.'-'.ControlAttributes::slug($name);
        }

        $stableSeed = ControlAttributes::errorKey(null, $attributes)
            ?? (filled($seed) ? $seed : null)
            ?? ControlAttributes::text($attributes->get('aria-label'))
            ?? ControlAttributes::text($attributes->get('aria-labelledby'))
            ?? '';

        return ControlAttributes::hashedId($prefix, $stableSeed);
    }
}
