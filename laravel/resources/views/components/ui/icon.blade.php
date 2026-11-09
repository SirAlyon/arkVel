{{--
 | Icona Lucide (mallardduck/blade-lucide-icons), sempre a tratto e in currentColor.
 |
 | - decorativa per default: aria-hidden, nessun nome accessibile (il testo vicino
 |   descrive l'azione);
 | - con `label` diventa un'immagine con nome (role="img" + aria-label), per le
 |   icone che portano informazione da sole.
 |
 | <x-ui.icon name="paw-print" />
 | <x-ui.icon name="circle-check" size="lg" :label="__('…')" />
 |
 | Dimensioni: xs 14 · sm 16 · md 20 (default) · lg 24 · xl 32 px.
--}}
@props([
    'name',
    'size' => 'md',
    'label' => null,
])

@php
    $sizes = [
        'xs' => 'size-3.5',
        'sm' => 'size-4',
        'md' => 'size-5',
        'lg' => 'size-6',
        'xl' => 'size-8',
    ];

    $a11y = $label !== null && $label !== ''
        ? ['role' => 'img', 'aria-label' => $label]
        : ['aria-hidden' => 'true'];

    $classes = trim(($sizes[$size] ?? $sizes['md']).' shrink-0 '.$attributes->get('class', ''));
@endphp

{{ svg('lucide-'.$name, $classes, [...$attributes->except('class')->getAttributes(), ...$a11y, 'focusable' => 'false', 'data-icon' => $name]) }}
