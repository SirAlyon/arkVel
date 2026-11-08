{{--
 | Combobox con ricerca: pattern WAI-ARIA APG "combobox con listbox popup" e
 | autocompletamento a lista (https://www.w3.org/WAI/ARIA/apg/patterns/combobox/).
 | Adattato da Pines (Combobox) e Sheaf UI (Combobox), MIT.
 |
 | - l'<input role="combobox"> filtra le opzioni mentre si scrive (senza accenti, senza
 |   maiuscole); la scelta resta quella confermata finché non se ne conferma un'altra;
 | - tastiera: ↓ / ↑ aprono e muovono l'opzione attiva (circolari, Alt+↓ apre e basta),
 |   Home / End vanno alla prima / ultima opzione quando una è attiva, Invio sceglie,
 |   Esc chiude (il secondo Esc svuota), Tab chiude e conferma;
 | - l'opzione scelta ha aria-selected e l'icona check (non solo il colore); quella attiva
 |   (aria-activedescendant) ha l'anello del focus;
 | - "Nessun risultato" visibile e conteggio dei risultati in una live region polite;
 | - bottone "Svuota il campo" (nome tradotto) quando c'è un testo o una scelta;
 | - il popup che scorre ha tabindex="0" (regola axe scrollable-region-focusable): in pratica
 |   non si raggiunge con Tab, perché Tab chiude la lista prima di spostare il focus.
 |
 | Props:
 | - `options`: array valore => etichetta, oppure valore => ['label' => …, 'description' => …];
 | - `value`: valore scelto all'inizio (con wire:model / x-model lo decide il modello);
 | - `icon`: icona Lucide decorativa all'inizio (es. "search");
 | - `search`: nome di una proprietà Livewire per la ricerca lato server (vedi sotto);
 | - `invalid`: forza lo stato di errore (di norma lo decide x-ui.field).
 |
 | Valore: x-modelable sul contenitore, quindi wire:model / x-model (e i modificatori) vanno
 | sul componente; con `name` c'è anche un <input type="hidden"> per i form classici.
 | `class` va sul contenitore; placeholder, disabled, required e gli altri attributi vanno
 | sull'<input>. Disabled ha il bordo `line` (sotto il 3:1): voluto, WCAG 1.4.11 esclude i
 | componenti inattivi.
 | Dentro x-ui.field eredita id, name, required, aria-describedby e aria-invalid (stesse
 | regole di x-ui.input, in App\Support\Ui\ResolvedControl): id, name e required propri non
 | sono ammessi. Da solo serve un aria-label; l'errore si legge da $errors con il name o
 | con la chiave di wire:model. L'id da solo: `id`, altrimenti `combobox-<name>`,
 | altrimenti un hash stabile di wire:model / search / aria-label (mai delle opzioni):
 | due combobox senza id né name nella stessa pagina vogliono un aria-label diverso.
 |
 | <x-ui.field name="breed" :label="__('…')">
 |     <x-ui.combobox wire:model="breed" :options="$breeds" :placeholder="__('…')" />
 | </x-ui.field>
 |
 | Ricerca lato server: con `search="query"` il componente non filtra da solo, ma scrive il
 | testo nella proprietà Livewire `query` ($wire.set, con un ritardo di 300 ms); il
 | componente Livewire ricalcola `$options` e la lista si aggiorna (il conteggio viene
 | riannunciato). Il valore scelto resta legato con wire:model:
 |
 | <x-ui.combobox wire:model="shelterId" search="shelterQuery" :options="$this->shelters" icon="search" :aria-label="__('…')" />
--}}
@use('App\Support\Ui\ResolvedControl')

@aware(['uiField' => null])

@props([
    'options' => [],
    'value' => null,
    'icon' => null,
    'search' => null,
    'invalid' => null,
])

