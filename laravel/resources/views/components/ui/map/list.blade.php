{{--
 | Elenco alternativo di una mappa (x-ui.map): gli STESSI segnaposto in una lista di link,
 | per chi usa tastiera o screen reader, per chi non vede la mappa (JavaScript spento, tile
 | non caricate) e per chi preferisce leggere. Sempre presente accanto o sotto la mappa.
 |
 | - `markers`: list<App\Support\Map\MapMarker>, nello stesso ordine in cui li legge chi usa
 |   lo screen reader (di solito per nome);
 | - `label`: nome della lista (aria-label), se non c’è un titolo collegato con
 |   aria-labelledby (passato come attributo).
 |
 | Ogni voce: conteggio in evidenza (decorativo, il testo lo ripete), titolo come link,
 | località pubblica (o «Posizione non disponibile» per chi non è sulla mappa) e conteggio.
 |
 | <x-ui.map.list :markers="$map->markers" aria-labelledby="catalog-map-list-title" />
--}}
@props([
    'markers' => [],
    'label' => null,
])

<ul
    role="list"
    @if ($label !== null) aria-label="{{ $label }}" @endif
    {{ $attributes->class('flex flex-col gap-2') }}
    data-map-list
>
    @foreach ($markers as $marker)
        <li wire:key="map-list-{{ $marker->id }}" class="flex min-w-0 items-start gap-3 rounded-step bg-surface p-4" data-map-list-item="{{ $marker->id }}">
            <span class="inline-flex min-w-11 shrink-0 items-center justify-center rounded-icon bg-primary px-2 py-2.5 text-small font-bold text-on-primary" aria-hidden="true">{{ \Illuminate\Support\Number::format($marker->count, locale: app()->getLocale()) }}</span>
            <div class="flex min-w-0 flex-col gap-1">
                <x-ui.link :href="$marker->url" variant="inherit" wire:navigate class="self-start text-body font-bold text-fg [overflow-wrap:anywhere]">{{ $marker->title }}</x-ui.link>
                <p class="text-small text-fg-muted">
                    <span>{{ $marker->description ?? __('ui_map.list.unlocated') }}</span>
                    @if ($marker->description !== null && ! $marker->isLocated())
                        <span>({{ __('ui_map.list.unlocated') }})</span>
                    @endif
                    <span aria-hidden="true">·</span>
                    <span>{{ $marker->countLabel }}</span>
                </p>
            </div>
        </li>
    @endforeach
</ul>
