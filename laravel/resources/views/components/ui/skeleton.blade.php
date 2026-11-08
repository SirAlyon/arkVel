{{--
 | Segnaposto di caricamento: contenitore che annuncia l'attesa (role="status" con un testo
 | sr-only tradotto) e, nello slot, i blocchi decorativi <x-ui.skeleton.block> (ognuno
 | aria-hidden). Lo slot può contenere markup di struttura (card, righe) purché senza testo:
 | gli attributi sul contenitore (class) ne fanno il layout.
 |
 | Niente aria-busy qui: su una regione live sospende l'annuncio finché non torna false, e
 | lo skeleton non lo farebbe mai. aria-busy="true" va semmai sul contenitore che si sta
 | caricando (la lista, la griglia dei risultati), da togliere quando arrivano i dati.
 |
 | - `label`: il testo annunciato (default «Caricamento in corso…»), per esempio
 |   «Caricamento degli animali…».
 |
 | La pulsazione è lieve (opacità) e con prefers-reduced-motion si ferma: la regola
 | globale di app.css porta le animazioni a una sola iterazione di 0,01 ms.
 |
 | <x-ui.skeleton :label="__('…')" class="w-full max-w-sm">
 |     <x-ui.skeleton.block shape="media" />
 |     <x-ui.skeleton.block shape="heading" class="w-2/3" />
 |     <x-ui.skeleton.block />
 | </x-ui.skeleton>
--}}
@props([
    'label' => null,
])

<div role="status" data-skeleton {{ $attributes }}>
    <span class="sr-only">{{ $label ?? __('ui_content.skeleton.loading') }}</span>
    {{ $slot }}
</div>
