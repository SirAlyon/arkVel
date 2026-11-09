{{--
 | Banda di invito all'azione a tutta larghezza (la banda donazione verde con CTA gialla
 | della home). Pannello piatto rounded-panel con padding `panel`: testo a sinistra e
 | azioni a destra su desktop, tutto in colonna su mobile.
 |
 | - `title` (obbligatorio): il titolo della banda;
 | - `tone`: leaf (default, verde) · inverse · sun · peach · mint. Testo nel colore on-*
 |   del fondo; il focus dei controlli dentro prende lo stesso colore (regole .bg-* di app.css);
 | - `heading-level` (default 2, da 1 a 6) e `size` (h1 · h2 default · h3): livello e aspetto
 |   del titolo. Livello 1 per una banda che è il contenuto principale della pagina;
 | - `icon`: icona Lucide decorativa in un quadrato stondato giallo (navy sul tono sun);
 | - `id`: id del titolo (aria-labelledby della <section>; default dallo slug del titolo);
 | - slot: il testo (text-lead); slot `actions`: i bottoni.
 |
 | Bottoni consigliati: `highlight` (giallo) su leaf e inverse, `primary` su sun, peach e mint.
 | Il giallo come TESTO sul verde è vietato sotto i 24 px (4,43:1): sul verde il giallo è
 | solo il fondo del bottone, con testo navy.
 |
 | <x-ui.cta-band :title="__('…')" icon="hand-heart">
 |     {{ __('…') }}
 |     <x-slot:actions>
 |         <x-ui.button variant="highlight" size="lg" :href="route('donate')">{{ __('…') }}</x-ui.button>
 |     </x-slot:actions>
 | </x-ui.cta-band>
--}}
@props([
    'title',
    'tone' => 'leaf',
    'headingLevel' => 2,
    'size' => 'h2',
    'icon' => null,
    'id' => null,
])

@use('Illuminate\Support\Str')

@php
    $tones = [
        'leaf' => 'bg-block-leaf text-on-block-leaf',
        'inverse' => 'bg-inverse text-on-inverse',
        'sun' => 'bg-block-sun text-on-block-sun',
        'peach' => 'bg-block-peach text-on-block-peach',
        'mint' => 'bg-block-mint text-on-block-mint',
    ];
    $tone = array_key_exists((string) $tone, $tones) ? $tone : 'leaf';
    $level = min(6, max(1, (int) $headingLevel));
    // Slug del titolo; un titolo senza lettere latine ricade su un hash. Con titoli
    // ripetuti nella stessa pagina passare `id` esplicito (gli id devono essere unici).
    $id ??= 'cta-'.(Str::slug($title) ?: substr(md5($title), 0, 8));
@endphp

<section
    aria-labelledby="{{ $id }}"
    data-cta-band="{{ $tone }}"
    {{ $attributes->class(['flex w-full flex-col gap-8 rounded-panel p-panel md:flex-row md:items-center md:justify-between', $tones[$tone]]) }}
>
    <div class="flex max-w-2xl flex-col gap-4">
        @if ($icon)
            <span @class(['inline-flex size-14 items-center justify-center rounded-icon-lg', 'bg-primary text-on-primary' => $tone === 'sun', 'bg-highlight text-on-highlight' => $tone !== 'sun'])>
                <x-ui.icon :name="$icon" size="xl" />
            </span>
        @endif
        <h{{ $level }} id="{{ $id }}" @class(['text-h1' => $size === 'h1', 'text-h3' => $size === 'h3', 'text-h2' => ! in_array($size, ['h1', 'h3'], true)])>{{ $title }}</h{{ $level }}>
        @if ($slot->isNotEmpty())
            <div class="text-lead">{{ $slot }}</div>
        @endif
    </div>

    @isset($actions)
        @if ($actions->isNotEmpty())
            <div class="flex shrink-0 flex-wrap gap-3" data-cta-actions>
                {{ $actions }}
            </div>
        @endif
    @endisset
</section>
