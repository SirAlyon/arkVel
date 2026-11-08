{{--
 | Interruttore acceso/spento: <input type="checkbox" role="switch"> nativo (lo stato
 | "attivo" lo legge lo screen reader dall'input, senza aria-checked), binario pill con
 | pallino che scorre con ease-pop. Lo stato non passa dal solo colore: il pallino cambia
 | lato e, acceso, mostra una spunta. Il testo dello slot è la label; l'intera riga è
 | cliccabile ed è alta almeno 44 px. Per un'impostazione che vale subito (newsletter,
 | notifiche); in un modulo da inviare è altrettanto valido di una checkbox.
 |
 | - `description`: testo sotto la label, collegato con aria-describedby;
 | - `checked`, disabled nativo (bordo `line`, sotto il 3:1: voluto, WCAG 1.4.11 esclude
 |   i componenti inattivi; la label diventa `fg-muted`);
 | - `required`: required nativo (va acceso per inviare) con l'indicatore testuale
 |   "(obbligatorio)", come x-ui.checkbox;
 | - `error`: errore esplicito; se manca si legge da $errors con il name (o wire:model);
 | - `invalid`: forza lo stato di errore senza messaggio.
 |
 | `class` va sul contenitore; gli altri attributi (wire:model, value...) sull'<input>.
 | Id, slug ed errore seguono le regole di App\Support\Ui\ControlAttributes.
 |
 | <x-ui.switch name="newsletter" wire:model.live="newsletter" :description="__('…')">{{ __('…') }}</x-ui.switch>
--}}
@use('App\Support\Ui\ControlAttributes')

@props([
    'description' => null,
    'error' => null,
    'required' => false,
    'checked' => false,
    'invalid' => null,
])

@php
    $errorKey = ControlAttributes::errorKey($attributes->get('name'), $attributes);
    $message = ControlAttributes::message($error, $errorKey, $errors ?? null);
    $invalid ??= $message !== null;

    $id = $attributes->get('id') ?? ($errorKey !== null
        ? 'field-'.ControlAttributes::slug($errorKey)
        : ControlAttributes::hashedId('switch', serialize($attributes->getAttributes()).$slot));
    $descriptionId = filled($description) ? $id.'-description' : null;
    $errorId = $message !== null ? $id.'-error' : null;
    $describedBy = ControlAttributes::describedBy($attributes->get('aria-describedby'), $descriptionId, $errorId);
@endphp

<div data-ui-switch {{ $attributes->only('class')->class('flex min-w-0 flex-col') }}>
    <label class="group/switch inline-flex min-h-11 cursor-pointer items-start gap-3 self-start py-2.5 text-body text-fg has-[input:disabled]:cursor-not-allowed has-[input:disabled]:text-fg-muted">
        <span class="relative inline-flex h-7 w-12 shrink-0">
            <input
                type="checkbox"
                role="switch"
                id="{{ $id }}"
                @if ($describedBy !== null) aria-describedby="{{ $describedBy }}" @endif
                @if ($invalid) aria-invalid="true" @endif
                @checked($checked)
                @required($required)
                {{ $attributes->except(['class', 'id', 'aria-describedby', 'role', 'type'])->class([
                    'peer absolute inset-0 size-full cursor-pointer appearance-none rounded-full border-2 bg-canvas',
                    'transition-colors duration-(--duration-base) ease-out checked:border-primary checked:bg-primary',
                    'disabled:cursor-not-allowed disabled:border-line disabled:bg-surface',
                    $invalid ? 'border-danger' : 'border-line-strong',
                ]) }}
            />
            <span
                aria-hidden="true"
                class="pointer-events-none absolute start-1 top-1 inline-flex size-5 items-center justify-center rounded-full bg-line-strong text-primary transition duration-(--duration-base) ease-pop peer-checked:translate-x-5 peer-checked:bg-on-primary peer-disabled:bg-line rtl:peer-checked:-translate-x-5"
            >
                <x-ui.icon name="check" size="xs" class="scale-50 opacity-0 transition duration-(--duration-base) ease-pop group-has-checked/switch:scale-100 group-has-checked/switch:opacity-100" />
            </span>
        </span>
        <span class="min-w-0">
            {{ $slot }}
            <x-ui.field.indicator :required="$required" />
        </span>
    </label>
    @if ($descriptionId)
        <p id="{{ $descriptionId }}" class="-mt-2 ps-15 text-small text-fg-muted">{{ $description }}</p>
    @endif
    @if ($errorId)
        <x-ui.field.error :id="$errorId" :message="$message" class="ps-15" />
    @endif
</div>
