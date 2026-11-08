{{--
 | Card animale (v2) del catalogo, direzione "B · Pop affettuoso". Props primitive: niente
 | Model né query, così la stessa card serve al catalogo, ai preferiti e alla styleguide.
 | Pattern "link esteso" adattato da Sheaf UI (MIT): l'unico link è sul nome e il suo
 | ::after (absolute inset-0) copre tutta la card, quindi è cliccabile ovunque senza link
 | vuoti con aria-label né bottoni dentro un link.
 |
 | - `name`, `href` (obbligatori): nome dell'animale (titolo e testo del link) e scheda;
 | - `photo`: URL della foto, mostrata 4:5 (`width`/`height` espliciti, lazy, object-cover)
 |   con `photo-alt` (default «Foto di <nome>»). Senza foto: zampa su fondo colorato;
 | - `tone` (sun · peach · mint, default sun): il blocco di colore dietro la foto. Nel
 |   catalogo si alterna card per card ($loop->index % 3);
 |   in dark il tono sun usa surface-raised, perché block-sun coincide con il fondo della card;
 | - `species`, `sex`, `age`, `size`: testi già tradotti (es. `$animal->species->getLabel()`),
 |   mostrati come badge. `size` diventa «Taglia <size>», perché da sola sarebbe ambigua;
 | - `location`: città o «città, regione», con icona e un prefisso per gli screen reader;
 | - `featured`: bollino «In evidenza» con stella (il significato è nel testo, non nel colore);
 | - `heading-level` (default 3): livello del titolo (h2…h6) secondo la pagina, per non
 |   saltare livelli (es. 2 se la griglia segue direttamente l'h1);
 | - slot `favorite`: il bottone preferiti, in alto a destra sopra la foto, fuori dal link
 |   e sopra il suo ::after (z-10). Va usato <livewire:favorite-button> o, in pagine statiche,
 |   <x-ui.animal-card.favorite>: in entrambi il nome accessibile contiene il nome dell'animale.
 |
 | Hover e focus del link: la card si solleva di 4 px (solo senza reduced-motion), la foto
 | si ingrandisce appena e il nome si sottolinea. Il focus resta l'anello globale sul nome.
 |
 | Card collegate: <x-ui.animal-card.cta> (ultima card della griglia, scura con CTA gialla) e
 | <x-ui.animal-card.skeleton> (segnaposto di caricamento).
 |
 | <ul role="list" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
 |     @foreach ($animals as $animal)
 |         <li>
 |             <x-ui.animal-card
 |                 :name="$animal->name"
 |                 :href="route('animals.show', $animal)"
 |                 :photo="$animal->getFirstMediaUrl('photos', 'thumb') ?: null"
 |                 :tone="['sun', 'peach', 'mint'][$loop->index % 3]"
 |                 :species="$animal->species->getLabel()"
 |                 :sex="$animal->sex->getLabel()"
 |                 :size="$animal->size?->getLabel()"
 |                 :location="$animal->organization->city"
 |                 :featured="$animal->featured"
 |                 heading-level="2"
 |             >
 |                 <x-slot:favorite>
 |                     <livewire:favorite-button :animal="$animal" :key="'fav-'.$animal->id" />
 |                 </x-slot:favorite>
 |             </x-ui.animal-card>
 |         </li>
 |     @endforeach
 |     <li><x-ui.animal-card.cta :href="route('catalog')" :count="$remaining" heading-level="2" /></li>
 | </ul>
--}}
@props([
    'name',
    'href',
    'photo' => null,
    'photoAlt' => null,
    'photoWidth' => 400,
    'photoHeight' => 500,
    'tone' => 'sun',
    'species' => null,
    'sex' => null,
    'age' => null,
    'size' => null,
    'location' => null,
    'featured' => false,
    'headingLevel' => 3,
])

@php
    $tones = [
        // In dark block-sun coincide con surface (navy-800), il fondo della card: il blocco
        // della foto passa a surface-raised, così resta visibile anche senza foto.
        'sun' => 'bg-block-sun text-on-block-sun dark:bg-surface-raised',
        'peach' => 'bg-block-peach text-on-block-peach',
        'mint' => 'bg-block-mint text-on-block-mint',
    ];
    $tone = array_key_exists((string) $tone, $tones) ? $tone : 'sun';
    $level = min(6, max(2, (int) $headingLevel));

    $badges = array_values(array_filter([
        $species,
        $sex,
        $age,
        filled($size) ? __('ui_domain.animal_card.size', ['size' => mb_strtolower((string) $size)]) : null,
    ], filled(...)));
@endphp

<article
    data-animal-card="{{ $tone }}"
    {{ $attributes->class([
        'group/card relative flex h-full flex-col gap-3 rounded-card bg-surface p-3 text-fg',
        'transition-transform duration-(--duration-base) ease-out',
        'motion-safe:hover:-translate-y-1 motion-safe:has-[[data-card-link]:focus-visible]:-translate-y-1',
    ]) }}
>
    <div class="relative aspect-4/5 overflow-hidden rounded-media {{ $tones[$tone] }}" data-animal-card-media>
        @if (filled($photo))
            <img
                src="{{ $photo }}"
                alt="{{ $photoAlt ?? __('ui_domain.animal_card.photo_alt', ['name' => $name]) }}"
                width="{{ $photoWidth }}"
                height="{{ $photoHeight }}"
                loading="lazy"
                decoding="async"
                class="size-full object-cover transition-transform duration-(--duration-slow) ease-out motion-safe:group-hover/card:scale-105"
            >
        @else
            {{-- Nessuna foto: zampa decorativa (il nome è nel titolo). svg() diretto perché
                 x-ui.icon arriva al massimo a 32 px. --}}
            <span class="flex size-full items-center justify-center" data-animal-card-fallback>
                {{ svg('lucide-paw-print', 'size-16 shrink-0', ['aria-hidden' => 'true', 'focusable' => 'false', 'data-icon' => 'paw-print']) }}
            </span>
        @endif

        @if ($featured)
            <x-ui.badge variant="stamp" icon="star" class="absolute start-3 top-3 -rotate-2 ring-2 ring-canvas" data-animal-card-featured>{{ __('ui_domain.animal_card.featured') }}</x-ui.badge>
        @endif

        @isset($favorite)
            @if ($favorite->isNotEmpty())
                <div class="absolute end-3 top-3 z-10" data-animal-card-favorite>
                    {{ $favorite }}
                </div>
            @endif
        @endisset
    </div>

    <div class="flex flex-1 flex-col gap-3 px-2 pb-2">
        <h{{ $level }} class="text-h4">
            <a
                href="{{ $href }}"
                data-card-link
                class="flex min-h-11 w-full items-center rounded-sm text-fg no-underline decoration-2 underline-offset-4 group-hover/card:underline after:absolute after:inset-0 after:rounded-card"
            >{{ $name }}</a>
        </h{{ $level }}>

        @if ($badges !== [])
            <ul role="list" class="flex flex-wrap gap-1.5">
                @foreach ($badges as $badge)
                    <li><x-ui.badge class="bg-canvas">{{ $badge }}</x-ui.badge></li>
                @endforeach
            </ul>
        @endif

        @if (filled($location))
            <p class="mt-auto flex items-center gap-1.5 text-small text-fg-muted">
                <x-ui.icon name="map-pin" size="sm" />
                <span class="sr-only">{{ __('ui_domain.animal_card.location') }}</span>
                <span class="min-w-0 truncate">{{ $location }}</span>
            </p>
        @endif
    </div>
</article>
