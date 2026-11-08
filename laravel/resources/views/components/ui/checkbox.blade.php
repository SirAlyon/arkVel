{{--
 | Casella di spunta: <input type="checkbox"> nativo stilizzato (appearance-none) con
 | spunta Lucide, dentro la sua <label>. Il testo dello slot è la label: l'intera riga è
 | cliccabile ed è alta almeno 44 px.
 |
 | - `description`: testo sotto la label, collegato con aria-describedby;
 | - `checked`, `required` (con indicatore testuale "(obbligatorio)");
 | - `indeterminate`: stato misto ("seleziona tutto" parziale), impostato come proprietà
 |   DOM con Alpine; lo screen reader lo legge "misto";
 | - `error`: errore esplicito; se manca si legge da $errors con il name (o wire:model).
 |   Icona + testo sotto la casella, aria-invalid e aria-describedby sull'input;
 | - `invalid`: forza lo stato di errore senza messaggio;
 | - disabled nativo: bordo `line` (sotto il 3:1), voluto: WCAG 1.4.11 esclude i componenti
 |   inattivi; la label diventa `fg-muted` e il cursore not-allowed.
 |
 | Dentro x-ui.checkbox-group eredita il name del gruppo (diventa `name[]`) e non mostra
 | errori propri: il messaggio è del gruppo, la casella prende solo il bordo `danger`.
 | Il gruppo si riconosce con @aware (legend + name, senza name proprio): i contenitori
 | non devono usare `legend` come prop.
 | `class` va sul contenitore; gli altri attributi (wire:model, value, x-model...)
 | sull'<input>. Id, slug ed errore seguono le regole di App\Support\Ui\ControlAttributes.
 |
 | <x-ui.checkbox name="privacy" wire:model="privacy" required :description="__('…')">
 |     {{ __('…') }} <x-ui.link :href="route('privacy')">{{ __('…') }}</x-ui.link>
 | </x-ui.checkbox>
--}}
@use('App\Support\Ui\ControlAttributes')

@aware([
    'name' => null,
    'legend' => null,
    'error' => null,
])

@php
    // Valore proprio, letto prima di @props (che lo toglie da $attributes).
    $ownName = $attributes->get('name');
@endphp

@props([
    'name' => null,
    'legend' => null,
    'error' => null,
    'description' => null,
    'required' => false,
    'checked' => false,
    'indeterminate' => false,
    'invalid' => null,
])

@php
    $inGroup = $ownName === null && filled($legend) && filled($name);
    $value = (string) $attributes->get('value', '');
    $errorKey = $inGroup ? $name : ControlAttributes::errorKey($ownName, $attributes);

    // Nel gruppo l'errore (esplicito o da $errors) è mostrato dal gruppo: qui solo il bordo.
    $message = ControlAttributes::message($error, $errorKey, $errors ?? null);
    $invalid ??= $message !== null;
    $message = $inGroup ? null : $message;

    $id = $attributes->get('id') ?? match (true) {
        $inGroup => 'field-'.ControlAttributes::slug($name).'-'.ControlAttributes::slug($value),
        $errorKey !== null => 'field-'.ControlAttributes::slug($errorKey).($value !== '' ? '-'.ControlAttributes::slug($value) : ''),
        default => ControlAttributes::hashedId('checkbox', serialize($attributes->getAttributes()).$slot),
    };
    $descriptionId = filled($description) ? $id.'-description' : null;
    $errorId = $message !== null ? $id.'-error' : null;
    $describedBy = ControlAttributes::describedBy($attributes->get('aria-describedby'), $descriptionId, $errorId);

    $inputClasses = [
        'peer size-6 shrink-0 cursor-pointer appearance-none rounded-lg border-2 bg-canvas',
        'transition-colors duration-(--duration-fast) ease-out',
        'checked:border-primary checked:bg-primary indeterminate:border-primary indeterminate:bg-primary',
        'disabled:cursor-not-allowed disabled:border-line disabled:bg-surface disabled:checked:border-line-strong disabled:checked:bg-line-strong',
        $invalid ? 'border-danger' : 'border-line-strong',
    ];
    $markClasses = 'pointer-events-none absolute inset-0 m-auto scale-50 text-on-primary opacity-0 transition duration-(--duration-fast) ease-pop';
@endphp

<div data-ui-checkbox {{ $attributes->only('class')->class('flex min-w-0 flex-col') }}>
    <label class="inline-flex min-h-11 cursor-pointer items-start gap-3 self-start py-2.5 text-body text-fg has-[input:disabled]:cursor-not-allowed has-[input:disabled]:text-fg-muted">
        <span class="relative inline-flex size-6 shrink-0">
            <input
                type="checkbox"
                id="{{ $id }}"
                @if ($inGroup) name="{{ str($name)->finish('[]') }}" @elseif ($ownName !== null) name="{{ $ownName }}" @endif
                @if ($describedBy !== null) aria-describedby="{{ $describedBy }}" @endif
                @if ($invalid) aria-invalid="true" @endif
                @if ($indeterminate) x-init="$el.indeterminate = true" @endif
                @checked($checked)
                @required($required)
                {{ $attributes->except(['class', 'id', 'aria-describedby'])->class($inputClasses) }}
            />
            <x-ui.icon name="check" size="sm" :class="$markClasses.' peer-checked:scale-100 peer-checked:opacity-100 peer-indeterminate:opacity-0'" />
            <x-ui.icon name="minus" size="sm" :class="$markClasses.' peer-indeterminate:scale-100 peer-indeterminate:opacity-100'" />
        </span>
        <span class="min-w-0">
            {{ $slot }}
            <x-ui.field.indicator :required="$required" />
        </span>
    </label>
    @if ($descriptionId)
        <p id="{{ $descriptionId }}" class="-mt-2 ps-9 text-small text-fg-muted">{{ $description }}</p>
    @endif
    @if ($errorId)
        <x-ui.field.error :id="$errorId" :message="$message" class="ps-9" />
    @endif
</div>
