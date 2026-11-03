@props([
    'href' => null,
    'variant' => 'primary',
    'size' => 'md',
])

@php
    $variants = [
        'primary' => 'bg-brand-600 text-white shadow-sm hover:bg-brand-700 focus-visible:ring-brand-500',
        'accent' => 'bg-accent-600 text-white shadow-sm hover:bg-accent-700 focus-visible:ring-accent-500',
        'outline' => 'border border-brand-300 bg-white text-brand-800 hover:bg-brand-50 focus-visible:ring-brand-500',
        'ghost' => 'text-brand-700 hover:bg-brand-100 focus-visible:ring-brand-500',
    ];
    $sizes = [
        'sm' => 'px-3 py-1.5 text-sm',
        'md' => 'px-4 py-2.5 text-sm',
        'lg' => 'px-6 py-3 text-base',
    ];

    $classes = implode(' ', [
        'inline-flex items-center justify-center gap-2 rounded-xl font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2',
        $variants[$variant] ?? $variants['primary'],
        $sizes[$size] ?? $sizes['md'],
    ]);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button {{ $attributes->class($classes) }}>{{ $slot }}</button>
@endif
