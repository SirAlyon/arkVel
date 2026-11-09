{{--
 | Gruppo di opzioni radio: fieldset con role="radiogroup" e legend (via x-ui.field in
 | modalità `group`), aiuto ed errore del gruppo collegati al fieldset.
 |
 | - `name` (obbligatorio) e `legend` (obbligatoria; `hide-legend` la lascia solo agli
 |   screen reader);
 | - `options`: valore => etichetta, oppure valore => ['label' => …, 'description' => …];
 |   in alternativa, x-ui.radio nello slot (ereditano name e required);
 | - `value`: opzione selezionata con `options` (con wire:model la decide Livewire);
 | - `orientation`: vertical (default) · horizontal (va a capo se serve);
 | - `help`, `error` (esplicito; altrimenti `$errors->first($name)`), `required`
 |   (aria-required sul gruppo e required nativo sulle opzioni), `optional`.
 |
 | Gli attributi wire:model* e x-model* passano alle opzioni generate da `options`;
 | gli altri vanno sul fieldset. La tastiera è quella nativa: Tab entra nel gruppo,
 | le frecce cambiano la scelta.
 |
 | <x-ui.radio-group name="home" :legend="__('…')" :options="$homeOptions" wire:model="home" required />
--}}
@use('App\Support\Ui\ControlAttributes')

@props([
    'name',
    'legend',
    'options' => [],
    'value' => null,
    'orientation' => 'vertical',
    'help' => null,
    'error' => null,
    'required' => false,
    'optional' => false,
    'hideLegend' => false,
])

@php
    $invalid = ControlAttributes::message($error, $name, $errors ?? null) !== null;
    $model = $attributes->whereStartsWith(['wire:model', 'x-model']);
@endphp

<x-ui.field
    group
    :name="$name"
    :label="$legend"
    :help="$help"
    :error="$error"
    :required="$required"
    :optional="$optional"
    :hide-label="$hideLegend"
    role="radiogroup"
    :aria-required="$required ? 'true' : null"
    :aria-invalid="$invalid ? 'true' : null"
    data-ui-radio-group
    {{ $attributes->whereDoesntStartWith(['wire:model', 'x-model']) }}
>
    <div @class([
        'flex',
        'flex-col' => $orientation !== 'horizontal',
        'flex-row flex-wrap gap-x-6' => $orientation === 'horizontal',
    ])>
        @foreach ($options as $optionValue => $option)
            <x-ui.radio
                :value="$optionValue"
                :checked="$value !== null && (string) $value === (string) $optionValue"
                :description="is_array($option) ? ($option['description'] ?? null) : null"
                :attributes="$model"
            >{{ is_array($option) ? $option['label'] : $option }}</x-ui.radio>
        @endforeach
        {{ $slot }}
    </div>
</x-ui.field>
