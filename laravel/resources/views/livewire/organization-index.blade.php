{{--
 | /enti (App\Livewire\OrganizationIndex): intestazione, filtri (nome, regione, tipo di ente:
 | in query string, modulo GET senza JavaScript), conteggio dei risultati annunciato (role="status"), griglia di
 | x-ui.organization-card, paginazione, stati vuoti (nessun ente / nessun risultato).
 | Gerarchia: h1 della pagina → h2 dei filtri e dei risultati → h3 delle card.
--}}
@php
    $typeOptions = [];
    foreach ($facets->types as $typeOption) {
        $typeOptions[$typeOption->value] = $typeOption->getLabel();
    }
@endphp

<div class="mx-auto flex w-full max-w-page flex-col gap-10 px-gutter py-section" data-organization-index>
    <header class="flex max-w-3xl flex-col gap-4">
        <p><x-ui.badge variant="highlight" icon="building-2" class="-rotate-2">{{ __('page_organizations.index.eyebrow') }}</x-ui.badge></p>
        <h1 class="text-h1 text-fg">{{ __('public.organizations.title') }}</h1>
        <p class="text-lead text-fg-muted">{{ __('public.organizations.subtitle') }}</p>
    </header>

    @if ($organizations->total() > 0 || $filters->isFiltered())
        <section aria-labelledby="organizations-filters-title" class="rounded-panel bg-surface p-panel" data-organization-filters>
            <h2 id="organizations-filters-title" class="sr-only">{{ __('page_organizations.index.filters.title') }}</h2>

            {{-- Con JavaScript ogni campo aggiorna i risultati (wire:model.live) e il modulo non
                 parte; senza, è un GET verso /enti con il bottone in noscript. --}}
            <form method="get" action="{{ route('organizations.index') }}" wire:submit="$refresh" class="flex flex-col gap-6" data-organization-filters-form>
                <div class="grid gap-4 md:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
                    <x-ui.field name="q" for="organizations-search" :label="__('page_organizations.index.filters.search')">
                        <x-ui.input
                            type="search"
                            icon="search"
                            wire:model.live.debounce.400ms="search"
                            :value="$search"
                            :placeholder="__('page_organizations.index.filters.search_placeholder')"
                            maxlength="{{ \App\Data\Organizations\OrganizationDirectoryFilters::MAX_SEARCH_LENGTH }}"
                            autocomplete="off"
                            enterkeyhint="search"
                        />
                    </x-ui.field>

                    <x-ui.field name="region" for="organizations-region" :label="__('page_organizations.index.filters.region')">
                        <x-ui.select
                            wire:model.live="region"
                            :value="$filters->region"
                            :options="array_combine($facets->regions, $facets->regions)"
                            :placeholder="__('page_organizations.index.filters.region_all')"
                        />
                    </x-ui.field>
                </div>

                @if (count($facets->types) > 1)
                    <div role="group" aria-labelledby="organizations-type-label" class="flex flex-col gap-3">
                        <p id="organizations-type-label" class="font-semibold text-fg">{{ __('page_organizations.index.filters.type') }}</p>
                        <ul role="list" class="flex flex-wrap gap-2">
                            <li>
                                <x-ui.tag :pressed="$filters->type === null" wire:click="$set('type', '')" data-organization-type="">
                                    {{ __('page_organizations.index.filters.type_all') }}
                                </x-ui.tag>
                            </li>
                            @foreach ($facets->types as $typeOption)
                                <li wire:key="type-{{ $typeOption->value }}">
                                    <x-ui.tag :pressed="$filters->type === $typeOption" wire:click="$set('type', '{{ $typeOption->value }}')" data-organization-type="{{ $typeOption->value }}">
                                        {{ $typeOption->getLabel() }}
                                    </x-ui.tag>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <noscript>
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-end">
                        @if (count($facets->types) > 1)
                            <x-ui.field name="type" for="organizations-type-noscript" :label="__('page_organizations.index.filters.type')" class="sm:w-72">
                                <x-ui.select
                                    :value="$filters->type?->value"
                                    :options="$typeOptions"
                                    :placeholder="__('page_organizations.index.filters.type_all')"
                                />
                            </x-ui.field>
                        @endif
                        <x-ui.button type="submit" icon="check">{{ __('page_organizations.index.filters.apply') }}</x-ui.button>
                    </div>
                </noscript>
            </form>
        </section>
    @endif

    <section id="organizations-results" aria-labelledby="organizations-results-title" class="flex scroll-mt-24 flex-col gap-8">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h2 id="organizations-results-title" class="sr-only">{{ __('page_organizations.index.results_title') }}</h2>
            <p role="status" class="text-lead font-semibold text-fg" data-organization-results-count>
                {{ trans_choice('page_organizations.index.results', $organizations->total(), ['count' => \Illuminate\Support\Number::format($organizations->total(), locale: app()->getLocale())]) }}
            </p>

            @if ($filters->isFiltered())
                <x-ui.button variant="ghost" size="sm" icon="rotate-ccw" wire:click="resetFilters" data-organization-reset>
                    {{ __('page_organizations.index.filters.reset') }}
                </x-ui.button>
            @endif
        </div>

        @if ($organizations->isNotEmpty())
            <ul
                role="list"
                class="grid grid-cols-1 gap-4 transition-opacity duration-(--duration-fast) ease-out sm:grid-cols-2 lg:grid-cols-3"
                wire:loading.delay.class="opacity-60"
                wire:target="search,region,type,resetFilters,gotoPage,previousPage,nextPage"
                data-organization-grid
            >
                @foreach ($organizations as $organization)
                    <li wire:key="org-{{ $organization->id }}">
                        <x-ui.organization-card
                            :name="$organization->name"
                            :href="route('organizations.show', $organization)"
                            :location="\App\Support\Site\PublicLocation::of($organization)"
                            :type="$organization->type->getLabel()"
                            verified
                            :available="$organization->available_animals_count"
                        />
                    </li>
                @endforeach
            </ul>

            <x-ui.pagination :paginator="$organizations" mode="livewire" scroll-to="organizations-results" />
        @elseif ($filters->isFiltered())
            <x-ui.empty-state
                variant="search"
                heading-level="3"
                :title="__('page_organizations.index.empty_filtered.title')"
                :description="__('page_organizations.index.empty_filtered.description')"
            >
                <x-slot:actions>
                    <x-ui.button variant="secondary" icon="rotate-ccw" wire:click="resetFilters">{{ __('page_organizations.index.filters.reset') }}</x-ui.button>
                </x-slot:actions>
            </x-ui.empty-state>
        @else
            <x-ui.empty-state
                icon="building-2"
                block="peach"
                heading-level="3"
                :title="__('page_organizations.index.empty.title')"
                :description="__('public.organizations.empty')"
            >
                <x-slot:actions>
                    <x-ui.button variant="secondary" :href="route('catalog')" wire:navigate.hover icon-trailing="arrow-right">{{ __('page_organizations.index.empty.catalog') }}</x-ui.button>
                </x-slot:actions>
            </x-ui.empty-state>
        @endif
    </section>
</div>
