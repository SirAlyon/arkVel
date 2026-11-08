{{--
 | Stato vuoto: icona Lucide in un blocco colorato stondato, titolo, descrizione e azioni.
 |
 | - `variant`: preimpostazioni con icona, blocco e testi tradotti (ui_content.empty_state.*):
 |   search («nessun risultato») · favorites («nessun preferito») · error. Senza variante
 |   servono almeno `title` e `icon`;
 | - `title`, `description`: sostituiscono i testi della variante; la descrizione può anche
 |   arrivare dallo slot (per markup, come un link nel testo);
 | - `icon`, `block` (sun · peach · mint · leaf): l'icona (decorativa) e il colore del suo
 |   blocco. Il significato lo porta sempre il testo, mai il solo colore;
 | - `heading-level` (default 2): livello del titolo (h2…h6) secondo la pagina;
 | - `framed` (default true): pannello su superficie; false per usarlo dentro una card;
 | - slot `actions`: uno o più <x-ui.button>;
 | - `id` sul blocco: titolo e descrizione ricevono `<id>-title` e `<id>-description`, da
 |   collegare con aria-labelledby/aria-describedby (es. un blocco di conferma che prende il
 |   focus e va letto per intero).
 |
 | Il blocco `sun` in dark è navy-800 come la superficie: sui pannelli `framed` le varianti
 | usano mint e peach, che restano distinguibili in entrambi i temi.
 |
 | <x-ui.empty-state variant="search" heading-level="2">
 |     <x-slot:actions>
 |         <x-ui.button variant="secondary" wire:click="resetFilters">{{ __('…') }}</x-ui.button>
 |     </x-slot:actions>
 | </x-ui.empty-state>
--}}
@props([
    'variant' => null,
    'title' => null,
    'description' => null,
    'icon' => null,
    'block' => null,
    'headingLevel' => 2,
    'framed' => true,
])

@php
    $presets = [
        'search' => ['icon' => 'search-x', 'block' => 'mint'],
        'favorites' => ['icon' => 'heart', 'block' => 'peach'],
        'error' => ['icon' => 'triangle-alert', 'block' => 'peach'],
    ];

    // Mappa simile a quella di x-ui.avatar, che però per sun usa highlight (vedi lì): le due
    // non coincidono, e condividerle richiederebbe una classe PHP per quattro classi statiche.
    $blocks = [
        'sun' => 'bg-block-sun text-on-block-sun',
        'peach' => 'bg-block-peach text-on-block-peach',
        'mint' => 'bg-block-mint text-on-block-mint',
        'leaf' => 'bg-block-leaf text-on-block-leaf',
    ];

    $preset = $presets[$variant] ?? null;
    $icon ??= $preset['icon'] ?? 'paw-print';
    $block = $block ?? $preset['block'] ?? 'mint';
    $title ??= $preset !== null ? __('ui_content.empty_state.'.$variant.'.title') : null;
    $description ??= $preset !== null && $slot->isEmpty() ? __('ui_content.empty_state.'.$variant.'.description') : null;
    $level = min(6, max(2, (int) $headingLevel));
    $rootId = $attributes->get('id');
    // Attributi pronti (con lo spazio davanti) per non lasciare doppi spazi senza id.
    $titleIdAttribute = $rootId ? ' id="'.e($rootId).'-title"' : '';
    $descriptionIdAttribute = $rootId ? ' id="'.e($rootId).'-description"' : '';
@endphp

<div
    data-empty-state="{{ $variant ?? 'custom' }}"
    {{ $attributes->class([
        'flex flex-col items-center gap-5 text-center',
        'rounded-panel bg-surface px-6 py-12 sm:px-panel' => $framed,
    ]) }}
>
    <span class="inline-flex size-20 items-center justify-center rounded-icon-lg {{ $blocks[$block] ?? $blocks['mint'] }}" data-empty-state-icon="{{ $block }}">
        <x-ui.icon :name="$icon" size="xl" />
    </span>

    <div class="flex max-w-prose flex-col gap-2">
        @if ($title)
            <h{{ $level }}{!! $titleIdAttribute !!} class="text-h4 text-fg">{{ $title }}</h{{ $level }}>
        @endif
        @if ($description)
            <p{!! $descriptionIdAttribute !!} class="text-fg-muted">{{ $description }}</p>
        @elseif ($slot->isNotEmpty())
            <div{!! $descriptionIdAttribute !!} class="text-fg-muted">{{ $slot }}</div>
        @endif
    </div>

    @isset($actions)
        @if ($actions->isNotEmpty())
            <div class="flex flex-wrap items-center justify-center gap-3">
                {{ $actions }}
            </div>
        @endif
    @endisset
</div>
