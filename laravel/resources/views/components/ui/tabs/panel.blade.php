{{--
 | Pannello di <x-ui.tabs> (role="tabpanel", aria-labelledby → la sua scheda). Quelli non
 | attivi sono `hidden` già nel render del server.
 |
 | - `name`: lo stesso della scheda (<x-ui.tabs.tab name="…">);
 | - `focusable` (default true): tabindex="0", così chi usa la tastiera raggiunge il
 |   contenuto con Tab dopo la scheda (APG). Metterlo a false quando il pannello comincia
 |   con un elemento focusabile (link, bottone, campo). Alto almeno 44 px, come i target.
 |
 | <x-ui.tabs.panel name="health">…</x-ui.tabs.panel>
--}}
@aware([
    'id',
    'selected' => null,
])

@props([
    'name',
    'focusable' => true,
])

@php
    $active = $selected !== null && (string) $selected === (string) $name;
@endphp

<div
    role="tabpanel"
    id="{{ $id }}-panel-{{ $name }}"
    aria-labelledby="{{ $id }}-tab-{{ $name }}"
    @if ($focusable) tabindex="0" @endif
    @unless ($active) hidden @endunless
    data-tab-panel="{{ $name }}"
    x-bind:hidden="! isSelected(@js((string) $name))"
    {{ $attributes->class('min-h-11 rounded-card') }}
>
    {{ $slot }}
</div>
