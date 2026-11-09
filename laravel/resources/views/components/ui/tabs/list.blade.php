{{--
 | Elenco delle schede di <x-ui.tabs> (role="tablist"): pill su superficie, come la nav della
 | styleguide. Scorre in orizzontale quando non ci sta (su mobile), senza far scorrere la
 | pagina; la scheda attiva (e col focus) viene portata in vista nel contenitore, senza
 | far scorrere la pagina (revealTab in resources/js/ui/content.js). Il padding verticale lascia spazio
 | all'anello di focus, che altrimenti verrebbe tagliato dall'area scorrevole.
 |
 | - `label`: nome accessibile dell'elenco (aria-label). In alternativa passare
 |   `aria-labelledby` con l'id di un titolo visibile.
 |
 | <x-ui.tabs.list :label="__('…')">…</x-ui.tabs.list>
--}}
@props([
    'label' => null,
])

<div class="-mx-1 max-w-full overflow-x-auto px-1 py-1.5" data-tabs-scroller>
    <div
        role="tablist"
        @if ($label) aria-label="{{ $label }}" @endif
        x-on:keydown="onKeydown($event)"
        {{ $attributes->class('flex w-max gap-1 rounded-full bg-surface p-1') }}
    >
        {{ $slot }}
    </div>
</div>
