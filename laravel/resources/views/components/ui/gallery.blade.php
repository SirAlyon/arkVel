{{--
 | Galleria foto (scheda animale): carosello Embla con miniature e lightbox PhotoSwipe,
 | entrambi MIT e caricati con import dinamico solo quando una galleria si inizializza
 | (resources/js/ui/media.js). Pattern WAI-ARIA APG "Carousel" (senza rotazione automatica).
 |
 | Foto, in alternativa:
 | - `media`: i Media di una collection (es. $animal->getMedia('photos')). Slide `hero` 3:2
 |   (anteprima `thumb` finché è in coda), miniature `thumb`, lightbox `zoom` → hero → card
 |   → thumb: sempre conversioni, mai l'originale (App\Support\Media\GalleryImages). L'alt
 |   di ogni foto è la custom property `alt` del Media (stringa o mappa lingua → testo),
 |   altrimenti «Foto n di N di <name>»;
 | - `images`: una lista di App\Support\Media\GalleryImage (foto statiche, styleguide).
 |
 | - `name` (obbligatorio): il soggetto, per il nome della regione («Foto di <name>»), gli alt
 |   di fallback e il nome della lightbox. `label` sostituisce il nome della regione;
 | - `id`: prefisso degli id (default «gallery»), unico nella pagina;
 | - `priority`: la prima slide è l'immagine LCP della pagina (fetchpriority="high");
 | - `sizes`: larghezza mostrata delle slide (default 100vw).
 |
 | Senza JS è una lista di foto scorrevole in orizzontale (scroll-snap) e ogni foto è un
 | link alla foto per la lightbox. Con JS: slide fuori vista `inert`, bottoni precedente/successiva
 | (≥ 44 px, aria-disabled agli estremi per non perdere il focus), frecce sinistra/destra
 | ovunque nella galleria, miniature come bottoni con aria-current, contatore annunciato
 | (aria-live), lightbox con Invio o Spazio sulla foto e focus restituito alla foto mostrata
 | alla chiusura. Con reduced-motion gli spostamenti sono istantanei. Lo swipe ha sempre
 | l'alternativa dei bottoni (WCAG 2.5.7).
 | Bottoni, contatore e miniature sono `invisible` (non `hidden`) finché Embla non è pronto:
 | occupano già il loro spazio, così all'avvio il contenuto sotto la galleria non si sposta
 | (CLS), e intanto restano fuori dall'albero accessibile e dal Tab.
 |
 | <x-ui.gallery :media="$animal->getMedia('photos')" :name="$animal->name" priority
 |     sizes="(min-width: 1024px) 60vw, 100vw" />
--}}
@props([
    'media' => null,
    'images' => null,
    'name',
    'label' => null,
    'id' => 'gallery',
    'priority' => false,
    'sizes' => '100vw',
])

@php
    use App\Support\Media\GalleryImages;

    /** @var list<\App\Support\Media\GalleryImage> $items */
    $items = $images !== null
        ? array_values(is_array($images) ? $images : iterator_to_array($images, false))
        : app(GalleryImages::class)->fromMedia($media ?? [], $name);
    $total = count($items);
    $multiple = $total > 1;

    $t = static fn (string $key, array $replace = []): string => __('ui_media.gallery.'.$key, $replace);

    $options = [
        'total' => $total,
        'labels' => [
            'counter' => $t('counter', ['position' => ':position', 'total' => ':total']),
            'dialog' => $t('lightbox.label', ['name' => $name]),
            'roledescription' => $t('roledescription'),
            'slide' => $t('slide_roledescription'),
            'close' => $t('lightbox.close'),
            'zoom' => $t('lightbox.zoom'),
            'previous' => $t('lightbox.previous'),
            'next' => $t('lightbox.next'),
            'error' => $t('lightbox.error'),
        ],
    ];
@endphp

