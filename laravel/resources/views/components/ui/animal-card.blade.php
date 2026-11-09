{{--
 | Card animale (v2) del catalogo, direzione "B · Pop affettuoso". Props primitive: niente
 | Model né query, così la stessa card serve al catalogo, ai preferiti e alla styleguide.
 | Pattern "link esteso" adattato da Sheaf UI (MIT): l'unico link è sul nome e il suo
 | ::after (absolute inset-0) copre tutta la card, quindi è cliccabile ovunque senza link
 | vuoti con aria-label né bottoni dentro un link.
 |
 | - `name`, `href` (obbligatori): nome dell'animale (titolo e testo del link) e scheda;
 | - `media`: il Media medialibrary della foto (UI-4): reso con <x-ui.picture> conversione
 |   `card` 4:5 (AVIF/WebP/JPEG, srcset con `photo-sizes`, placeholder, mai l'originale);
 |   `priority` per la prima card sopra la piega (immagine LCP: niente lazy);
 | - `photo`: in alternativa un URL già pronto (styleguide, contenuti statici), mostrato 4:5
 |   (`width`/`height` espliciti, lazy, object-cover). `photo-alt` (default «Foto di <nome>»)
 |   vale per entrambi. Senza foto: zampa su fondo colorato;
 | - `navigate` (default true): il link usa wire:navigate.hover (navigazione SPA della shell);
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
 | - `reveal` (default true): la card compare allo scroll (x-reveal, resources/js/ui/motion.js):
 |   mai sopra la piega, mai senza JS o con reduced-motion. Spenta con `priority` (LCP);
 | - `transition-name`: nome della View Transition della foto (App\Support\Site\AnimalPhotoTransition),
 |   unico nella pagina: con wire:navigate la foto vola dalla card alla galleria della scheda.
 |   x-site.animal-card lo passa da sé; null (default) = nessuna transizione della foto.
 |
 | Foto in una cornice del colore del blocco (padding 8 px, angoli interni 16 px = raggio
 | media meno la cornice): il tono a rotazione della direzione B resta visibile anche con
 | la foto.
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
 |                 :media="$animal->getFirstMedia('photos') ?? $animal->getFirstMedia('avatar')"
 |                 :priority="$loop->first"
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
    'media' => null,
    'photoSizes' => '(min-width: 1024px) 25vw, (min-width: 640px) 50vw, 100vw',
    'priority' => false,
    'navigate' => true,
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
    'reveal' => true,
    'transitionName' => null,
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
    @if ($reveal && ! $priority) x-reveal @endif
    {{ $attributes->class([
        'group/card relative flex h-full flex-col gap-3 rounded-card bg-surface p-3 text-fg',
        'transition-transform duration-(--duration-base) ease-out',
        'motion-safe:hover:-translate-y-1 motion-safe:has-[[data-card-link]:focus-visible]:-translate-y-1',
    ]) }}
>
    <div class="relative aspect-4/5 rounded-media p-2 {{ $tones[$tone] }}" data-animal-card-media>
        <div
            class="relative size-full overflow-hidden rounded-icon-lg"
            data-animal-card-photo
            @if (filled($transitionName)) wire:transition.navigate="{{ $transitionName }}" data-photo-transition="{{ $href }}" @endif
        >
            @if ($media instanceof \Spatie\MediaLibrary\MediaCollections\Models\Media)
                <x-ui.picture
                    :media="$media"
                    conversion="card"
                    fill
                    :rounded="false"
                    :alt="$photoAlt ?? __('ui_domain.animal_card.photo_alt', ['name' => $name])"
                    :sizes="$photoSizes"
                    :priority="$priority"
                    class="transition-transform duration-(--duration-slow) ease-out motion-safe:group-hover/card:scale-105"
                />
            @elseif (filled($photo))
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
        </div>

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
                @if ($navigate) wire:navigate.hover @endif
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
