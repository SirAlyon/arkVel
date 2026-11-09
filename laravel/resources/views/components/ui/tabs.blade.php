{{--
 | Schede (pattern WAI-ARIA APG "Tabs", attivazione automatica). Adattato da Pines/Sheaf UI (MIT).
 |
 | - `id` (obbligatorio): prefisso stabile degli id di schede e pannelli (aria-controls /
 |   aria-labelledby); deve essere unico nella pagina;
 | - `selected` (obbligatorio): nome della scheda attiva al caricamento. Lo stato iniziale è
 |   renderizzato dal server (aria-selected, tabindex, pannelli `hidden`): nessun salto quando
 |   parte Alpine. Se è null, Alpine attiva la prima scheda;
 | - tastiera: frecce sinistra/destra (circolari), Home/End; la scheda col focus si attiva
 |   subito (roving tabindex: solo la scheda attiva è nell'ordine di tabulazione);
 | - Livewire: `wire:model` (o `x-model`) sul componente sincronizza la scheda attiva
 |   (x-modelable). Passare anche `:selected` con lo stesso valore, per il primo render.
 |
 | Sottocomponenti: x-ui.tabs.list (tablist, con `label`), x-ui.tabs.tab (`name`, `icon`),
 | x-ui.tabs.panel (`name`, `focusable`). Leggono `id` e `selected` dal padre (@aware).
 | JS: Alpine.data('uiTabs') in resources/js/ui/content.js.
 |
 | <x-ui.tabs id="animal" :selected="$tab" wire:model.live="tab">
 |     <x-ui.tabs.list :label="__('…')">
 |         <x-ui.tabs.tab name="info" icon="info">{{ __('…') }}</x-ui.tabs.tab>
 |         <x-ui.tabs.tab name="health">{{ __('…') }}</x-ui.tabs.tab>
 |     </x-ui.tabs.list>
 |     <x-ui.tabs.panel name="info">…</x-ui.tabs.panel>
 |     <x-ui.tabs.panel name="health">…</x-ui.tabs.panel>
 | </x-ui.tabs>
--}}
@props([
    'id',
    'selected',
])

<div
    x-data="uiTabs(@js($selected))"
    x-modelable="selected"
    data-ui-tabs="{{ $id }}"
    {{ $attributes->class('flex min-w-0 flex-col gap-4') }}
>
    {{ $slot }}
</div>
