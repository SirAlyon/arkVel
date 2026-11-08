{{--
 | Campo di testo a riga singola: pill alta 48 px con bordo `line-strong` (≥ 3:1).
 |
 | - `type`: text (default) · email · tel · number · password · search · url · date
 |   (e gli altri tipi nativi: time, datetime-local...);
 | - `icon` / `icon-trailing`: icona Lucide decorativa all'inizio / alla fine;
 | - `prefix` / `suffix`: testo fisso nella pill (es. "€", "kg"), aggiunto alla
 |   descrizione del campo (aria-describedby) quando il controllo ha un id;
 | - `invalid`: forza lo stato di errore (di norma lo decide x-ui.field);
 | - password: bottone "Mostra password" da 44 px con aria-pressed (Alpine);
 | - disabled / readonly: attributi nativi. Readonly ha il bordo tratteggiato e il fondo
 |   `surface`, così si distingue senza il colore. Disabled ha il bordo `line`, sotto il
 |   3:1: è voluto, WCAG 1.4.11 esclude i componenti inattivi (e il testo resta leggibile).
 |
 | Dentro x-ui.field eredita id, name, required, aria-describedby e aria-invalid (vedi
 | x-ui.field e App\Support\Ui\ResolvedControl): id, name e required propri non sono
 | ammessi (vanno sul field). Da solo serve un nome accessibile (aria-label o
 | aria-labelledby); lo stato di errore si legge da $errors con il name o, se manca, con
 | la chiave di wire:model.
 | `class` va sulla pill (larghezza, margini); tutti gli altri attributi (wire:model,
 | autocomplete, placeholder, inputmode, min, max...) vanno sull'<input>.
 |
 | Il focus usa l'anello globale (stessi token di app.css) disegnato sulla pill, che è
 | il contorno visibile del controllo: per questo l'<input> interno non ha outline.
 |
 | <x-ui.field name="email" :label="__('…')"><x-ui.input type="email" icon="mail" wire:model="email" /></x-ui.field>
 | <x-ui.input type="search" icon="search" :aria-label="__('…')" wire:model.live.debounce="q" />
 | <x-ui.field name="amount" :label="__('…')"><x-ui.input type="number" prefix="€" min="1" /></x-ui.field>
--}}
@use('App\Support\Ui\ResolvedControl')

@aware(['uiField' => null])

@props([
    'type' => 'text',
    'icon' => null,
    'iconTrailing' => null,
    'prefix' => null,
    'suffix' => null,
    'invalid' => null,
])

@php
    // Id, name, obbligo ed errore: dal field se c'è, altrimenti propri (ResolvedControl).
    $control = ResolvedControl::resolve($uiField, $attributes, $errors ?? null, $invalid);
    $id = $control->id;
    $invalid = $control->invalid;

    $prefixId = $id !== null && filled($prefix) ? $id.'-prefix' : null;
    $suffixId = $id !== null && filled($suffix) ? $id.'-suffix' : null;
    $describedBy = $control->describedBy($prefixId, $suffixId);

    $password = $type === 'password';
    $disabled = (bool) $attributes->get('disabled', false);
    $readonly = (bool) $attributes->get('readonly', false);
    $leading = filled($icon) || filled($prefix);
    $trailing = filled($iconTrailing) || filled($suffix) || $password;

    $boxClasses = [
        'flex min-h-12 w-full min-w-0 items-center rounded-full border-2 text-fg',
        'transition-colors duration-(--duration-fast) ease-out',
        'has-[input:focus-visible]:outline-3 has-[input:focus-visible]:outline-offset-2',
        'has-[input:disabled]:cursor-not-allowed has-[input:disabled]:border-line has-[input:disabled]:bg-surface',
        $invalid ? 'border-danger' : 'border-line-strong',
        'hover:border-fg' => ! $invalid && ! $disabled && ! $readonly,
        $readonly ? 'border-dashed bg-surface' : 'bg-canvas',
    ];

    $inputClasses = [
        'h-11 w-full min-w-0 flex-1 rounded-full bg-transparent text-body text-fg placeholder:text-fg-muted',
        'focus-visible:outline-hidden disabled:cursor-not-allowed disabled:text-fg-muted',
        $leading ? 'ps-2' : 'ps-5',
        $trailing ? 'pe-2' : 'pe-5',
    ];
@endphp

<div
    data-ui-input="{{ $type }}"
    @if ($password) x-data="{ revealed: false }" @endif
    {{ $attributes->only('class')->class($boxClasses) }}
>
    @if (filled($icon))
        <span class="inline-flex shrink-0 ps-4 text-fg-muted"><x-ui.icon :name="$icon" /></span>
    @endif
    @if (filled($prefix))
        <span @if ($prefixId) id="{{ $prefixId }}" @endif class="shrink-0 select-none ps-5 font-semibold text-fg-muted">{{ $prefix }}</span>
    @endif

    <input
        type="{{ $type }}"
        @if ($password) x-bind:type="revealed ? 'text' : 'password'" @endif
        @if ($id !== null) id="{{ $id }}" @endif
        @if ($control->name !== null) name="{{ $control->name }}" @endif
        @if ($describedBy !== null) aria-describedby="{{ $describedBy }}" @endif
        @if ($invalid) aria-invalid="true" @endif
        @required($control->required)
        {{ $attributes->except(['class', 'id', 'name', 'required', 'aria-describedby'])->class($inputClasses) }}
    />

    @if (filled($suffix))
        <span @if ($suffixId) id="{{ $suffixId }}" @endif class="shrink-0 select-none pe-5 font-semibold text-fg-muted">{{ $suffix }}</span>
    @endif
    @if (filled($iconTrailing))
        <span class="inline-flex shrink-0 pe-4 text-fg-muted"><x-ui.icon :name="$iconTrailing" /></span>
    @endif
    @if ($password)
        <button
            type="button"
            data-password-toggle
            x-on:click="revealed = ! revealed"
            x-bind:aria-pressed="revealed.toString()"
            aria-pressed="false"
            aria-label="{{ __('ui_forms.input.show_password') }}"
            @if ($id !== null) aria-controls="{{ $id }}" @endif
            @disabled($disabled)
            class="inline-flex size-11 shrink-0 items-center justify-center rounded-full text-fg transition-colors duration-(--duration-fast) ease-out hover:bg-surface disabled:cursor-not-allowed disabled:text-fg-muted disabled:hover:bg-transparent"
        >
            <x-ui.icon name="eye" x-show="! revealed" />
            <x-ui.icon name="eye-off" x-show="revealed" style="display: none" />
        </button>
    @endif
</div>
