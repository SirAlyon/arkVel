{{--
 | Chip "pill" con bordo `line-strong`. Tre usi:
 |
 | - statico: etichetta su superficie (`<x-ui.tag>Taglia media</x-ui.tag>`);
 | - filtro a scelta: con `pressed` (true/false) è un <button> con aria-pressed; lo
 |   stato attivo è navy pieno. Gli attributi (wire:click, x-bind:aria-pressed...)
 |   vanno sul bottone;
 | - rimovibile: con `removable` mostra un bottone "×" da 44 px con nome
 |   "Rimuovi <label>"; gli attributi diversi da `class` vanno sul bottone di rimozione.
 |
 | <x-ui.tag :pressed="$species === 'dog'" wire:click="$set('species', 'dog')">Cani</x-ui.tag>
 | <x-ui.tag removable label="Cani" wire:click="removeFilter('species')">Cani</x-ui.tag>
--}}
@props([
    'pressed' => null,
    'removable' => false,
    'label' => null,
    'icon' => null,
])

@php
    $base = 'inline-flex min-h-11 items-center gap-2 rounded-full border-2 font-semibold';
@endphp

@if ($pressed !== null)
    <button
        type="button"
        aria-pressed="{{ $pressed ? 'true' : 'false' }}"
        {{ $attributes->class([
            $base,
            'border-line-strong bg-canvas px-5 text-fg transition-colors duration-(--duration-fast) ease-out hover:border-fg',
            'aria-pressed:border-primary aria-pressed:bg-primary aria-pressed:text-on-primary',
        ]) }}
    >
        @if ($icon)
            <x-ui.icon :name="$icon" size="sm" />
        @endif
        {{ $slot }}
    </button>
@elseif ($removable)
    <span {{ $attributes->only('class')->class([$base, 'border-line-strong bg-canvas ps-5 pe-0 text-fg']) }}>
        @if ($icon)
            <x-ui.icon :name="$icon" size="sm" />
        @endif
        <span>{{ $slot }}</span>
        <button
            type="button"
            aria-label="{{ __('ui.tag.remove', ['label' => $label ?? trim(strip_tags((string) $slot))]) }}"
            {{ $attributes->except('class')->class('-my-0.5 inline-flex size-11 items-center justify-center rounded-full text-fg transition-colors duration-(--duration-fast) ease-out hover:bg-surface') }}
        >
            <x-ui.icon name="x" size="sm" />
        </button>
    </span>
@else
    <span {{ $attributes->class([$base, 'border-transparent bg-surface px-4 text-small text-fg']) }}>
        @if ($icon)
            <x-ui.icon :name="$icon" size="sm" />
        @endif
        {{ $slot }}
    </span>
@endif
