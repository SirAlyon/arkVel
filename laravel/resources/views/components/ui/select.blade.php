{{--
 | Select nativo stilizzato: pill alta 48 px, bordo `line-strong`, chevron Lucide.
 | Nativo di proposito: tastiera, lettori di schermo e mobile funzionano da soli. Per
 | cercare tra molte opzioni c'è il combobox (gruppo "pickers"). Solo scelta singola.
 |
 | - `options`: array valore => etichetta; un valore array diventa un <optgroup>
 |   (etichetta del gruppo => [valore => etichetta]). In alternativa, le <option> nello slot;
 | - `placeholder`: prima opzione vuota (value=""); `true` usa il testo predefinito
 |   tradotto. Con `required` è l'opzione "segnaposto" dell'HTML: non soddisfa l'obbligo;
 | - `value`: valore selezionato con `options` (con wire:model lo decide Livewire);
 | - `invalid`: forza lo stato di errore (di norma lo decide x-ui.field);
 | - disabled nativo, con il bordo `line` (sotto il 3:1): è voluto, WCAG 1.4.11 esclude
 |   i componenti inattivi.
 |
 | Dentro x-ui.field eredita id, name, required, aria-describedby e aria-invalid (vedi
 | x-ui.field e App\Support\Ui\ResolvedControl): id, name e required propri non sono
 | ammessi (vanno sul field). Da solo serve un nome accessibile (aria-label o
 | aria-labelledby); lo stato di errore si legge da $errors con il name o, se manca, con
 | la chiave di wire:model.
 | `class` va sul contenitore; gli altri attributi sul <select>.
 |
 | <x-ui.field name="species" :label="__('…')">
 |     <x-ui.select wire:model.live="species" :options="$speciesOptions" placeholder />
 | </x-ui.field>
--}}
@use('App\Support\Ui\ResolvedControl')

@aware(['uiField' => null])

@props([
    'options' => [],
    'placeholder' => null,
    'value' => null,
    'invalid' => null,
])

@php
    // Id, name, obbligo ed errore: dal field se c'è, altrimenti propri (ResolvedControl).
    $control = ResolvedControl::resolve($uiField, $attributes, $errors ?? null, $invalid);
    $invalid = $control->invalid;
    $describedBy = $control->describedBy();

    $placeholder = $placeholder === true ? __('ui_forms.select.placeholder') : $placeholder;
    $selected = static fn (int|string $option): bool => $value !== null && (string) $value === (string) $option;
    $disabled = (bool) $attributes->get('disabled', false);

    $classes = [
        'peer h-12 w-full min-w-0 cursor-pointer appearance-none truncate rounded-full border-2 bg-canvas ps-5 pe-12 text-body text-fg',
        'transition-colors duration-(--duration-fast) ease-out',
        'disabled:cursor-not-allowed disabled:border-line disabled:bg-surface disabled:text-fg-muted',
        $invalid ? 'border-danger' : 'border-line-strong',
        'hover:border-fg' => ! $invalid && ! $disabled,
    ];
@endphp

<div data-ui-select {{ $attributes->only('class')->class('relative flex min-w-0 items-center') }}>
    <select
        @if ($control->id !== null) id="{{ $control->id }}" @endif
        @if ($control->name !== null) name="{{ $control->name }}" @endif
        @if ($describedBy !== null) aria-describedby="{{ $describedBy }}" @endif
        @if ($invalid) aria-invalid="true" @endif
        @required($control->required)
        {{ $attributes->except(['class', 'id', 'name', 'required', 'aria-describedby'])->class($classes) }}
    >
        @if (filled($placeholder))
            <option value="" @selected($value === null || $value === '')>{{ $placeholder }}</option>
        @endif
        @foreach ($options as $optionValue => $optionLabel)
            @if (is_array($optionLabel))
                <optgroup label="{{ $optionValue }}">
                    @foreach ($optionLabel as $groupValue => $groupLabel)
                        <option value="{{ $groupValue }}" @selected($selected($groupValue))>{{ $groupLabel }}</option>
                    @endforeach
                </optgroup>
            @else
                <option value="{{ $optionValue }}" @selected($selected($optionValue))>{{ $optionLabel }}</option>
            @endif
        @endforeach
        {{ $slot }}
    </select>
    <x-ui.icon name="chevron-down" class="pointer-events-none absolute end-5 text-fg peer-disabled:text-fg-muted" />
</div>
