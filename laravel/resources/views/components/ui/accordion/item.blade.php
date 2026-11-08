{{--
 | Elemento di <x-ui.accordion>: card su superficie con un bottone dentro un'intestazione
 | (livello dal padre, `heading-level`) e una region (aria-labelledby → il bottone).
 |
 | - `title`: testo del bottone (stringa o slot `title`);
 | - `name`: identifica l'elemento (id e stato); se manca è derivato dal titolo;
 | - `open`: aperto al caricamento. Nella modalità singola vale solo il primo `open`.
 |
 | Lo stato non passa dal solo colore: aria-expanded, e a vista la freccia ruota e il suo
 | cerchio diventa pieno.
 |
 | <x-ui.accordion.item name="cost" :title="__('…')" open>
 |     <p>…</p>
 | </x-ui.accordion.item>
--}}
@aware([
    'headingLevel' => 3,
    'id' => null,
])

@props([
    'title',
    'name' => null,
    'open' => false,
])

@php
    $level = min(6, max(2, (int) $headingLevel));
    $name = (string) ($name ?? 'item-'.substr(md5(strip_tags((string) $title)), 0, 8));
    $prefix = ($id ? $id : 'accordion').'-'.$name;
    $open = (bool) $open;
@endphp

<div data-accordion-item="{{ $name }}" {{ $attributes->class('rounded-card bg-surface') }}>
    <h{{ $level }} class="text-h5">
        <button
            type="button"
            id="{{ $prefix }}-trigger"
            aria-controls="{{ $prefix }}-panel"
            aria-expanded="{{ $open ? 'true' : 'false' }}"
            data-accordion-trigger="{{ $name }}"
            x-on:click="toggle(@js($name))"
            x-bind:aria-expanded="isOpen(@js($name)).toString()"
            class="group/trigger flex min-h-16 w-full items-center justify-between gap-4 rounded-card px-6 py-4 text-start text-fg"
        >
            <span class="underline-offset-4 decoration-2 group-hover/trigger:underline">{{ $title }}</span>
            <span
                class="inline-flex size-10 shrink-0 items-center justify-center rounded-full bg-canvas text-fg transition-colors duration-(--duration-base) ease-out group-aria-expanded/trigger:bg-primary group-aria-expanded/trigger:text-on-primary"
            >
                <x-ui.icon name="chevron-down" class="transition-transform duration-(--duration-base) ease-out group-aria-expanded/trigger:rotate-180" />
            </span>
        </button>
    </h{{ $level }}>

    <div
        id="{{ $prefix }}-panel"
        role="region"
        aria-labelledby="{{ $prefix }}-trigger"
        data-accordion-panel="{{ $name }}"
        @unless ($open) hidden style="height: 0px; overflow: hidden;" @endunless
        x-show="isOpen(@js($name))"
        x-collapse
        class="duration-(--duration-base)! ease-out!"
    >
        <div class="flex flex-col gap-3 px-6 pb-6 text-fg">
            {{ $slot }}
        </div>
    </div>
</div>
