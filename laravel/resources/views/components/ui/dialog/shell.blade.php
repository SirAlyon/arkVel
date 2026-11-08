{{--
 | Contenitore comune di <x-ui.dialog> e <x-ui.drawer> (uso interno): il dato Alpine
 | `uiDialog` (resources/js/ui/overlays.js) con modello, eventi con nome e id di titolo e
 | descrizione, lo slot trigger, il <dialog> nativo e l'outlet dei toast
 | (<x-ui.toaster.outlet>), così i toast restano visibili e annunciati con il modale aperto.
 |
 | - `kind`: dialog · drawer, cioè gli eventi open-<kind> / close-<kind> e l'attributo
 |   data-<kind>-trigger;
 | - `options`: { name, dismissible, open } per uiDialog;
 | - `model`: gli attributi wire:model* / x-model* del componente, sul contenitore Alpine;
 | - `trigger`: lo slot trigger del componente;
 | - `described`: c'è una descrizione (aria-describedby);
 | - `toasts`: posizione dei toast sopra il modale (placement di x-ui.toaster.outlet);
 | - gli altri attributi (classi, role, data-*) vanno sul <dialog>.
--}}
@props([
    'kind' => 'dialog',
    'options' => [],
    'model' => null,
    'trigger' => null,
    'described' => false,
    'toasts' => 'dialog',
])

<div
    x-data="uiDialog(@js($options))"
    x-modelable="isOpen"
    x-id="['ui-dialog-title', 'ui-dialog-description']"
    x-on:open-{{ $kind }}.window="targets($event) && show()"
    x-on:close-{{ $kind }}.window="targets($event) && close()"
    class="contents"
    {{ $model }}
>
    @if ($trigger)
        <span class="contents" x-on:click="show()" data-{{ $kind }}-trigger>{{ $trigger }}</span>
    @endif

    <dialog
        x-ref="dialog"
        wire:ignore.self
        x-bind:aria-labelledby="$id('ui-dialog-title')"
        @if ($described) x-bind:aria-describedby="$id('ui-dialog-description')" @endif
        x-on:close="closed()"
        x-on:pointerdown="pointerDown($event)"
        x-on:click="clicked($event)"
        {{ $attributes }}
    >
        {{ $slot }}

        <x-ui.toaster.outlet :placement="$toasts" />
    </dialog>
</div>
