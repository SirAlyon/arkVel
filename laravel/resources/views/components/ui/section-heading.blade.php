{{--
 | Intestazione di una sezione di pagina: eyebrow opzionale, titolo, descrizione e un'azione
 | (es. «Vedi tutti») a destra su desktop e sotto il testo su mobile.
 |
 | - `title` (obbligatorio): il titolo;
 | - `id`: id del titolo, da usare per `aria-labelledby` della <section> che lo contiene
 |   (default: «heading-» + slug del titolo);
 | - `heading-level` (default 2): livello del titolo (h2…h6) secondo la pagina;
 | - `size`: h2 (default, text-h2) · h3: l'aspetto, indipendente dal livello;
 | - `eyebrow`: testo breve sopra il titolo; `eyebrow-variant` badge (default, pill gialla)
 |   o text (testo piccolo in grassetto);
 | - `description`: sottotitolo text-lead in fg-muted (o lo slot);
 | - slot `action`: link o bottone, es. <x-ui.button variant="secondary" :href="…">;
 | - `reveal` (default true): compare allo scroll (x-reveal, resources/js/ui/motion.js): mai
 |   sopra la piega, mai senza JS o con reduced-motion. false dentro un contenitore che ha
 |   già il suo x-reveal.
 |
 | <section aria-labelledby="featured-title">
 |     <x-ui.section-heading id="featured-title" :title="__('…')" :eyebrow="__('…')" :description="__('…')">
 |         <x-slot:action>
 |             <x-ui.button variant="secondary" :href="route('catalog')" icon-trailing="arrow-right">{{ __('…') }}</x-ui.button>
 |         </x-slot:action>
 |     </x-ui.section-heading>
 |     …
 | </section>
--}}
@props([
    'title',
    'id' => null,
    'headingLevel' => 2,
    'size' => 'h2',
    'eyebrow' => null,
    'eyebrowVariant' => 'badge',
    'description' => null,
    'reveal' => true,
])

@use('Illuminate\Support\Str')

@php
    $level = min(6, max(2, (int) $headingLevel));
    // Slug del titolo; un titolo senza lettere latine ricade su un hash. Con titoli
    // ripetuti nella stessa pagina passare `id` esplicito (gli id devono essere unici).
    $id ??= 'heading-'.(Str::slug($title) ?: substr(md5($title), 0, 8));
@endphp

<div data-section-heading @if ($reveal) x-reveal @endif {{ $attributes->class('flex flex-col gap-5 md:flex-row md:items-end md:justify-between') }}>
    <div class="flex max-w-3xl flex-col gap-3">
        @if (filled($eyebrow))
            @if ($eyebrowVariant === 'text')
                <p class="text-small font-bold text-fg-muted" data-section-eyebrow="text">{{ $eyebrow }}</p>
            @else
                <p data-section-eyebrow="badge"><x-ui.badge variant="highlight">{{ $eyebrow }}</x-ui.badge></p>
            @endif
        @endif

        <h{{ $level }} id="{{ $id }}" @class(['text-fg', 'text-h3' => $size === 'h3', 'text-h2' => $size !== 'h3'])>{{ $title }}</h{{ $level }}>

        @if (filled($description))
            <p class="text-lead text-fg-muted">{{ $description }}</p>
        @elseif ($slot->isNotEmpty())
            <div class="text-lead text-fg-muted">{{ $slot }}</div>
        @endif
    </div>

    @isset($action)
        @if ($action->isNotEmpty())
            <div class="flex shrink-0 flex-wrap gap-3" data-section-action>
                {{ $action }}
            </div>
        @endif
    @endisset
</div>
