{{--
 | Menu a tendina, pattern WAI-ARIA APG "menu button". Adattato da Pines (Dropdown Menu)
 | e Sheaf UI (Dropdown), MIT.
 |
 | - trigger: un <x-ui.button> con aria-haspopup="menu", aria-expanded e aria-controls;
 |   `label` è il testo (o, con `icon-only`, il nome accessibile), `icon`, `variant`
 |   (default secondary) e `size` passano al bottone; gli attributi wire:* / x-* restano sul
 |   contenitore;
 | - menu role="menu" con voci <x-ui.dropdown.item> (role="menuitem", link o bottoni) e
 |   <x-ui.dropdown.separator>;
 | - tastiera: Invio / Spazio / ↓ aprono sulla prima voce, ↑ sull'ultima; nel menu ↑ ↓
 |   (circolari), Home / End, lettera iniziale; Esc chiude e torna al trigger; Tab chiude e
 |   prosegue; clic su una voce: esegue l'azione, chiude e torna al trigger;
 | - `align`: start (default) · end, rispetto al trigger; `position`: bottom (default) · top.
 |   x-anchor ribalta il menu se non c'è spazio. Il menu si apre nel top layer (popover).
 |
 | <x-ui.dropdown :label="__('…')" icon="ellipsis-vertical" icon-only align="end">
 |     <x-ui.dropdown.item icon="pencil" :href="route('…')">{{ __('…') }}</x-ui.dropdown.item>
 |     <x-ui.dropdown.separator />
 |     <x-ui.dropdown.item icon="trash-2" variant="danger" wire:click="delete">{{ __('…') }}</x-ui.dropdown.item>
 | </x-ui.dropdown>
--}}
@props([
    'label',
    'icon' => null,
    'iconOnly' => false,
    'variant' => 'secondary',
    'size' => 'md',
    'align' => 'start',
    'position' => 'bottom',
])

@php
    $placement = ($position === 'top' ? 'top' : 'bottom').'-'.($align === 'end' ? 'end' : 'start');
@endphp

<div
    x-data="uiDropdown"
    x-id="['ui-dropdown-trigger', 'ui-dropdown-menu']"
    x-on:keydown.escape="escape($event)"
    x-on:focusout="focusLeft($event)"
    x-on:click.outside="close(false)"
    data-dropdown
    {{ $attributes->class('relative inline-flex') }}
>
    <x-ui.button
        :variant="$variant"
        :size="$size"
        :icon="$icon"
        :icon-trailing="$iconOnly ? null : 'chevron-down'"
        :label="$iconOnly ? $label : null"
        x-ref="trigger"
        x-bind:id="$id('ui-dropdown-trigger')"
        x-bind:aria-controls="$id('ui-dropdown-menu')"
        x-bind:aria-expanded="open.toString()"
        aria-haspopup="menu"
        aria-expanded="false"
        x-on:click="toggle()"
        x-on:keydown.down.prevent="show('first')"
        x-on:keydown.up.prevent="show('last')"
        data-dropdown-trigger
    >@unless ($iconOnly){{ $label }}@endunless</x-ui.button>

    <div
        x-ref="menu"
        x-show="open"
        x-anchor.{{ $placement }}.offset.8.fixed="$refs.trigger"
        x-bind:id="$id('ui-dropdown-menu')"
        x-bind:aria-labelledby="$id('ui-dropdown-trigger')"
        x-on:keydown.down.prevent="focusItem('next')"
        x-on:keydown.up.prevent="focusItem('previous')"
        x-on:keydown.home.prevent="focusItem('first')"
        x-on:keydown.end.prevent="focusItem('last')"
        x-on:keydown.space="space($event)"
        x-on:keydown.tab="close(false)"
        x-on:keydown="typeahead($event)"
        x-on:click.capture="blockDisabled($event)"
        x-on:click="activated($event)"
        popover="manual"
        role="menu"
        tabindex="-1"
        style="display: none"
        data-dropdown-menu
        class="ui-layer inset-auto z-50 m-0 flex min-w-56 max-w-xs flex-col gap-0.5 rounded-card bg-surface-raised p-2 text-fg shadow-overlay transition duration-(--duration-fast) ease-out starting:open:scale-95 starting:open:opacity-0"
    >
        {{ $slot }}
    </div>
</div>
