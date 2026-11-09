{{--
 | Pannello dei filtri del catalogo: una faccetta per gruppo, con il numero di animali per
 | ogni scelta (conteggi disgiuntivi da SearchCatalog). Reso due volte nella pagina, nella
 | colonna laterale (da lg) e nel drawer (sotto lg): `prefix` rende unici gli id, e ogni
 | copia è un <form> suo, così i radio con lo stesso name non diventano un solo gruppo.
 |
 | - specie, sesso, taglia: fieldset + legend con x-ui.radio («Tutti» e i valori, anche a 0,
 |   così il pannello non cambia forma). Frecce per cambiare scelta, come da nativo;
 | - regione, ente: x-ui.select in x-ui.field (valori aperti, solo quelli con animali);
 | - con JavaScript ogni scelta aggiorna i risultati (wire:model.live); senza, il modulo è
 |   un GET verso /animali con un bottone «Applica i filtri» (noscript) e i campi nascosti
 |   di ricerca e ordinamento.
 |
 | <x-site.catalog.filters :results="$results" prefix="sidebar" />
--}}
@props([
    'results',
    'prefix',
])

@php
    /** @var \App\Support\Catalog\CatalogResults $results */
    $facetNames = array_map(static fn (\App\Enums\CatalogFacet $facet): string => $facet->value, \App\Enums\CatalogFacet::cases());
@endphp

<form
    method="get"
    action="{{ route('catalog') }}"
    wire:submit="$refresh"
    data-catalog-filters="{{ $prefix }}"
    {{ $attributes->class('flex flex-col gap-6') }}
>
    <x-site.catalog.hidden-state :parameters="$results->stateParameters()" :except="$facetNames" />

    {{-- Niente @continue nei @foreach: rompe le chiavi compilate di Livewire 4. --}}
    @foreach (array_filter($results->facets, static fn (\App\Support\Catalog\CatalogFacetGroup $group): bool => ! $group->isEmpty()) as $group)
        @php($facet = $group->facet->value)

        @if ($group->usesSelect())
            <x-ui.field :name="$facet" :for="$prefix.'-'.$facet" :label="$group->facet->label()" data-catalog-facet="{{ $facet }}">
                <x-ui.select
                    wire:model.live="{{ $facet }}"
                    :value="$group->selected"
                    :placeholder="__('page_catalog.filters.all_'.$facet)"
                    :options="collect($group->options)->mapWithKeys(fn (\App\Support\Catalog\CatalogFacetOption $option): array => [$option->value => __('page_catalog.filters.option', ['label' => $option->label, 'count' => \Illuminate\Support\Number::format($option->count, locale: app()->getLocale())])])->all()"
                />
            </x-ui.field>
        @else
            <fieldset class="flex min-w-0 flex-col" data-catalog-facet="{{ $facet }}">
                <legend class="mb-1 text-body font-semibold text-fg">{{ $group->facet->label() }}</legend>

                <x-ui.radio
                    :id="$prefix.'-'.$facet.'-all'"
                    :name="$facet"
                    value=""
                    :checked="$group->selected === null"
                    wire:model.live="{{ $facet }}"
                >{{ __('page_catalog.filters.all') }}</x-ui.radio>

                @foreach ($group->options as $option)
                    <x-ui.radio
                        :id="$prefix.'-'.$facet.'-'.$option->value"
                        :name="$facet"
                        :value="$option->value"
                        :checked="$option->selected"
                        wire:model.live="{{ $facet }}"
                    >{{ __('page_catalog.filters.option', ['label' => $option->label, 'count' => \Illuminate\Support\Number::format($option->count, locale: app()->getLocale())]) }}</x-ui.radio>
                @endforeach
            </fieldset>
        @endif
    @endforeach

    <noscript>
        <x-ui.button type="submit" icon="check" class="w-full">{{ __('page_catalog.filters.apply') }}</x-ui.button>
    </noscript>
</form>
