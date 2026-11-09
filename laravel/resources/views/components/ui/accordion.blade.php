{{--
 | Accordion (pattern WAI-ARIA APG "Accordion"), adatto alle FAQ. Adattato da Pines/Sheaf UI (MIT).
 |
 | - `multiple` (default false): più elementi aperti insieme; senza, aprirne uno chiude
 |   gli altri (si possono comunque chiudere tutti);
 | - `heading-level` (default 3): livello delle intestazioni che contengono i bottoni
 |   (h2…h6), da scegliere in base alla struttura della pagina;
 | - `id`: prefisso degli id degli elementi (consigliato se in pagina ci sono più accordion
 |   con gli stessi `name`);
 | - tastiera: Tab tra i bottoni, Invio/Spazio apre e chiude, frecce su/giù (circolari) e
 |   Home/End spostano il focus tra le intestazioni.
 |
 | Lo stato iniziale (`open` sugli elementi) è renderizzato dal server; x-collapse anima
 | apertura e chiusura con le durate dei token (zero con prefers-reduced-motion).
 | JS: Alpine.data('uiAccordion') in resources/js/ui/content.js.
 |
 | <x-ui.accordion heading-level="2">
 |     <x-ui.accordion.item name="cost" :title="__('…')" open>…</x-ui.accordion.item>
 |     <x-ui.accordion.item name="time" :title="__('…')">…</x-ui.accordion.item>
 | </x-ui.accordion>
--}}
@props([
    'multiple' => false,
    'headingLevel' => 3,
    'id' => null,
])

<div
    x-data="uiAccordion(@js((bool) $multiple))"
    x-on:keydown="onKeydown($event)"
    data-ui-accordion
    @if ($id) id="{{ $id }}" @endif
    {{ $attributes->class('flex flex-col gap-3') }}
>
    {{ $slot }}
</div>