@if ($total > 0)
    <section
        id="{{ $id }}"
        aria-label="{{ $label ?? $t('label', ['name' => $name]) }}"
        aria-roledescription="{{ $t('roledescription') }}"
        data-ui-gallery
        x-data="uiGallery(@js($options))"
        x-on:keydown="onKeydown($event)"
        {{ $attributes->class('group/gallery flex min-w-0 flex-col gap-3') }}
    >
        <div
            data-gallery-viewport
            class="overflow-x-auto overscroll-x-contain rounded-media snap-x snap-mandatory group-data-ready/gallery:snap-none group-data-ready/gallery:overflow-hidden"
        >
            <ul role="list" data-gallery-container class="flex touch-pan-y">
                @foreach ($items as $index => $image)
                    @php($zoom = $image->zoomOrSlide())
                    <li data-gallery-slide class="min-w-0 shrink-0 grow-0 basis-full snap-start">
                        <div
                            role="group"
                            aria-roledescription="{{ $t('slide_roledescription') }}"
                            aria-label="{{ $t('slide', ['position' => $index + 1, 'total' => $total]) }}"
                        >
                            <a
                                href="{{ $zoom->src }}"
                                data-gallery-zoom
                                data-pswp-width="{{ $zoom->width }}"
                                data-pswp-height="{{ $zoom->height }}"
                                @if ($zoom->srcset) data-pswp-srcset="{{ $zoom->srcset }}" @endif
                                data-cropped="true"
                                x-on:keydown.space.prevent="open({{ $index }})"
                                class="group/zoom relative block cursor-zoom-in rounded-media focus-visible:outline-none"
                            >
                                <x-ui.picture
                                    :picture="$image->slide"
                                    :alt="$image->alt"
                                    :sizes="$sizes"
                                    :priority="$priority && $loop->first"
                                />
                                <span class="sr-only">{{ $t('open') }}</span>
                                {{-- Focus disegnato dentro la foto (il viewport la ritaglia): anello di 3 px
                                     nel colore del focus su un bordo canvas, leggibile su qualunque foto. --}}
                                <span aria-hidden="true" class="pointer-events-none absolute inset-0 hidden rounded-media outline-3 -outline-offset-3 ring-6 ring-inset ring-canvas group-focus-visible/zoom:block"></span>
                            </a>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>

        @if ($multiple)
            <div class="invisible flex items-center justify-between gap-3" data-gallery-controls x-bind:class="{ invisible: !ready }">
                <p class="text-small font-semibold text-fg-muted" aria-live="polite" aria-atomic="true">
                    <span aria-hidden="true"><span x-text="current + 1">1</span> / {{ $total }}</span>
                    <span class="sr-only" x-text="counter()">{{ $t('counter', ['position' => 1, 'total' => $total]) }}</span>
                </p>
                <div class="flex gap-2">
                    <x-ui.button
                        variant="secondary"
                        size="sm"
                        icon="chevron-left"
                        :label="$t('previous')"
                        data-gallery-previous
                        x-on:click="previous()"
                        x-bind:aria-disabled="atStart ? 'true' : 'false'"
                        class="aria-disabled:cursor-not-allowed aria-disabled:border-line aria-disabled:text-fg-muted aria-disabled:hover:bg-transparent rtl:[&_svg]:rotate-180"
                    />
                    <x-ui.button
                        variant="secondary"
                        size="sm"
                        icon="chevron-right"
                        :label="$t('next')"
                        data-gallery-next
                        x-on:click="next()"
                        x-bind:aria-disabled="atEnd ? 'true' : 'false'"
                        class="aria-disabled:cursor-not-allowed aria-disabled:border-line aria-disabled:text-fg-muted aria-disabled:hover:bg-transparent rtl:[&_svg]:rotate-180"
                    />
                </div>
            </div>

            <div data-gallery-thumbs x-bind:class="{ invisible: !ready }" class="invisible -m-2 overflow-x-auto p-2">
                <ul role="list" aria-label="{{ $t('thumbnails') }}" class="flex w-max gap-2">
                    @foreach ($items as $index => $image)
                        <li>
                            <button
                                type="button"
                                data-gallery-thumb="{{ $index }}"
                                aria-label="{{ $t('thumbnail', ['position' => $index + 1, 'total' => $total]) }}"
                                aria-current="{{ $index === 0 ? 'true' : 'false' }}"
                                x-bind:aria-current="current === {{ $index }} ? 'true' : 'false'"
                                x-on:click="goTo({{ $index }})"
                                class="block size-16 overflow-hidden rounded-icon opacity-70 ring-offset-2 ring-offset-canvas transition-opacity duration-(--duration-fast) ease-out hover:opacity-100 aria-[current=true]:opacity-100 aria-[current=true]:ring-3 aria-[current=true]:ring-fg"
                            >
                                <x-ui.picture :picture="$image->thumb" decorative fill :rounded="false" sizes="4rem" />
                            </button>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </section>
@endif
