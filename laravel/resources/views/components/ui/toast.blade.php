{{--
 | Una notifica toast. Due usi:
 |
 | - dentro <x-ui.toaster> (senza `title`): legge il toast corrente del ciclo x-for dallo
 |   store Alpine `toasts`. È il caso normale: i toast si lanciano con l'evento `toast`;
 | - statico (con `title`): stessa grafica, chiudibile, per anteprime e avvisi già nella
 |   pagina. Non è una regione live: gli annunci li fa solo il toaster. Chiudendolo con il
 |   focus dentro, il focus passa al primo elemento focalizzabile che lo segue (o a quello
 |   prima), oppure a `focus-target` (selettore CSS, es. il contenitore dei messaggi).
 |
 | Tipi: success · warning · danger · info. Il tipo non passa dal solo colore: icona nel
 | riquadro colorato e, per gli screen reader, il nome del tipo prima del titolo (schema
 | tradotto ui_overlays.toast.prefix, punteggiatura compresa).
 |
 | <x-ui.toast type="success" :title="__('…')" :description="__('…')" />
--}}
@props([
    'type' => 'info',
    'title' => null,
    'description' => null,
    'focusTarget' => null,
])

@php
    $bound = $title === null;

    $types = [
        'success' => ['circle-check', 'bg-success-subtle text-on-success-subtle'],
        'warning' => ['triangle-alert', 'bg-warning-subtle text-on-warning-subtle'],
        'danger' => ['circle-x', 'bg-danger-subtle text-on-danger-subtle'],
        'info' => ['info', 'bg-info-subtle text-on-info-subtle'],
    ];

    $type = array_key_exists($type, $types) ? $type : 'info';
    $prefixes = [];

    foreach (array_keys($types) as $key) {
        $prefixes[$key] = __('ui_overlays.toast.prefix', ['type' => __('ui_overlays.toast.types.'.$key)]);
    }
@endphp

<div
    @if ($bound)
        x-bind:data-type="toast.type"
        x-bind:data-leaving="toast.leaving"
        x-bind:class="toast.leaving && 'translate-y-2 opacity-0'"
    @else
        x-data="uiToast(@js(['focusTarget' => $focusTarget]))"
        x-show="shown"
        data-type="{{ $type }}"
    @endif
    data-toast
    {{ $attributes->class([
        'flex w-full items-start gap-3 rounded-card bg-surface-raised p-3 pe-2 text-fg shadow-overlay',
        // Solo i toast dello store entrano ed escono animati; l'anteprima statica no.
        'transition-[opacity,translate] duration-(--duration-base) ease-out starting:translate-y-4 starting:opacity-0' => $bound,
    ]) }}
>
    @foreach ($types as $key => [$icon, $tone])
        @if ($bound || $key === $type)
            <span
                @if ($bound) x-show="toast.type === '{{ $key }}'" @endif
                class="inline-flex size-11 shrink-0 items-center justify-center rounded-icon {{ $tone }}"
                data-toast-icon="{{ $key }}"
            >
                <x-ui.icon :name="$icon" />
            </span>
        @endif
    @endforeach

    <div class="flex min-w-0 flex-1 flex-col gap-0.5 py-2.5">
        <p class="font-bold">
            @if ($bound)
                <span class="sr-only" x-text="@js($prefixes)[toast.type]"></span>
                <span x-text="toast.title"></span>
            @else
                <span class="sr-only">{{ $prefixes[$type] }}</span>
                <span>{{ $title }}</span>
            @endif
        </p>
        @if ($bound)
            <p class="text-small text-fg-muted" x-show="toast.description" x-text="toast.description"></p>
        @elseif ($description)
            <p class="text-small text-fg-muted">{{ $description }}</p>
        @endif
    </div>

    <x-ui.button
        variant="ghost"
        size="sm"
        icon="x"
        :label="__('ui_overlays.toast.close')"
        x-on:click="{{ $bound ? 'closeToast(toast.id, $event)' : 'dismiss()' }}"
        data-toast-close
    />
</div>
