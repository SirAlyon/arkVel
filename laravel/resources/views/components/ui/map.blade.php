{{--
 | Mappa interattiva (MapLibre GL JS + tile vettoriali OpenFreeMap, UI-7) con posizioni
 | SOLO approssimate (App\Support\Map\ApproximateLocation: il server non manda mai le
 | coordinate salvate).
 |
 | - `label` (obbligatoria): nome accessibile della mappa (aria-label del canvas);
 | - `markers`: list<App\Support\Map\MapMarker>, segnaposto con conteggio (cluster quando
 |   sono vicini) e riquadro con titolo, località, conteggio e link. Si mostrano solo quelli
 |   con una posizione. Con Livewire si aggiornano a ogni render (data-map-markers);
 | - `area`: App\Support\Map\ApproximateLocation, un cerchio (la zona, non un punto);
 | - `fallback`: testo aggiunto al messaggio di errore (es. dove trovare gli stessi dati);
 | - `size`: md (default: quadrata su mobile, 16:9 da sm, 4:3 da lg) · sm (16:9).
 |
 | Performance: MapLibre (e il suo CSS) arriva con import dinamico solo quando la mappa sta
 | per entrare in vista (resources/js/ui/map.js); il riquadro ha le proporzioni riservate e
 | le istruzioni occupano sempre il loro spazio (in errore diventano invisibili, non spariscono):
 | nessun CLS. Il canvas non è mai candidato LCP.
 | Id: `id`, o uno ricavato dall’etichetta e reso univoco nella pagina (App\Support\Ui\ElementIds).
 | Accessibilità: il canvas è raggiungibile con Tab (frecce per spostarsi, + e − per lo
 | zoom, istruzioni visibili collegate con aria-describedby), i segnaposto sono <button>
 | (con Tab solo quelli in vista, nell’ordine dell’elenco), il riquadro è un dialog che si
 | chiude con Esc e restituisce il focus. La mappa non sostituisce i dati: chi la
 | usa affianca un elenco (x-ui.map.list) o un testo. Senza JavaScript o se le tile non si
 | caricano resta un messaggio. Reduced-motion: nessuna animazione di volo.
 | Attribuzione (licenze ODbL di OpenStreetMap e OpenMapTiles) sempre visibile sotto la mappa.
 |
 | <x-ui.map :label="__('…')" :markers="$map->markers" :fallback="__('…')" />
 | <x-ui.map :label="__('…')" :area="$area" size="sm" />
--}}
@props([
    'label',
    'markers' => [],
    'area' => null,
    'fallback' => null,
    'size' => 'md',
    'id' => null,
])

@php
    /** @var list<\App\Support\Map\MapMarker> $markers */
    /** @var \App\Support\Map\ApproximateLocation|null $area */
    $id ??= app(\App\Support\Ui\ElementIds::class)->unique('map-'.substr(md5($label), 0, 8));
    $located = array_values(array_map(
        static fn (\App\Support\Map\MapMarker $marker): array => $marker->toArray(),
        array_filter($markers, static fn (\App\Support\Map\MapMarker $marker): bool => $marker->isLocated()),
    ));
    $error = trim(__('ui_map.error').' '.($fallback ?? ''));

    $config = [
        'styles' => [
            'light' => config('services.maps.style_light'),
            'dark' => config('services.maps.style_dark'),
        ],
        'id' => $id,
        'area' => $area?->toArray(),
        'instructions' => $id.'-instructions',
        'labels' => [
            'error' => $error,
            'marker' => __('ui_map.marker.label'),
            'action' => __('ui_map.marker.action'),
            'actionContext' => __('ui_map.marker.action_context'),
            'cluster' => __('ui_map.cluster.label'),
            'clusterTitle' => __('ui_map.cluster.title'),
            // Testi dei controlli di MapLibre (opzione `locale`).
            'ui' => [
                'Map.Title' => $label,
                'NavigationControl.ZoomIn' => __('ui_map.controls.zoom_in'),
                'NavigationControl.ZoomOut' => __('ui_map.controls.zoom_out'),
                'Popup.Close' => __('ui_map.controls.popup_close'),
                'CooperativeGesturesHandler.WindowsHelpText' => __('ui_map.controls.gestures_windows'),
                'CooperativeGesturesHandler.MacHelpText' => __('ui_map.controls.gestures_mac'),
                'CooperativeGesturesHandler.MobileHelpText' => __('ui_map.controls.gestures_mobile'),
            ],
        ],
    ];

    $frame = [
        'md' => 'aspect-square sm:aspect-video lg:aspect-4/3',
        'sm' => 'aspect-video',
    ][$size] ?? 'aspect-video';
@endphp

<div
    x-data="uiMap(@js($config))"
    x-bind:data-state="state"
    id="{{ $id }}"
    data-ui-map
    data-map-markers="{{ json_encode($located, JSON_THROW_ON_ERROR) }}"
    {{ $attributes->class('flex min-w-0 flex-col gap-2') }}
>
    <div @class(['relative isolate w-full overflow-hidden rounded-card border border-line bg-surface', $frame]) data-map-frame>
        {{-- Il canvas di MapLibre: Livewire non lo tocca (wire:ignore). --}}
        <div x-ref="canvas" wire:ignore class="ui-map absolute inset-0" x-show="state !== 'error'" data-map-canvas></div>

        <div
            x-show="state !== 'ready'"
            class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center gap-3 p-6 text-center text-fg-muted"
            data-map-placeholder
        >
            <x-ui.icon name="map" size="xl" />
            <noscript><p class="text-small font-semibold text-fg">{{ __('ui_map.noscript') }}</p></noscript>
            <p x-show="state === 'loading'" x-cloak class="text-small" aria-hidden="true" data-map-loading>{{ __('ui_map.loading') }}</p>
            {{-- Regione live sempre presente: si riempie solo se la mappa non si carica. --}}
            <p role="status" class="max-w-sm text-small font-semibold text-fg empty:hidden" x-text="state === 'error' ? labels.error : ''" data-map-status></p>
        </div>
    </div>

    {{-- Sempre nel layout (niente x-show: comparirebbe solo dopo Alpine, spostando la pagina);
         in errore resta il suo spazio, invisibile anche agli screen reader. --}}
    <p
        id="{{ $id }}-instructions"
        x-bind:class="{ 'invisible': state === 'error' }"
        x-bind:aria-hidden="state === 'error' ? 'true' : null"
        class="text-caption text-fg-muted"
        data-map-instructions
    >
        {{ $area !== null && $located === [] ? __('ui_map.instructions_area') : __('ui_map.instructions') }}
    </p>

    <p class="text-caption text-fg-muted" data-map-attribution>
        {{ __('ui_map.attribution.label') }}
        <x-ui.link href="https://openfreemap.org" external class="text-caption">{{ __('ui_map.attribution.openfreemap') }}</x-ui.link>
        ·
        <x-ui.link href="https://www.openmaptiles.org/" external class="text-caption">{{ __('ui_map.attribution.openmaptiles') }}</x-ui.link>
        ·
        <x-ui.link href="https://www.openstreetmap.org/copyright" external class="text-caption">{{ __('ui_map.attribution.openstreetmap') }}</x-ui.link>
    </p>
</div>
