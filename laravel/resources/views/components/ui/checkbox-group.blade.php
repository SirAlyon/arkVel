{{--
 | Gruppo di caselle di spunta (scelta multipla): fieldset e legend (via x-ui.field in
 | modalità `group`), aiuto ed errore del gruppo collegati al fieldset.
 |
 | - `name` (obbligatorio; le caselle diventano `name[]`) e `legend` (obbligatoria;
 |   `hide-legend` la lascia solo agli screen reader);
 | - `options`: valore => etichetta, oppure valore => ['label' => …, 'description' => …];
 |   in alternativa, x-ui.checkbox nello slot (ereditano il name);
 | - `value`: array dei valori spuntati con `options` (con wire:model lo decide Livewire);
 | - `orientation`: vertical (default) · horizontal (va a capo se serve);
 | - `help`, `error` (esplicito; altrimenti `$errors->first($name)`), `required` /
 |   `optional` (solo indicatore testuale: un gruppo di checkbox non ha un "obbligatorio"
 |   nativo, la regola la applica la validazione).
 |
 | Gli attributi wire:model* e x-model* passano alle caselle generate da `options`;
 | gli altri vanno sul fieldset.
 |
 | <x-ui.checkbox-group name="species" :legend="__('…')" :options="$speciesOptions" wire:model="species" orientation="horizontal" />
--}}
@props([
    'name',
    'legend',
    'options' => [],
    'value' => [],
    'orientation' => 'vertical',
    'help' => null,
    'error' => null,
    'required' => false,
    'optional' => false,
    'hideLegend' => false,
])

@php
    $model = $attributes->whereStartsWith(['wire:model', 'x-model']);
    $checked = array_map('strval', (array) $value);
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
    data-ui-checkbox-group
    {{ $attributes->whereDoesntStartWith(['wire:model', 'x-model']) }}
>
    <div @class([
        'flex',
        'flex-col' => $orientation !== 'horizontal',
        'flex-row flex-wrap gap-x-6' => $orientation === 'horizontal',
    ])>
        @foreach ($options as $optionValue => $option)
            <x-ui.checkbox
                :value="$optionValue"
                :checked="in_array((string) $optionValue, $checked, true)"
                :description="is_array($option) ? ($option['description'] ?? null) : null"
                :attributes="$model"
            >{{ is_array($option) ? $option['label'] : $option }}</x-ui.checkbox>
        @endforeach
        {{ $slot }}
    </div>
</x-ui.field>
