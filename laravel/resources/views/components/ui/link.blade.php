{{--
 | Link testuale: sottolineato (mai riconoscibile solo dal colore), colore `link`.
 |
 | - `external`: apre in una nuova scheda (rel noopener), con icona e un testo per
 |   gli screen reader che lo annuncia;
 | Link testuale: per WCAG 2.5.8 basta un target di 24 px (data-text-link), non i 44 px
 | dei controlli. Per un'azione isolata usare <x-ui.button>.
 | - `variant="inherit"` usa il colore del testo circostante (link dentro i blocchi
 |   colorati, dove `link` non è verificato come coppia).
 |
 | <x-ui.link :href="route('catalog')">{{ __('…') }}</x-ui.link>
 | <x-ui.link href="https://…" external>…</x-ui.link>
--}}
@props([
    'href',
    'external' => false,
    'variant' => 'default',
])

<a
    href="{{ $href }}"
    @if ($external) target="_blank" rel="noopener noreferrer" @endif
    data-text-link
    {{ $attributes->class([
        'inline-flex items-baseline gap-1 rounded-sm font-semibold underline decoration-2 underline-offset-4 transition-colors duration-(--duration-fast) ease-out hover:decoration-4',
        'text-link hover:text-link-hover' => $variant === 'default',
        'text-current' => $variant === 'inherit',
    ]) }}
>{{ $slot }}@if ($external)<x-ui.icon name="arrow-up-right" size="sm" class="self-center" /><span class="sr-only">{{ __('ui.link.external') }}</span>@endif</a>
