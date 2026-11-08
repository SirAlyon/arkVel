{{--
 | Scheda di <x-ui.tabs> (role="tab"): bottone pill da 44 px, attiva navy piena (giallo in
 | dark). Lo stato attivo non passa dal solo colore: aria-selected per gli screen reader e,
 | a vista, il fondo pieno che cambia forma al blocco.
 |
 | - `name`: identifica la scheda e il suo pannello (<x-ui.tabs.panel name="…">);
 | - `icon`: icona Lucide decorativa prima del testo.
 |
 | <x-ui.tabs.tab name="health" icon="stethoscope">{{ __('…') }}</x-ui.tabs.tab>
--}}
@aware([
    'id',
    'selected' => null,
])

@props([
    'name',
    'icon' => null,
])

@php
    $active = $selected !== null && (string) $selected === (string) $name;
@endphp

<button
    type="button"
    role="tab"
    id="{{ $id }}-tab-{{ $name }}"
    aria-controls="{{ $id }}-panel-{{ $name }}"
    aria-selected="{{ $active ? 'true' : 'false' }}"
    tabindex="{{ $active ? '0' : '-1' }}"
    data-tab="{{ $name }}"
    x-on:click="select(@js((string) $name))"
    x-bind:aria-selected="isSelected(@js((string) $name)).toString()"
    x-bind:tabindex="isSelected(@js((string) $name)) ? 0 : -1"
    {{ $attributes->class([
        'inline-flex min-h-11 shrink-0 items-center gap-2 whitespace-nowrap rounded-full px-4 text-small font-semibold text-fg',
        'transition-colors duration-(--duration-fast) ease-out hover:bg-surface-raised',
        'aria-selected:bg-primary aria-selected:text-on-primary',
    ]) }}
>
    @if ($icon)
        <x-ui.icon :name="$icon" size="sm" />
    @endif
    <span>{{ $slot }}</span>
</button>
