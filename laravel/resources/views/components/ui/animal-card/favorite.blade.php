{{--
 | Bottone preferiti della card animale: cerchio da 44 px con un cuore, toggle con
 | aria-pressed. Il nome accessibile contiene il nome dell'animale («Salva Fido nei
 | preferiti»), così in una griglia i bottoni non hanno tutti lo stesso nome, e resta
 | fisso: lo stato lo dice aria-pressed (APG, toggle button), non un nome che cambia.
 | Lo stato non passa dal solo colore: il cuore pieno o vuoto e aria-pressed lo dicono.
 |
 | È l'equivalente statico di <livewire:favorite-button> (stesso nome accessibile), per le
 | pagine senza Livewire e per la styleguide. Gli attributi (wire:click, x-on:click,
 | x-bind:aria-pressed…) vanno sul <button>.
 |
 | - `name` (obbligatorio): nome dell'animale;
 | - `pressed`: già nei preferiti;
 | - `label`: sostituisce il nome accessibile calcolato;
 | - `guest`: il clic non salva (porta alla pagina di accesso): data-favorite-guest, così il
 |   cuore non fa il «pop» di un preferito aggiunto (resources/js/ui/motion.js).
 |
 | <x-slot:favorite>
 |     <x-ui.animal-card.favorite name="Fido" :pressed="$favorited" wire:click="toggle" />
 | </x-slot:favorite>
--}}
@props([
    'name',
    'pressed' => false,
    'label' => null,
    'guest' => false,
])

<button
    type="button"
    aria-pressed="{{ $pressed ? 'true' : 'false' }}"
    aria-label="{{ $label ?? __('ui_domain.animal_card.favorite', ['name' => $name]) }}"
    data-favorite
    @if ($guest) data-favorite-guest @endif
    {{ $attributes->class([
        'group/favorite inline-flex size-11 items-center justify-center rounded-full bg-canvas text-fg',
        'transition-[background-color,color,scale] duration-(--duration-fast) ease-pop motion-safe:hover:scale-110 motion-safe:active:scale-95',
        'aria-pressed:bg-primary aria-pressed:text-on-primary',
    ]) }}
>
    <x-ui.icon name="heart" class="group-aria-pressed/favorite:fill-current" />
</button>
