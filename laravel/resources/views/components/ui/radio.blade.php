{{--
 | Opzione radio: <input type="radio"> nativo stilizzato (appearance-none) con un punto
 | che compare con ease-pop, dentro la sua <label>. Il testo dello slot è la label:
 | l'intera riga è cliccabile ed è alta almeno 44 px. Le frecce spostano la scelta tra
 | le opzioni con lo stesso name (comportamento nativo).
 |
 | - `value` (obbligatorio), `checked`;
 | - `description`: testo sotto la label, collegato con aria-describedby;
 | - disabled nativo: bordo `line` (sotto il 3:1), voluto: WCAG 1.4.11 esclude i componenti
 |   inattivi; la label diventa `fg-muted` e il cursore not-allowed.
 |
 | Va dentro x-ui.radio-group: ne eredita name e required (riconosce il gruppo con
 | @aware: legend + name, senza name proprio). L'errore è del gruppo (il messaggio è sul
 | fieldset), l'opzione prende solo il bordo `danger`. Id e slug seguono le regole di
 | App\Support\Ui\ControlAttributes.
 | `class` va sul contenitore; gli altri attributi (wire:model, x-model...) sull'<input>.
 |
 | <x-ui.radio-group name="home" :legend="__('…')">
 |     <x-ui.radio value="house" wire:model="home" :description="__('…')">{{ __('…') }}</x-ui.radio>
 | </x-ui.radio-group>
--}}
@use('App\Support\Ui\ControlAttributes')

@aware([
    'name' => null,
    'legend' => null,
    'required' => false,
    'error' => null,
])

@php
    // Valori propri, letti prima di @props (che li toglie da $attributes).
    $ownName = $attributes->get('name');
    $ownRequired = (bool) $attributes->get('required', false);
@endphp

@props([
    'name' => null,
    'legend' => null,
    'required' => false,
    'error' => null,
    'value',
    'description' => null,
    'checked' => false,
])

@php
    $inGroup = $ownName === null && filled($legend) && filled($name);
    $name = $inGroup ? $name : $ownName;
    $required = $inGroup ? (bool) $required : $ownRequired;
    // L'errore è del gruppo (messaggio sul fieldset): qui solo il bordo `danger`.
    $invalid = $inGroup && ControlAttributes::message($error, $name, $errors ?? null) !== null;

    $id = $attributes->get('id') ?? ($name !== null
        ? 'field-'.ControlAttributes::slug($name).'-'.ControlAttributes::slug((string) $value)
        : ControlAttributes::hashedId('radio', serialize($attributes->getAttributes()).$value));
    $descriptionId = filled($description) ? $id.'-description' : null;
    $describedBy = ControlAttributes::describedBy($attributes->get('aria-describedby'), $descriptionId);
@endphp

<div data-ui-radio {{ $attributes->only('class')->class('flex min-w-0 flex-col') }}>
    <label class="inline-flex min-h-11 cursor-pointer items-start gap-3 self-start py-2.5 text-body text-fg has-[input:disabled]:cursor-not-allowed has-[input:disabled]:text-fg-muted">
        <span class="relative inline-flex size-6 shrink-0">
            <input
                type="radio"
                id="{{ $id }}"
                @if ($name !== null) name="{{ $name }}" @endif
                value="{{ $value }}"
                @if ($describedBy !== null) aria-describedby="{{ $describedBy }}" @endif
                @checked($checked)
                @required($required)
                {{ $attributes->except(['class', 'id', 'aria-describedby'])->class([
                    'peer size-6 shrink-0 cursor-pointer appearance-none rounded-full border-2 bg-canvas',
                    $invalid ? 'border-danger' : 'border-line-strong',
                    'transition-colors duration-(--duration-fast) ease-out checked:border-primary',
                    'disabled:cursor-not-allowed disabled:border-line disabled:bg-surface',
                ]) }}
            />
            <span aria-hidden="true" class="pointer-events-none absolute inset-0 m-auto size-3 scale-0 rounded-full bg-primary transition-transform duration-(--duration-fast) ease-pop peer-checked:scale-100 peer-disabled:bg-line-strong"></span>
        </span>
        <span class="min-w-0">{{ $slot }}</span>
    </label>
    @if ($descriptionId)
        <p id="{{ $descriptionId }}" class="-mt-2 ps-9 text-small text-fg-muted">{{ $description }}</p>
    @endif
</div>
