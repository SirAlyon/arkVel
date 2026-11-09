{{--
 | Card di un animale del sito pubblico a partire dal Model: calcola i dati e li passa a
 | x-ui.animal-card (props primitive), così home, catalogo, pagina dell'ente e scheda
 | (animali simili) mostrano la stessa card con gli stessi dati.
 |
 | - foto: la prima della galleria (`photos`), altrimenti l'avatar (medialibrary, UI-4);
 | - badge: specie, sesso, età (App\Support\Site\AnimalAge) e taglia, tradotti;
 | - località: quella pubblica dell'ente (App\Support\Site\PublicLocation, «città, regione»);
 | - link alla scheda (`animals.show`) e bottone preferiti (<livewire:favorite-button>).
 |
 | Props:
 | - `animal` (obbligatorio): con `media` e `organization` già caricati (eager loading),
 |   altrimenti ogni card fa le sue query;
 | - `favorited`: stato del preferito già calcolato dalla pagina per tutta la griglia
 |   (App\Actions\Animals\FindFavoritedAnimalIds, una query): evita una query per card.
 |   null (default) = lo legge il bottone;
 | - `priority`: immagine LCP (prima card sopra la piega), niente lazy loading;
 | - `heading-level` (default 3), `tone` (sun · peach · mint), `photo-sizes`: come in
 |   x-ui.animal-card;
 | - `with-location` (default true): false dove la località è già nel contesto (la
 |   pagina di un ente elenca solo i suoi animali);
 | - `photo-transition` (default true): la foto ha il nome di View Transition dell'animale
 |   (App\Support\Site\AnimalPhotoTransition) e con wire:navigate vola nella galleria
 |   della scheda. Il nome dev'essere unico nella pagina: false se lo stesso animale
 |   compare due volte (es. in un riquadro o popup oltre che nella griglia);
 | - `reveal` (default true): comparsa allo scroll, come in x-ui.animal-card.
 |
 | <x-site.animal-card
 |     :animal="$animal"
 |     :favorited="in_array($animal->id, $favoriteIds, true)"
 |     :priority="$loop->first"
 |     :tone="['sun', 'peach', 'mint'][$loop->index % 3]"
 |     heading-level="2"
 | />
--}}
@props([
    'animal',
    'favorited' => null,
    'priority' => false,
    'headingLevel' => 3,
    'tone' => 'sun',
    'photoSizes' => '(min-width: 1024px) 25vw, (min-width: 640px) 50vw, 100vw',
    'withLocation' => true,
    'photoTransition' => true,
    'reveal' => true,
])

@php
    /** @var \App\Models\Animal $animal */
@endphp

<x-ui.animal-card
    :name="$animal->name"
    :href="route('animals.show', $animal)"
    :media="$animal->getFirstMedia('photos') ?? $animal->getFirstMedia('avatar')"
    :photo-sizes="$photoSizes"
    :priority="$priority"
    :tone="$tone"
    :species="$animal->species->getLabel()"
    :sex="$animal->sex->getLabel()"
    :age="\App\Support\Site\AnimalAge::of($animal)"
    :size="$animal->size?->getLabel()"
    :location="$withLocation ? \App\Support\Site\PublicLocation::of($animal->organization) : null"
    :featured="$animal->featured"
    :heading-level="$headingLevel"
    :reveal="$reveal"
    :transition-name="$photoTransition ? \App\Support\Site\AnimalPhotoTransition::name($animal) : null"
    {{ $attributes }}
>
    <x-slot:favorite>
        <livewire:favorite-button :animal="$animal" :favorited="$favorited" :key="'fav-'.$animal->id" />
    </x-slot:favorite>
</x-ui.animal-card>
