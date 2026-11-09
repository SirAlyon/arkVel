{{--
 | Area di testo su più righe: rounded-media, bordo `line-strong`, focus con l'anello
 | globale sul <textarea>.
 |
 | - `rows`: righe iniziali (default 4);
 | - `autoresize`: cresce con il contenuto (field-sizing: content, dove supportato;
 |   altrove resta ridimensionabile a mano);
 | - `counter`: contatore di caratteri, richiede `maxlength`. Il numero visibile è
 |   nascosto agli screen reader; il limite fa parte della descrizione del campo e,
 |   vicino al limite, una live region "polite" annuncia i caratteri rimasti (con una
 |   pausa dopo la digitazione, per non interrompere);
 | - `invalid`: forza lo stato di errore (di norma lo decide x-ui.field);
 | - il valore iniziale va nello slot (o con wire:model);
 | - disabled / readonly: attributi nativi. Readonly ha il bordo tratteggiato e il fondo
 |   `surface`; disabled ha il bordo `line`, sotto il 3:1: è voluto, WCAG 1.4.11 esclude
 |   i componenti inattivi.
 |
 | Dentro x-ui.field eredita id, name, required, aria-describedby e aria-invalid (vedi
 | x-ui.field e App\Support\Ui\ResolvedControl): id, name e required propri non sono
 | ammessi (vanno sul field). Da solo serve un nome accessibile (aria-label o
 | aria-labelledby); lo stato di errore si legge da $errors con il name o, se manca, con
 | la chiave di wire:model.
 | `class` va sul contenitore; gli altri attributi sul <textarea>.
 |
 | <x-ui.field name="message" :label="__('…')">
 |     <x-ui.textarea wire:model="message" maxlength="500" counter autoresize />
 | </x-ui.field>
--}}
@use('App\Support\Ui\ResolvedControl')

@aware(['uiField' => null])

@props([
    'rows' => 4,
    'autoresize' => false,
    'counter' => false,
    'invalid' => null,
])

@php
    $maxlength = (int) $attributes->get('maxlength', 0);
    $counter = $counter && $maxlength > 0;

    // Id, name, obbligo ed errore: dal field se c'è, altrimenti propri (ResolvedControl).
    // Il contatore ha bisogno di un id anche fuori da un field (per la descrizione).
    $control = ResolvedControl::resolve($uiField, $attributes, $errors ?? null, $invalid, prefix: $counter ? 'textarea' : null, seed: serialize($attributes->except('class')->getAttributes()));
    $id = $control->id;
    $invalid = $control->invalid;
    $limitId = $counter ? $id.'-limit' : null;
    $describedBy = $control->describedBy($limitId);

    $length = mb_strlen(html_entity_decode(trim((string) $slot), ENT_QUOTES | ENT_HTML5));
    $disabled = (bool) $attributes->get('disabled', false);
    $readonly = (bool) $attributes->get('readonly', false);

    $classes = [
        'block w-full min-w-0 rounded-media border-2 px-5 py-3 text-body text-fg placeholder:text-fg-muted',
        'transition-colors duration-(--duration-fast) ease-out',
        'disabled:cursor-not-allowed disabled:border-line disabled:bg-surface disabled:text-fg-muted',
        $invalid ? 'border-danger' : 'border-line-strong',
        'hover:border-fg' => ! $invalid && ! $disabled && ! $readonly,
        $readonly ? 'border-dashed bg-surface' : 'bg-canvas',
        $autoresize ? 'field-sizing-content min-h-32 max-h-96 resize-y' : 'resize-y',
    ];

    $messages = [
        'remaining' => __('ui_forms.textarea.remaining'),
        'remainingOne' => __('ui_forms.textarea.remaining_one'),
        'limitReached' => __('ui_forms.textarea.limit_reached', ['max' => $maxlength]),
    ];
@endphp

<div
    data-ui-textarea
    @if ($counter) x-data="uiCharCounter({ max: {{ $maxlength }}, messages: @js($messages) })" @endif
    {{ $attributes->only('class')->class('flex min-w-0 flex-col gap-1.5') }}
>
    <textarea
        rows="{{ $rows }}"
        @if ($id !== null) id="{{ $id }}" @endif
        @if ($control->name !== null) name="{{ $control->name }}" @endif
        @if ($describedBy !== null) aria-describedby="{{ $describedBy }}" @endif
        @if ($invalid) aria-invalid="true" @endif
        @if ($counter) x-ref="control" x-on:input="update()" x-on:change="update()" @endif
        @required($control->required)
        {{ $attributes->except(['class', 'id', 'name', 'required', 'aria-describedby'])->class($classes) }}
    >{{ $slot }}</textarea>

    @if ($counter)
        <div class="flex justify-end px-5 text-caption text-fg-muted">
            <span id="{{ $limitId }}" class="sr-only">{{ __('ui_forms.textarea.limit', ['max' => $maxlength]) }}</span>
            <span
                aria-hidden="true"
                data-counter
                x-text="`${count} / ${max}`"
                x-bind:class="remaining === 0 && 'font-semibold text-danger'"
            >{{ $length }} / {{ $maxlength }}</span>
        </div>
        <span class="sr-only" aria-live="polite" data-counter-status x-text="announcement"></span>
    @endif
</div>
