@props([
    'color' => 'stone',
])

@php
    $palette = [
        'brand' => 'bg-brand-100 text-brand-800 ring-brand-200',
        'accent' => 'bg-accent-100 text-accent-800 ring-accent-200',
        'stone' => 'bg-stone-100 text-stone-700 ring-stone-200',
    ];
    $classes = $palette[$color] ?? $palette['stone'];
@endphp

<span {{ $attributes->class(['inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset', $classes]) }}>
    {{ $slot }}
</span>
