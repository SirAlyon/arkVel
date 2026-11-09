{{--
 | Styleguide · gruppo "map": x-ui.map (segnaposto con cluster e area approssimata),
 | x-ui.map.list (elenco alternativo) e x-ui.view-switch (selettore «Elenco / Mappa»).
 | Testi in lang/{it,en}/styleguide_map.php; JS in resources/js/ui/map.js (MapLibre con
 | import dinamico), tema in resources/css/ui/map.css.
 | Nella styleguide non c’è DB: gli enti di esempio sono MapMarker costruiti qui, con
 | coordinate di città passate da ApproximateLocation come sul sito.
--}}
@php
    use App\Support\Map\ApproximateLocation;
    use App\Support\Map\MapMarker;

    $t = static fn (string $key, array $replace = []): string => __('styleguide_map.'.$key, $replace);

    $marker = static fn (string $id, string $title, string $place, int $count, ?float $lat, ?float $lng): MapMarker => new MapMarker(
        id: $id,
        title: $title,
        description: $place,
        count: $count,
        countLabel: trans_choice('page_catalog.map.animals', $count, ['count' => $count]),
        url: route('catalog', ['organization' => $id]),
        location: ApproximateLocation::fromCoordinates($lat, $lng),
    );

    $markers = [
        $marker('rifugio-aurora', 'Rifugio Aurora', 'Roma, Lazio', 12, 41.8902, 12.4922),
        $marker('gattile-colosseo', 'Gattile Colosseo', 'Roma, Lazio', 5, 41.8986, 12.4769),
        $marker('canile-stella', 'Canile Stella', 'Milano, Lombardia', 8, 45.4642, 9.1900),
        $marker('oasi-del-sole', 'Oasi del Sole', 'Napoli, Campania', 3, 40.8518, 14.2681),
        $marker('amici-a-quattro-zampe', 'Amici a quattro zampe', 'Bari, Puglia', 2, null, null),
    ];

    $area = ApproximateLocation::fromCoordinates(43.7696, 11.2558);

    $views = [
        'list' => ['label' => __('page_catalog.view.list'), 'icon' => 'layout-grid', 'href' => route('styleguide.group', 'map')],
        'map' => ['label' => __('page_catalog.view.map'), 'icon' => 'map', 'href' => route('styleguide.group', ['group' => 'map', 'view' => 'map'])],
    ];
    $current = request()->query('view') === 'map' ? 'map' : 'list';
@endphp
<x-styleguide.layout :title="__('styleguide.page_title', ['group' => __('styleguide.groups.map.title'), 'styleguide' => __('styleguide.title')])" current="map">
    <div class="flex flex-col gap-3 pt-4 md:pt-8">
        <h1 class="text-h1">{{ __('styleguide.groups.map.title') }}</h1>
        <p class="max-w-2xl text-lead text-fg-muted">{{ __('styleguide.groups.map.intro') }}</p>
    </div>

    {{-- ── Map ───────────────────────────────────────────────────────────── --}}
    <x-styleguide.component id="map" :title="$t('map.title')" :description="$t('map.description')" :a11y="[$t('map.a11y_name'), $t('map.a11y_keyboard'), $t('map.a11y_list'), $t('map.a11y_fallback'), $t('map.a11y_motion')]">
        <x-styleguide.example :title="$t('map.markers')">
            <x-slot:code>@verbatim<x-ui.map
    id="catalog-map"
    :label="__('page_catalog.map.label')"
    :markers="$map->markers"
    :fallback="__('page_catalog.map.fallback')"
/>@endverbatim</x-slot:code>
            <x-ui.map id="demo-map-markers" :label="$t('map.markers_label')" :markers="$markers" :fallback="$t('map.fallback')" class="w-full" data-demo="map-markers" />
        </x-styleguide.example>

        <x-styleguide.example :title="$t('map.area')">
            <x-slot:code>@verbatim<x-ui.map
    id="organization-map"
    :label="__('page_organizations.show.map.label', ['name' => $organization->name])"
    :area="\App\Support\Map\ApproximateLocation::of($organization)"
    size="sm"
/>@endverbatim</x-slot:code>
            <x-ui.map id="demo-map-area" :label="$t('map.area_label')" :area="$area" size="sm" class="w-full max-w-xl" data-demo="map-area" />
        </x-styleguide.example>
    </x-styleguide.component>

    {{-- ── Map list ──────────────────────────────────────────────────────── --}}
    <x-styleguide.component id="map-list" tag="x-ui.map.list" :title="$t('list.title')" :description="$t('list.description')" :a11y="[$t('list.a11y_same'), $t('list.a11y_unlocated')]">
        <x-styleguide.example :title="$t('list.example')">
            <x-slot:code>@verbatim<h3 id="catalog-map-list-title">{{ __('page_catalog.map.list_title') }}</h3>
<x-ui.map.list :markers="$map->markers" aria-labelledby="catalog-map-list-title" />@endverbatim</x-slot:code>
            <x-ui.map.list :markers="$markers" :label="$t('list.label')" class="w-full max-w-md" data-demo="map-list" />
        </x-styleguide.example>
    </x-styleguide.component>

    {{-- ── View switch ───────────────────────────────────────────────────── --}}
    <x-styleguide.component id="view-switch" :title="$t('switch.title')" :description="$t('switch.description')" :a11y="[$t('switch.a11y_links'), $t('switch.a11y_current'), $t('switch.a11y_target')]">
        <x-styleguide.example :title="$t('switch.example')">
            <x-slot:code>@verbatim<x-ui.view-switch
    :label="__('page_catalog.view.label')"
    :options="$viewOptions"
    :current="$catalogView->value"
    property="display"
/>@endverbatim</x-slot:code>
            <x-ui.view-switch :label="__('page_catalog.view.label')" :options="$views" :current="$current" data-demo="view-switch" />
        </x-styleguide.example>
    </x-styleguide.component>
</x-styleguide.layout>
