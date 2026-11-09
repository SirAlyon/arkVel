{{--
 | Intestazione condivisa da <x-ui.dialog> e <x-ui.drawer> (uso interno): titolo h2
 | (aria-labelledby del dialog), descrizione facoltativa (aria-describedby), icona
 | facoltativa in un riquadro stondato e bottone di chiusura con nome tradotto.
 | Gli id arrivano da x-id sul contenitore del componente ($id('ui-dialog-title'), …).
--}}
@props([
    'title',
    'description' => null,
    'icon' => null,
    'tone' => 'highlight',
    'closeLabel',
])

@php
    $tones = [
        'highlight' => 'bg-highlight text-on-highlight',
        'danger' => 'bg-danger-subtle text-on-danger-subtle',
    ];
@endphp

<header {{ $attributes->class('flex items-start gap-4') }}>
    @if ($icon)
        <span class="inline-flex size-12 shrink-0 items-center justify-center rounded-icon-lg {{ $tones[$tone] ?? $tones['highlight'] }}">
            <x-ui.icon :name="$icon" size="lg" />
        </span>
    @endif
    <div class="flex min-w-0 flex-1 flex-col gap-2 pt-2">
        <h2 x-bind:id="$id('ui-dialog-title')" class="text-h4" data-dialog-title>{{ $title }}</h2>
        @if ($description)
            <p x-bind:id="$id('ui-dialog-description')" class="text-fg-muted" data-dialog-description>{{ $description }}</p>
        @endif
    </div>
    <x-ui.button variant="ghost" size="sm" icon="x" :label="$closeLabel" x-on:click="close()" data-dialog-close class="-me-2" />
</header>
