{{--
 | Regioni live e lista dei toast (uso interno), condivise da <x-ui.toaster> e
 | <x-ui.toaster.outlet>. Usa il dato Alpine del genitore (uiToaster o uiToasterOutlet,
 | resources/js/ui/overlays.js): `speaks` dice se questa è la regione attiva (le altre
 | tacciono e non disegnano i toast), la lista ha x-ref="toastList" e ogni toast si chiude
 | con closeToast(); Esc su un toast (non danger) lo chiude come il suo bottone (escapeToast()).
 | Gli attributi (classi, popover…) vanno sulla lista.
--}}
<div role="status" class="sr-only" x-text="speaks ? $store.toasts.polite : ''" data-toaster-status></div>
<div role="alert" class="sr-only" x-text="speaks ? $store.toasts.assertive : ''" data-toaster-alert></div>

<ol
    x-ref="toastList"
    x-on:mouseenter="$store.toasts.pause()"
    x-on:mouseleave="pointerLeft()"
    x-on:focusin="entered($event)"
    x-on:focusout="left($event)"
    x-on:keydown.escape="escapeToast($event)"
    data-toaster-list
    {{ $attributes->class('flex-col gap-3') }}
>
    <template x-for="toast in (speaks ? $store.toasts.items : [])" x-bind:key="toast.id">
        <li class="pointer-events-auto">
            <x-ui.toast />
        </li>
    </template>
</ol>
