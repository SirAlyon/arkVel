{{--
 | Ultima card della griglia animali: blocco scuro (bg-inverse) con zampa, un invito e la
 | CTA gialla «Sfoglia il catalogo» (<x-ui.button variant="highlight">, larga quanto la card:
 | in una griglia a 4 colonne la card è stretta). Ha la stessa altezza delle card animale
 | della riga (h-full) e lo stesso raggio.
 |
 | - `href` (obbligatorio): destinazione della CTA (di solito il catalogo);
 | - `count`: quanti altri animali ci sono, per un testo con il numero formattato nella
 |   lingua corrente («Nel catalogo ci sono altri 128 animali…»);
 | - `title`, `description`, `action`: sostituiscono i testi predefiniti; la descrizione
 |   può anche arrivare dallo slot;
 | - `heading-level` (default 3): lo stesso livello delle card animale vicine.
 |
 | L'unico elemento interattivo è il bottone-link; sul fondo inverse il focus prende il
 | colore on-inverse (regola .bg-inverse di app.css).
 |
 | <li><x-ui.animal-card.cta :href="route('catalog')" :count="$total - 3" /></li>
--}}
@props([
    'href',
    'count' => null,
    'title' => null,
    'description' => null,
    'action' => null,
    'headingLevel' => 3,
])

@use('Illuminate\Support\Number')

@php
    $level = min(6, max(2, (int) $headingLevel));
    $title ??= __('ui_domain.animal_card.cta.title');
    $description ??= $slot->isEmpty()
        ? ($count !== null
            ? trans_choice('ui_domain.animal_card.cta.description_count', (int) $count, ['count' => Number::format((int) $count, locale: app()->getLocale())])
            : __('ui_domain.animal_card.cta.description'))
        : null;
@endphp

<div
    data-animal-card-cta
    {{ $attributes->class('flex h-full min-h-80 flex-col justify-between gap-8 rounded-card bg-inverse p-6 text-on-inverse') }}
>
    <span class="inline-flex size-14 items-center justify-center rounded-icon-lg bg-highlight text-on-highlight">
        <x-ui.icon name="paw-print" size="xl" />
    </span>

    <div class="flex flex-col gap-3">
        <h{{ $level }} class="text-h4">{{ $title }}</h{{ $level }}>
        @if ($description)
            <p>{{ $description }}</p>
        @else
            <div>{{ $slot }}</div>
        @endif
    </div>

    <x-ui.button variant="highlight" size="sm" :href="$href" class="w-full">{{ $action ?? __('ui_domain.animal_card.cta.action') }}</x-ui.button>
</div>
