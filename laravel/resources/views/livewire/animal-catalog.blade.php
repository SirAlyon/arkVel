{{--
 | Catalogo pubblico (/animali, UI-6b), direzione «B · Pop affettuoso».
 |
 | - ricerca con label visibile (wire:model.live.debounce): si aggiornano solo risultati,
 |   conteggi e chip; l'esito è nella regione live `data-catalog-status` (role=status);
 | - filtri con i conteggi nella colonna laterale da lg e in un drawer (bottom sheet) sotto;
 | - chip dei filtri attivi rimovibili: dopo la rimozione il focus passa al chip vicino, o
 |   al campo di ricerca se non ne restano;
 | - griglia di x-site.animal-card (h2: la pagina ha solo l'h1 sopra), la prima con
 |   `priority` (immagine LCP); i preferiti dell'utente arrivano in una query sola
 |   ($favoriteIds); ultima card verso gli enti quando i risultati finiscono;
 | - caricamento continuo: wire:intersect sulle prime CatalogResults::AUTO_LOAD_PAGES
 |   pagine, poi «Carica altri» (un link a ?page=N+1, che senza JavaScript cambia pagina),
 |   più x-ui.pagination (link veri) per chi non ha JavaScript e per i motori di ricerca;
 | - vista «Mappa» (?view=map, UI-7, x-ui.view-switch): al posto della griglia la mappa
 |   degli enti con animali che corrispondono ai filtri (x-ui.map, posizioni approssimate)
 |   e, sempre, l’elenco alternativo degli stessi enti (x-ui.map.list);
 | - senza JavaScript ricerca, filtri e ordinamento sono moduli GET verso /animali.
--}}
@php
    /** @var \App\Support\Catalog\CatalogResults $results */
    $activeFacets = count($results->filters->activeFacets());
    $tones = ['sun', 'peach', 'mint'];
    $mapView = $catalogView === \App\Enums\CatalogView::Map;
    // La mappa non carica animali (solo totale e faccette): lì conta il totale.
    $hasResults = $mapView ? $results->total > 0 : ! $results->isEmpty();
    // Stato dei moduli GET senza JavaScript: filtri, ordinamento e vista.
    $state = [...$results->stateParameters(), ...$catalogView->queryParameters()];
@endphp

<div class="mx-auto w-full max-w-page px-gutter pb-section pt-6 sm:pt-10" data-catalog>
    <header class="flex max-w-3xl flex-col gap-3">
        <h1 class="text-h1 text-fg">{{ __('page_catalog.title') }}</h1>
        <p class="text-lead text-fg-muted">{{ __('page_catalog.intro') }}</p>
    </header>

    {{-- Ricerca: label visibile, risultati che si aggiornano mentre si scrive. --}}
    <form
        role="search"
        method="get"
        action="{{ route('catalog') }}"
        wire:submit="$refresh"
        class="mt-8 flex max-w-3xl flex-col gap-3"
        data-catalog-search
    >
        <x-site.catalog.hidden-state :parameters="$state" :except="['q']" />
        <x-ui.field name="q" :label="__('page_catalog.search.label')" :help="__('page_catalog.search.help')">
            <x-ui.input
                type="search"
                icon="search"
                wire:model.live.debounce.400ms="search"
                :value="$search"
                :placeholder="__('page_catalog.search.placeholder')"
                maxlength="{{ \App\Support\Catalog\ResolveCatalogFilters::MAX_SEARCH_LENGTH }}"
                autocomplete="off"
                enterkeyhint="search"
            />
        </x-ui.field>
        <noscript>
            <x-ui.button type="submit" icon="search">{{ __('page_catalog.search.submit') }}</x-ui.button>
        </noscript>
    </form>

    <div class="mt-10 grid gap-8 lg:grid-cols-4 lg:gap-10">
        {{-- Filtri da lg: colonna laterale. Sotto lg sono nel drawer in fondo. --}}
        <aside class="hidden lg:block" aria-labelledby="catalog-filters-heading" data-catalog-sidebar>
            <div class="flex flex-col gap-5 rounded-card bg-surface p-6">
                <div class="flex flex-col gap-1">
                    <h2 id="catalog-filters-heading" class="text-h5 text-fg">{{ __('page_catalog.filters.title') }}</h2>
                    <p class="text-small text-fg-muted">{{ __('page_catalog.filters.description') }}</p>
                </div>
                <x-site.catalog.filters :results="$results" prefix="sidebar" />
            </div>
        </aside>

        <div class="flex min-w-0 flex-col gap-6 lg:col-span-3">
            {{-- Barra dei risultati: esito (regione live), filtri su mobile, ordinamento. --}}
            <div class="flex flex-wrap items-end justify-between gap-4">
                <p role="status" class="text-h5 text-fg" data-catalog-status>{{ $results->summary() }}</p>

                <div class="flex w-full flex-wrap items-end gap-3 sm:w-auto">
                    <div class="lg:hidden" x-data>
                        <x-ui.button
                            variant="secondary"
                            icon="sliders-horizontal"
                            aria-haspopup="dialog"
                            x-on:click="$dispatch('open-drawer', 'catalog-filters')"
                            data-catalog-filters-trigger
                        >
                            {{ __('page_catalog.filters.open') }}
                            @if ($activeFacets > 0)
                                <x-ui.badge variant="primary" aria-hidden="true">{{ $activeFacets }}</x-ui.badge>
                                <span class="sr-only">({{ trans_choice('page_catalog.filters.open_active', $activeFacets, ['count' => $activeFacets]) }})</span>
                            @endif
                        </x-ui.button>
                    </div>

                    <x-ui.view-switch
                        :label="__('page_catalog.view.label')"
                        :options="$viewOptions"
                        :current="$catalogView->value"
                        property="display"
                        data-catalog-view-switch
                    />

                    {{-- Nella mappa l’ordine non conta: l’ordinamento è solo nella griglia. --}}
                    @unless ($mapView)
                        <form
                            method="get"
                            action="{{ route('catalog') }}"
                            wire:submit="$refresh"
                            class="flex min-w-0 basis-full flex-wrap items-end gap-3 sm:basis-auto"
                            data-catalog-sort
                        >
                            <x-site.catalog.hidden-state :parameters="$state" :except="['sort']" />
                            <x-ui.field name="sort" :label="__('page_catalog.sort.label')" class="min-w-0 flex-1 sm:w-64 sm:flex-none">
                                <x-ui.select wire:model.live="sort" :value="$sort" :options="$sortOptions" />
                            </x-ui.field>
                            <noscript>
                                <x-ui.button type="submit" variant="secondary">{{ __('page_catalog.sort.apply') }}</x-ui.button>
                            </noscript>
                        </form>
                    @endunless
                </div>
            </div>

            {{-- Chip dei filtri attivi. Togliendone uno il focus va al chip che prende il suo
                 posto, al precedente o, se non ne restano, al campo di ricerca. --}}
            @if ($results->activeFilters !== [])
                <div
                    class="flex flex-wrap items-center gap-2"
                    x-data="{
                        remove(key, index) {
                            $wire.removeFilter(key).then(() => requestAnimationFrame(() => {
                                const chips = [...document.querySelectorAll('[data-catalog-chip] button')];
                                const target = chips[index] ?? chips[index - 1] ?? document.getElementById('field-q');
                                target?.focus();
                            }));
                        },
                    }"
                    data-catalog-chips
                >
                    <ul role="list" aria-label="{{ __('page_catalog.chips.label') }}" class="flex flex-wrap gap-2">
                        @foreach ($results->activeFilters as $chip)
                            <li wire:key="chip-{{ $chip->key }}" data-catalog-chip="{{ $chip->key }}">
                                <x-ui.tag removable :label="$chip->description" x-on:click="remove('{{ $chip->key }}', {{ $loop->index }})">{{ $chip->label }}</x-ui.tag>
                            </li>
                        @endforeach
                    </ul>
                    <x-ui.button variant="ghost" size="sm" x-on:click="$wire.resetFilters().then(() => document.getElementById('field-q')?.focus())" data-catalog-reset>{{ __('page_catalog.chips.reset') }}</x-ui.button>
                </div>
            @endif

            @if ($hasResults && $mapView && ! $map->isEmpty())
                {{-- Vista mappa: la mappa (se qualche ente ha una posizione) e sempre l’elenco
                     degli stessi enti, che per tastiera e screen reader vale la mappa. --}}
                <section
                    aria-labelledby="catalog-map-title"
                    class="flex flex-col gap-5 transition-opacity duration-(--duration-fast) ease-out"
                    wire:loading.delay.class="opacity-60"
                    wire:loading.delay.attr="aria-busy"
                    data-catalog-map
                >
                    <div class="flex max-w-3xl flex-col gap-1">
                        <h2 id="catalog-map-title" class="text-h4 text-fg">{{ __('page_catalog.map.title') }}</h2>
                        <p class="text-small text-fg-muted">{{ __('page_catalog.map.description') }}</p>
                    </div>

                    <div class="grid gap-6 xl:grid-cols-[minmax(0,3fr)_minmax(0,2fr)] xl:items-start">
                        @if ($map->located() !== [])
                            <x-ui.map
                                wire:key="catalog-map"
                                id="catalog-map"
                                :label="__('page_catalog.map.label')"
                                :markers="$map->markers"
                                :fallback="__('page_catalog.map.fallback')"
                            />
                        @endif

                        <div class="flex min-w-0 flex-col gap-3" data-catalog-map-list>
                            <h3 id="catalog-map-list-title" class="text-h5 text-fg">{{ __('page_catalog.map.list_title') }}</h3>
                            @if ($map->hasUnlocated())
                                <p class="text-small text-fg-muted" data-catalog-map-unlocated>{{ __('page_catalog.map.unlocated') }}</p>
                            @endif
                            <x-ui.map.list :markers="$map->markers" aria-labelledby="catalog-map-list-title" />
                        </div>
                    </div>
                </section>
            @elseif ($hasResults && $mapView)
                {{-- Animali trovati ma nessun ente da mostrare (indice di ricerca in ritardo):
                     niente elenco vuoto, un rimando alla griglia. --}}
                <x-ui.empty-state
                    icon="map"
                    block="mint"
                    :title="__('page_catalog.map.empty_title')"
                    :description="__('page_catalog.map.empty_description')"
                    data-catalog-empty="map"
                >
                    <x-slot:actions>
                        <x-ui.button variant="secondary" icon="layout-grid" :href="$viewOptions['list']['href']" wire:click.prevent="$set('display', 'list')">{{ __('page_catalog.map.empty_action') }}</x-ui.button>
                    </x-slot:actions>
                </x-ui.empty-state>
            @elseif ($hasResults)
                <div
                    class="flex flex-col gap-8 transition-opacity duration-(--duration-fast) ease-out"
                    wire:loading.delay.class="opacity-60"
                    wire:loading.delay.attr="aria-busy"
                    wire:target.except="loadMore"
                    data-catalog-results
                >
                    <ul role="list" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3" data-catalog-grid>
                        @foreach ($results->animals as $animal)
                            <li wire:key="animal-{{ $animal->id }}">
                                <x-site.animal-card
                                    :animal="$animal"
                                    :favorited="in_array($animal->id, $favoriteIds, true)"
                                    photo-sizes="(min-width: 1280px) 19rem, (min-width: 640px) 50vw, 100vw"
                                    :priority="$loop->first"
                                    :tone="$tones[$loop->index % 3]"
                                    heading-level="2"
                                />
                            </li>
                        @endforeach

                        @if (! $results->hasMore())
                            <li wire:key="catalog-end" data-catalog-end>
                                <x-ui.animal-card.cta
                                    :href="route('organizations.index')"
                                    :title="__('page_catalog.end.title')"
                                    :description="__('page_catalog.end.description')"
                                    :action="__('page_catalog.end.action')"
                                    heading-level="2"
                                />
                            </li>
                        @endif
                    </ul>
                </div>

                {{-- Segnaposto mentre arrivano gli altri animali: stessa forma delle card. --}}
                <div wire:loading.block wire:target="loadMore" data-catalog-loading>
                    <x-ui.skeleton :label="__('page_catalog.results.loading')" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach (range(1, 3) as $placeholder)
                            <x-ui.animal-card.skeleton bare :class="[1 => '', 2 => 'max-sm:hidden', 3 => 'max-xl:hidden'][$placeholder]" />
                        @endforeach
                    </x-ui.skeleton>
                </div>

                @if ($results->canAutoLoad())
                    {{-- Sentinella del caricamento continuo: una nuova a ogni pagina (wire:key),
                         così si riattiva anche se resta visibile dopo il caricamento. --}}
                    <div
                        wire:key="catalog-sentinel-{{ $results->lastLoadedPage }}"
                        wire:intersect.once.margin.600px="loadMore"
                        aria-hidden="true"
                        data-catalog-sentinel
                    ></div>
                @endif

                @if ($results->hasMore() || $results->previousPage() !== null)
                    <div class="flex flex-col items-center gap-4 text-center" data-catalog-more>
                        @if ($results->hasMore())
                            <p class="text-small text-fg-muted">{{ __('page_catalog.more.progress', ['shown' => \Illuminate\Support\Number::format($results->lastItem(), locale: app()->getLocale()), 'total' => \Illuminate\Support\Number::format($results->total, locale: app()->getLocale())]) }}</p>
                        @endif

                        @if ($results->canLoadMore())
                            <x-ui.button
                                size="lg"
                                icon="plus"
                                :href="$results->pageUrl($results->nextPage())"
                                wire:click.prevent="loadMore"
                                data-catalog-load-more
                            >{{ __('page_catalog.more.action') }}</x-ui.button>
                        @endif

                        <x-ui.pagination
                            :current="$results->firstPage"
                            :current-to="$results->lastLoadedPage"
                            :last="$results->lastPage()"
                            :url="$results->pageUrl(...)"
                            :label="__('page_catalog.pagination.label')"
                            class="w-full"
                            data-catalog-pagination
                        />
                    </div>
                @endif
            @elseif ($results->firstPage > $results->lastPage())
                <x-ui.empty-state
                    icon="paw-print"
                    block="peach"
                    :title="__('page_catalog.empty.past_title')"
                    :description="__('page_catalog.empty.past_description')"
                    data-catalog-empty="past"
                >
                    <x-slot:actions>
                        <x-ui.button :href="$results->pageUrl(1)" wire:navigate>{{ __('page_catalog.empty.past_action') }}</x-ui.button>
                    </x-slot:actions>
                </x-ui.empty-state>
            @elseif ($results->filters->isFiltered())
                <x-ui.empty-state
                    variant="search"
                    :title="__('page_catalog.empty.filtered_title')"
                    :description="__('page_catalog.empty.filtered_description')"
                    data-catalog-empty="filtered"
                >
                    <x-slot:actions>
                        <x-ui.button variant="secondary" icon="x" wire:click="resetFilters">{{ __('page_catalog.filters.reset') }}</x-ui.button>
                    </x-slot:actions>
                </x-ui.empty-state>
            @else
                <x-ui.empty-state
                    icon="paw-print"
                    block="mint"
                    :title="__('page_catalog.empty.catalog_title')"
                    :description="__('page_catalog.empty.catalog_description')"
                    data-catalog-empty="catalog"
                >
                    <x-slot:actions>
                        <x-ui.button variant="secondary" :href="route('organizations.index')" wire:navigate.hover>{{ __('page_catalog.empty.catalog_action') }}</x-ui.button>
                    </x-slot:actions>
                </x-ui.empty-state>
            @endif
        </div>
    </div>

    {{-- Filtri sotto lg: drawer che su mobile è un bottom sheet. Fuori dalla barra (che da
         lg è nascosta), così il <dialog> resta visibile anche ridimensionando. --}}
    <x-ui.drawer name="catalog-filters" sheet :title="__('page_catalog.filters.title')" :description="__('page_catalog.filters.description')">
        <x-site.catalog.filters :results="$results" prefix="drawer" />

        <x-slot:footer>
            @if ($results->filters->hasFacetFilters())
                <x-ui.button variant="secondary" wire:click="resetFilters" data-catalog-drawer-reset>{{ __('page_catalog.filters.reset') }}</x-ui.button>
            @endif
            <x-ui.button x-on:click="close()" data-catalog-drawer-close>{{ trans_choice('page_catalog.filters.show_results', $results->total, ['count' => \Illuminate\Support\Number::format($results->total, locale: app()->getLocale())]) }}</x-ui.button>
        </x-slot:footer>
    </x-ui.drawer>
</div>
