{{--
 | Etichetta non interattiva (stato, specie, "in evidenza"...). Pill, testo 14 px 700.
 |
 | Varianti: neutral (default, bordo decorativo) · primary · highlight · stamp ·
 | success · warning · danger · info. Il significato non passa mai dal solo colore:
 | il testo lo dice, e `icon` può rinforzarlo (decorativa).
 |
 | <x-ui.badge variant="success" icon="circle-check">{{ __('…') }}</x-ui.badge>
--}}
@props([
    'variant' => 'neutral',
    'icon' => null,
])

@php
    $variants = [
        'neutral' => 'border-2 border-line text-fg',
        'primary' => 'bg-primary text-on-primary',
        'highlight' => 'bg-highlight text-on-highlight',
        'stamp' => 'bg-stamp text-on-stamp',
        'success' => 'bg-success-subtle text-on-success-subtle',
        'warning' => 'bg-warning-subtle text-on-warning-subtle',
        'danger' => 'bg-danger-subtle text-on-danger-subtle',
        'info' => 'bg-info-subtle text-on-info-subtle',
    ];
@endphp

<span data-variant="{{ $variant }}" {{ $attributes->class(['inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-caption font-bold', $variants[$variant] ?? $variants['neutral']]) }}>
    @if ($icon)
        <x-ui.icon :name="$icon" size="xs" />
    @endif
    {{ $slot }}
</span>
