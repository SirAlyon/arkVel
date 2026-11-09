{{--
 | Voce di <x-ui.dropdown>: role="menuitem", fuori dall'ordine di tabulazione (tabindex -1,
 | il focus lo muovono le frecce). Altezza minima 44 px.
 |
 | - con `href` è un link, altrimenti un <button> (wire:click, x-on:click…);
 | - `icon`: icona Lucide decorativa prima del testo;
 | - `variant="danger"`: azione distruttiva (testo danger, l'icona rinforza il significato);
 | - `disabled`: aria-disabled, resta raggiungibile con le frecce ma non si attiva.
 |
 | <x-ui.dropdown.item icon="share-2" wire:click="share">{{ __('…') }}</x-ui.dropdown.item>
--}}
@props([
    'href' => null,
    'icon' => null,
    'variant' => 'default',
    'disabled' => false,
])

@php
    $tag = $href !== null ? 'a' : 'button';

    $variants = [
        'default' => 'text-fg hover:bg-surface focus:bg-surface',
        'danger' => 'text-danger hover:bg-danger-subtle hover:text-on-danger-subtle focus:bg-danger-subtle focus:text-on-danger-subtle',
    ];
@endphp

<{{ $tag }}
    @if ($tag === 'a')
        @unless ($disabled) href="{{ $href }}" @endunless
    @else
        type="button"
    @endif
    role="menuitem"
    tabindex="-1"
    @if ($disabled) aria-disabled="true" @endif
    data-variant="{{ $variant }}"
    data-dropdown-item
    {{ $attributes->class([
        'flex min-h-11 w-full items-center gap-3 rounded-full px-4 py-2 text-start font-semibold no-underline transition-colors duration-(--duration-fast) ease-out',
        $disabled ? 'cursor-not-allowed text-fg-muted' : ($variants[$variant] ?? $variants['default']),
    ]) }}
>
    @if ($icon)
        <x-ui.icon :name="$icon" />
    @endif
    <span class="min-w-0 flex-1">{{ $slot }}</span>
</{{ $tag }}>
