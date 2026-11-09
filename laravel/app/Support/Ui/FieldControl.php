<?php

declare(strict_types=1);

namespace App\Support\Ui;

use Illuminate\Support\ViewErrorBag;
use InvalidArgumentException;

/**
 * Contesto di un x-ui.field: id del controllo, name, label (e il suo id), obbligo,
 * messaggio d'errore e id di aiuto ed errore (per aria-describedby).
 *
 * x-ui.field (App\View\Components\Ui\Field) lo costruisce dalle sue prop
 * ({@see fromField()}) e lo espone ai controlli nello slot come `uiField`, che lo
 * ritrovano con @aware e lo passano a {@see ResolvedControl::resolve()}: label,
 * descrizioni e controllo puntano sempre agli stessi id. Le regole su id ed errore sono
 * in {@see ControlAttributes}.
 */
final readonly class FieldControl
{
    public function __construct(
        public string $id,
        public ?string $name,
        public string $label,
        public string $labelId,
        public bool $required,
        public ?string $message,
        public ?string $helpId,
        public ?string $errorId,
    ) {}

    /**
     * Contesto di un campo dalle sue prop: id da `for`, poi da `name`, poi dalla label;
     * errore esplicito o letto da $errors con il name.
     */
    public static function fromField(
        ?string $for,
        ?string $name,
        string $label,
        ?string $help,
        ?string $error,
        bool $required,
        ?ViewErrorBag $errors,
    ): self {
        $id = ControlAttributes::fieldId($for, $name, $label);
        $message = ControlAttributes::message($error, $name, $errors);

        return new self(
            id: $id,
            name: $name,
            label: $label,
            labelId: $id.'-label',
            required: $required,
            message: $message,
            helpId: filled($help) ? $id.'-help' : null,
            errorId: $message !== null ? $id.'-error' : null,
        );
    }

    /**
     * Valore di `for` della label, dato l'HTML già renderizzato del controllo (Blade
     * renderizza lo slot prima della vista del field). `for` vale solo per gli elementi
     * etichettabili (input, select, textarea...): un controllo che non lo è (il gruppo
     * del date-picker inline) si collega alla label con aria-labelledby="<labelId>", e in
     * quel caso la label non ha `for` (sarebbe HTML non valido).
     */
    public function labelFor(string $controlHtml): ?string
    {
        return str_contains($controlHtml, 'aria-labelledby="'.$this->labelId.'"') ? null : $this->id;
    }

    /**
     * Un controllo dentro un field usa SOLO id, name e obbligo del field: un id o un name
     * propri diversi staccherebbero label e descrizioni, un `required` proprio non
     * avrebbe l'indicatore testuale. Meglio un errore chiaro al primo render che un
     * campo inaccessibile in silenzio.
     *
     * @throws InvalidArgumentException
     */
    public function ensureCompatible(?string $id, ?string $name, bool $required): void
    {
        if ($id !== null && $id !== $this->id) {
            throw new InvalidArgumentException("x-ui.field: the control has its own id \"{$id}\" but the field expects \"{$this->id}\". Set the id with `for` on x-ui.field.");
        }

        if ($name !== null && $name !== $this->name) {
            throw new InvalidArgumentException("x-ui.field: the control has its own name \"{$name}\". Set `name` on x-ui.field, the control inherits it.");
        }

        if ($required && ! $this->required) {
            throw new InvalidArgumentException('x-ui.field: the control is required but the field is not. Set `required` on x-ui.field.');
        }
    }
}
