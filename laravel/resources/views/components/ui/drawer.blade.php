{{--
 | Pannello laterale (drawer / sheet): stesso meccanismo di <x-ui.dialog> (<dialog> nativo
 | con showModal(), focus trap, Esc, clic sul backdrop, ritorno del focus, scroll della
 | pagina bloccato), ma ancorato a un lato. Utile per il menu mobile e i filtri.
 | Adattato da Pines (Slide Over) e Sheaf UI (Sheet), MIT.
 |
 | - `side`: right (default) · left · bottom (bottom sheet, a tutta larghezza);
 | - `sheet`: sotto i 640 px un drawer laterale diventa un bottom sheet (filtri su mobile);
 | - apertura: slot `trigger`, $dispatch('open-drawer', 'nome') / 'close-drawer', oppure
 |   x-model / wire:model booleano, come il dialog; `close()` lo chiude dall'interno;
 | - `title` (h2, aria-labelledby) e `description` (aria-describedby); intestazione e
 |   `footer` restano fissi (data-sticky), il contenuto scorre. Il <dialog> riserva la loro
 |   altezza con scroll-padding (misurata in uiDialog), così l'elemento con il focus non
 |   finisce sotto (WCAG 2.4.11);
 | - contenitore Alpine, trigger e <dialog> stanno in <x-ui.dialog.shell>, comune al
 |   dialog; i toast lanciati con il drawer aperto compaiono sopra di lui, senza coprirne il
 |   footer (dal lato opposto al pannello, o in alto su mobile e per il bottom sheet);
 | - stondato (rounded-panel) solo sul lato interno.
 |
 | <x-ui.drawer name="filters" sheet :title="__('…')">
 |     <x-slot:trigger><x-ui.button variant="secondary" icon="sliders-horizontal">{{ __('…') }}</x-ui.button></x-slot:trigger>
 |     …
 |     <x-slot:footer><x-ui.button x-on:click="close()">{{ __('…') }}</x-ui.button></x-slot:footer>
 | </x-ui.drawer>
--}}
@props([
    'name' => null,
    'title',
    'description' => null,
    'side' => 'right',
    'sheet' => false,
    'dismissible' => true,
    'icon' => null,
    'open' => false,
])

@php
    $side = in_array($side, ['right', 'left', 'bottom'], true) ? $side : 'right';

    // Geometria per lato. Il <dialog> modale ha max-width/max-height del browser (un
    // margine attorno): i drawer laterali la tolgono in altezza, il bottom sheet in larghezza.
    $sides = [
        'right' => 'ml-auto rounded-l-panel',
        'left' => 'mr-auto rounded-r-panel',
        'bottom' => 'mt-auto w-full max-w-none rounded-t-panel',
    ];

    $geometry = match (true) {
        $side === 'bottom' => $sides['bottom'],
        $sheet => 'mt-auto w-full max-w-none rounded-t-panel sm:mt-0 sm:h-dvh sm:max-h-none sm:w-sm sm:rounded-none '.($side === 'right' ? 'sm:ml-auto sm:rounded-l-panel' : 'sm:mr-auto sm:rounded-r-panel'),
        default => 'h-dvh max-h-none w-sm '.$sides[$side],
    };

    $model = $attributes->whereStartsWith(['wire:model', 'x-model']);
    $options = [
        'name' => $name,
        'dismissible' => (bool) $dismissible,
        'open' => (bool) $open,
    ];
@endphp

<x-ui.dialog.shell
    kind="drawer"
    :options="$options"
    :model="$model"
    :trigger="$trigger ?? null"
    :described="(bool) $description"
    :toasts="'drawer-'.$side"
    :attributes="$attributes->whereDoesntStartWith(['wire:model', 'x-model'])
        ->class(['ui-overlay ui-layer ui-drawer m-0 bg-surface-raised text-fg shadow-overlay', $geometry])
        ->merge(['data-side' => $side, 'data-sheet' => $sheet && $side !== 'bottom', 'data-drawer' => $name])"
>
    <div class="flex min-h-full flex-col">
        <x-ui.dialog.header
            :title="$title"
            :description="$description"
            :icon="$icon"
            :close-label="__('ui_overlays.drawer.close')"
            class="sticky top-0 z-10 bg-surface-raised p-6 pb-4"
            data-sticky="top"
        />

        <div class="flex flex-1 flex-col gap-4 px-6 pb-6" data-drawer-body>{{ $slot }}</div>

        @isset($footer)
            <footer class="sticky bottom-0 flex flex-wrap gap-3 border-t-2 border-line bg-surface-raised p-6 pt-4" data-sticky="bottom" data-drawer-footer>{{ $footer }}</footer>
        @endisset
    </div>
</x-ui.dialog.shell>
