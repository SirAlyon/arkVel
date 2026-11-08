<?php

declare(strict_types=1);

namespace App\View\Components\Ui;

use App\Support\Ui\FieldControl;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\ViewErrorBag;
use Illuminate\View\Component;

/**
 * <x-ui.field>: label, controllo (nello slot), aiuto ed errore. Vista e documentazione
 * d'uso in resources/views/components/ui/field.blade.php.
 *
 * È un componente a classe (gli altri x-ui sono anonimi) per un solo motivo: le
 * proprietà pubbliche finiscono nei dati del componente, che Blade tiene nello stack
 * mentre renderizza lo slot. Così i controlli figli ritrovano il field più vicino con
 * `@aware(['uiField' => null])`, senza indovinarlo da prop generiche come `label`.
 * `uiField` è null in un field `group`: lì ogni controllo ha id e name suoi.
 */
final class Field extends Component
{
    /** Contesto del campo: id del controllo, name, label e il suo id, obbligo, errore, id di aiuto ed errore. */
    public FieldControl $context;

    /** Il contesto ereditato dai controlli nello slot (null nei gruppi). */
    public ?FieldControl $uiField;

    public function __construct(
        ViewFactory $views,
        public string $label,
        public ?string $name = null,
        public ?string $for = null,
        public ?string $help = null,
        public ?string $error = null,
        public bool $required = false,
        public bool $optional = false,
        public bool $hideLabel = false,
        public bool $group = false,
    ) {
        $errors = $views->shared('errors');

        $this->context = FieldControl::fromField(
            for: $for,
            name: $name,
            label: $label,
            help: $help,
            error: $error,
            required: $required,
            errors: $errors instanceof ViewErrorBag ? $errors : null,
        );
        $this->uiField = $group ? null : $this->context;
    }

    public function render(): View
    {
        return view('components.ui.field');
    }
}
