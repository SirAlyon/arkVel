{{--
 | Tooltip: breve descrizione aggiuntiva di un controllo (role="tooltip", collegato al
 | trigger con aria-describedby). Adattato da Pines (Tooltip) e Sheaf UI (Tooltip), MIT.
 |
 | - lo slot è il trigger: un solo elemento focalizzabile (di solito un <x-ui.button>);
 | - compare al passaggio del mouse (dopo un breve ritardo) e al focus da tastiera;
 | - resta visibile se il puntatore ci passa sopra; Esc lo nasconde senza spostare il
 |   focus (WCAG 1.4.13);
 | - `text`: il testo del tooltip; `position`: top (default) · bottom · left · right
 |   (x-anchor lo ribalta se non c'è spazio);
 | - mai per informazioni essenziali né per contenuti interattivi: non è raggiungibile su
 |   touch. Per un bottone solo icona il nome resta la `label` del bottone: il tooltip è la
 |   descrizione in più.
 |
 | <x-ui.tooltip :text="__('…')">
 |     <x-ui.button variant="ghost" icon="share-2" :label="__('…')" />
 | </x-ui.tooltip>
--}}
@props([
    'text',
    'position' => 'top',
])

@php
    $position = in_array($position, ['top', 'bottom', 'left', 'right'], true) ? $position : 'top';
@endphp

<span
    x-data="uiTooltip"
    x-id="['ui-tooltip']"
    x-on:mouseenter="pointer(true)"
    x-on:mouseleave="pointer(false)"
    x-on:focusin="focus(true)"
    x-on:focusout="focus(false)"
    x-on:keydown.escape="dismiss($event)"
    x-on:keydown.escape.window="dismiss()"
    data-tooltip
    {{ $attributes->class('relative inline-flex') }}
>
    {{ $slot }}
    <span
        x-ref="tooltip"
        x-show="visible"
        x-anchor.{{ $position }}.offset.8.fixed="trigger"
        x-bind:id="$id('ui-tooltip')"
        popover="manual"
        role="tooltip"
        style="display: none"
        data-tooltip-content
        class="inset-auto z-50 m-0 max-w-xs rounded-icon bg-inverse px-3 py-2 text-caption font-semibold text-on-inverse shadow-overlay transition duration-(--duration-fast) ease-out starting:open:opacity-0"
    >{{ $text }}</span>
</span>
