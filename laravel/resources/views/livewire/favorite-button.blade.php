{{-- Stesso toggle della card animale (x-ui.animal-card.favorite): nome fisso con il nome
     dell'animale, stato in aria-pressed. Per l'ospite ($guest) il clic porta all'accesso. --}}
<div>
    <x-ui.animal-card.favorite :name="$animalName" :pressed="$favorited" :guest="$guest" wire:click="toggle" />
</div>