@php
    // Id, name, obbligo ed errore: dal field se c'è, altrimenti propri (ResolvedControl).
    // Il seme dell'id è stabile (wire:model, search, aria-label), mai le opzioni: con la
    // ricerca lato server cambiano a ogni risposta e cambierebbero l'id dell'input
    // focalizzato, aria-controls e gli id delle opzioni.
    $control = ResolvedControl::resolve($uiField, $attributes, $errors ?? null, $invalid, prefix: 'combobox', seed: $search);
    $id = $control->id;
    $name = $control->name;
    $invalid = $control->invalid;
    $describedBy = $control->describedBy();

    $items = [];
    foreach ($options as $optionValue => $option) {
        $items[] = [
            'value' => (string) $optionValue,
            'label' => (string) (is_array($option) ? ($option['label'] ?? $optionValue) : $option),
            'description' => is_array($option) && filled($option['description'] ?? null) ? (string) $option['description'] : null,
        ];
    }

    $value = $value !== null ? (string) $value : '';
    $selectedLabel = collect($items)->firstWhere('value', $value)['label'] ?? '';
    $listboxId = $id.'-listbox';
    $listLabel = $uiField?->label ?? $attributes->get('aria-label') ?? __('ui_pickers.combobox.options');
    $disabled = (bool) $attributes->get('disabled', false);

    $config = [
        'value' => $value,
        'label' => $selectedLabel,
        'search' => $search,
        'messages' => [
            'none' => __('ui_pickers.combobox.results_none'),
            'one' => __('ui_pickers.combobox.results_one'),
            'many' => __('ui_pickers.combobox.results_many'),
        ],
    ];

    // wire:model* e x-model* vanno sul contenitore (x-modelable), insieme a class.
    $modelKeys = array_values(array_filter(array_keys($attributes->getAttributes()), static fn (string $key): bool => str_starts_with($key, 'wire:model') || str_starts_with($key, 'x-model')));

    $boxClasses = [
        'flex min-h-12 w-full min-w-0 items-center rounded-full border-2 bg-canvas text-fg',
        'transition-colors duration-(--duration-fast) ease-out',
        'has-[input:focus-visible]:outline-3 has-[input:focus-visible]:outline-offset-2',
        'has-[input:disabled]:cursor-not-allowed has-[input:disabled]:border-line has-[input:disabled]:bg-surface',
        $invalid ? 'border-danger' : 'border-line-strong',
        'hover:border-fg' => ! $invalid && ! $disabled,
    ];

    $inputClasses = [
        'h-11 w-full min-w-0 flex-1 rounded-full bg-transparent text-body text-fg placeholder:text-fg-muted',
        'focus-visible:outline-hidden disabled:cursor-not-allowed disabled:text-fg-muted',
        filled($icon) ? 'ps-2' : 'ps-5',
    ];

    $buttonClasses = 'inline-flex size-11 shrink-0 items-center justify-center rounded-full text-fg transition-colors duration-(--duration-fast) ease-out hover:bg-surface disabled:cursor-not-allowed disabled:text-fg-muted disabled:hover:bg-transparent';
@endphp

<div
    x-data="uiCombobox(@js($config))"
    x-modelable="value"
    x-on:keydown.escape="escape($event)"
    x-on:focusout="focusLeft($event)"
    x-on:click.outside="open && close()"
    data-ui-combobox
    {{ $attributes->only(['class', ...$modelKeys])->class('relative w-full min-w-0') }}
