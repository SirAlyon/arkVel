{{--
 | Segnaposto della card animale mentre la griglia carica (wire:loading, lazy). Stessa
 | forma della card (surface, padding 12 px, foto 4:5, nome, badge, luogo), quindi nessun
 | salto di layout quando arrivano i dati. I blocchi sono <x-ui.skeleton.block> decorativi.
 |
 | - da solo annuncia l'attesa: è avvolto in <x-ui.skeleton> (role="status" e un testo
 |   tradotto, default «Caricamento degli animali…», sostituibile con `label`);
 | - `bare`: solo la forma, aria-hidden, senza annuncio. Per una griglia di segnaposto:
 |   un solo <x-ui.skeleton> esterno che annuncia una volta, con dentro le card `bare`.
 |
 | Gli attributi (class…) vanno sulla card.
 |
 | <x-ui.animal-card.skeleton />
 | <x-ui.skeleton :label="__('…')" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
 |     @foreach (range(1, 4) as $i)
 |         <x-ui.animal-card.skeleton bare />
 |     @endforeach
 | </x-ui.skeleton>
--}}
@props([
    'label' => null,
    'bare' => false,
])

@if ($bare)
    <div aria-hidden="true" data-animal-card-skeleton {{ $attributes->class('flex flex-col gap-3 rounded-card bg-surface p-3') }}>
        <div class="aspect-4/5">
            <x-ui.skeleton.block shape="media" class="size-full" />
        </div>
        <div class="flex flex-col gap-3 px-2 pb-2">
            <x-ui.skeleton.block shape="heading" class="w-1/2" />
            <div class="flex gap-1.5 py-1.5">
                <x-ui.skeleton.block class="w-16" />
                <x-ui.skeleton.block class="w-20" />
                <x-ui.skeleton.block class="w-14" />
            </div>
            <x-ui.skeleton.block class="w-2/3" />
        </div>
    </div>
@else
    <x-ui.skeleton :label="$label ?? __('ui_domain.animal_card.loading')">
        <x-ui.animal-card.skeleton bare :attributes="$attributes" />
    </x-ui.skeleton>
@endif
