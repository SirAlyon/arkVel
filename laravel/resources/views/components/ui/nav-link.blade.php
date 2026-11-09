{{--
 | Voce di navigazione (header, menu mobile): link "pill" con stato attivo navy pieno.
 |
 | - `current`: valore di aria-current: `page` (la pagina della voce), `true` (una pagina
 |   della sua sezione, es. la scheda di un animale per "Catalogo") o null. Lo stato attivo
 |   non passa solo dal colore: aria-current lo annuncia;
 | - `navigate` (default true): wire:navigate.hover, cioè navigazione SPA di Livewire con
 |   prefetch al passaggio del mouse o al focus. Da spegnere per i link fuori dal sito
 |   pubblico (pannelli Filament), che hanno un altro layout;
 | - `layout`: pill (default, nel gruppo di pillole su surface dell'header, 44 px) · block
 |   (riga a tutta larghezza da 48 px, per il menu mobile);
 | - `icon`: icona Lucide decorativa prima del testo.
 |
 | <x-ui.nav-link :href="route('catalog')" current="page">{{ __('…') }}</x-ui.nav-link>
--}}
@props([
    'href',
    'current' => null,
    'navigate' => true,
    'layout' => 'pill',
    'icon' => null,
])

@php
    // [dimensioni, hover]: la pill sta su surface (hover un gradino sopra), il blocco su
    // surface-raised (il fondo del drawer, hover su surface).
    $layouts = [
        'pill' => ['min-h-11 px-4 text-small', 'hover:bg-surface-raised'],
        'block' => ['min-h-12 w-full px-5 text-body', 'hover:bg-surface'],
    ];

    [$box, $hover] = $layouts[$layout] ?? $layouts['pill'];
@endphp

<a
    href="{{ $href }}"
    @if ($navigate) wire:navigate.hover @endif
    @if ($current !== null) aria-current="{{ $current }}" @endif
    data-nav-link
    {{ $attributes->class([
        'inline-flex shrink-0 items-center gap-3 rounded-full font-semibold whitespace-nowrap no-underline transition-colors duration-(--duration-fast) ease-out',
        $box,
        $current !== null ? 'bg-primary text-on-primary hover:bg-primary-hover' : 'text-fg '.$hover,
    ]) }}
>
    @if ($icon)
        <x-ui.icon :name="$icon" />
    @endif
    <span>{{ $slot }}</span>
</a>