>
    @if ($name !== null)
        <input type="hidden" name="{{ $name }}" value="{{ $value }}" x-bind:value="value" data-combobox-value />
    @endif

    <div x-ref="box" @class($boxClasses)>
        @if (filled($icon))
            <span class="inline-flex shrink-0 ps-4 text-fg-muted"><x-ui.icon :name="$icon" /></span>
        @endif

        <input
            x-ref="input"
            type="text"
            id="{{ $id }}"
            role="combobox"
            value="{{ $selectedLabel }}"
            x-model="query"
            x-on:input="typed()"
            x-on:click="show()"
            x-on:keydown.down.prevent="move('next', $event)"
            x-on:keydown.up.prevent="move('previous', $event)"
            x-on:keydown.home="edge('first', $event)"
            x-on:keydown.end="edge('last', $event)"
            x-on:keydown.enter="enter($event)"
            x-on:keydown.tab="close()"
            x-bind:aria-expanded="open.toString()"
            x-bind:aria-activedescendant="activeId"
            aria-expanded="false"
            aria-controls="{{ $listboxId }}"
            aria-autocomplete="list"
            autocomplete="off"
            @if ($describedBy !== null) aria-describedby="{{ $describedBy }}" @endif
            @if ($invalid) aria-invalid="true" @endif
            @required($control->required)
            {{ $attributes->except(['class', 'id', 'name', 'required', 'aria-describedby', ...$modelKeys])->class($inputClasses) }}
        />

        <button
            type="button"
            x-show="query !== '' || value !== ''"
            x-on:click="clear()"
            aria-label="{{ __('ui_pickers.combobox.clear') }}"
            @if ($selectedLabel === '') style="display: none" @endif
            @disabled($disabled)
            data-combobox-clear
            class="{{ $buttonClasses }}"
        >
            <x-ui.icon name="x" />
        </button>
        <button
            type="button"
            tabindex="-1"
            x-on:click="toggle()"
            x-bind:aria-expanded="open.toString()"
            aria-expanded="false"
            aria-controls="{{ $listboxId }}"
            aria-label="{{ __('ui_pickers.combobox.toggle') }}"
            @disabled($disabled)
            data-combobox-toggle
            class="{{ $buttonClasses }} me-0.5"
        >
            <x-ui.icon name="chevron-down" class="transition-transform duration-(--duration-fast) ease-out" x-bind:class="open && 'rotate-180'" />
        </button>
    </div>

    <div
        x-ref="popup"
        x-show="open"
        x-anchor.bottom-start.offset.8.fixed="$refs.box"
        x-on:mousedown.prevent
        popover="manual"
        tabindex="0"
        style="display: none"
        data-combobox-popup
        class="ui-picker-layer inset-auto z-50 m-0 max-h-80 overflow-y-auto rounded-card bg-surface-raised p-2 text-fg shadow-overlay transition duration-(--duration-fast) ease-out starting:open:scale-95 starting:open:opacity-0"
    >
        <ul
            x-ref="listbox"
            x-show="visibleIds.length > 0"
            id="{{ $listboxId }}"
            role="listbox"
            aria-label="{{ $listLabel }}"
            data-combobox-listbox
            class="flex flex-col gap-0.5"
        >
            @foreach ($items as $index => $item)
                <li
                    id="{{ $id }}-option-{{ $index }}"
                    role="option"
                    aria-selected="{{ $item['value'] === $value ? 'true' : 'false' }}"
                    x-show="isVisible($el)"
                    x-bind:aria-selected="isSelected($el).toString()"
                    x-bind:data-active="activeId === $el.id"
                    x-on:click="choose($el)"
                    data-value="{{ $item['value'] }}"
                    data-label="{{ $item['label'] }}"
                    data-search="{{ trim($item['label'].' '.$item['description']) }}"
                    @if ($search !== null) wire:key="{{ $id }}-option-{{ $item['value'] }}" @endif
                    class="group/option flex min-h-11 cursor-pointer items-center gap-3 rounded-media px-4 py-2 text-start transition-colors duration-(--duration-fast) ease-out hover:bg-surface data-active:bg-surface data-active:outline-3 data-active:-outline-offset-3 data-active:outline-focus"
                >
                    <span class="flex min-w-0 flex-1 flex-col">
                        <span class="font-semibold">{{ $item['label'] }}</span>
                        @if ($item['description'] !== null)
                            <span class="text-small text-fg-muted">{{ $item['description'] }}</span>
                        @endif
                    </span>
                    <x-ui.icon name="check" class="invisible group-aria-selected/option:visible" />
                </li>
            @endforeach
        </ul>
        <p x-show="visibleIds.length === 0" style="display: none" data-combobox-empty class="flex min-h-11 items-center gap-3 px-4 text-fg-muted">
            <x-ui.icon name="search-x" />
            <span>{{ __('ui_pickers.combobox.no_results') }}</span>
        </p>
    </div>

    <div role="status" class="sr-only" x-text="announcement" data-combobox-status></div>
</div>
