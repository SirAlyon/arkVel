{{-- Stesso toggle della card animale (x-ui.animal-card.favorite): nome fisso con il nome
     dell'animale, stato in aria-pressed. --}}
<div>
    <x-ui.animal-card.favorite :name="$animalName" :pressed="$favorited" wire:click="toggle" />
</div>
