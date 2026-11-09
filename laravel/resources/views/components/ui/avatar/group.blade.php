{{--
 | Gruppo di avatar sovrapposti (elenco <ul>), con un "+N" opzionale per chi non è mostrato.
 |
 | - `people`: elenco di persone o enti, ognuno `['name' => …, 'src' => …?]`;
 | - `max`: quanti avatar mostrare al massimo; gli altri finiscono nel "+N";
 | - `total`: il numero totale reale, se `people` è solo una parte (es. i primi 5 di 40);
 | - `size`, `shape`: passati a ogni x-ui.avatar (e al "+N");
 | - `ring` (canvas · surface): colore dell'anello che separa gli avatar, uguale al fondo
 |   su cui sta il gruppo;
 | - `label`: nome accessibile dell'elenco (aria-label), es. «Volontari del rifugio».
 |
 | Il "+N" è visibile come numero (aria-hidden) e letto come «e altri N»: neutro, perché il
 | gruppo vale per persone ed enti.
 |
 | <x-ui.avatar.group :people="$volunteers" :max="4" :label="__('…')" />
--}}
@props([
    'people' => [],
    'max' => null,
    'total' => null,
    'size' => 'md',
    'shape' => 'circle',
    'ring' => 'canvas',
    'label' => null,
])

@php
    $people = array_values(is_array($people) ? $people : collect($people)->all());
    $shown = $max !== null ? array_slice($people, 0, max(0, (int) $max)) : $people;
    $more = max(0, (int) ($total ?? count($people)) - count($shown));

    $rings = [
        'canvas' => 'ring-2 ring-canvas',
        'surface' => 'ring-2 ring-surface',
    ];

    // Sovrapposizione + anello coprono il bordo destro dell'avatar precedente: restano
    // sotto ~1/5 del diametro, così le iniziali (centrate) si leggono per intero.
    $overlaps = [
        'sm' => '-space-x-1',
        'md' => '-space-x-2',
        'lg' => '-space-x-3',
        'xl' => '-space-x-4',
    ];

    $moreSizes = [
        'sm' => 'size-8 text-caption',
        'md' => 'size-11 text-caption',
        'lg' => 'size-14 text-small',
        'xl' => 'size-20 text-h5',
    ];

    $ringClasses = $rings[$ring] ?? $rings['canvas'];
    // Stessa forma di x-ui.avatar (calcolo duplicato di proposito: una riga, due componenti).
    $radius = $shape === 'square' ? (in_array($size, ['lg', 'xl'], true) ? 'rounded-icon-lg' : 'rounded-icon') : 'rounded-full';
@endphp

<ul
    role="list"
    @if ($label) aria-label="{{ $label }}" @endif
    data-avatar-group
    {{ $attributes->class(['flex items-center', $overlaps[$size] ?? $overlaps['md']]) }}
>
    @foreach ($shown as $person)
        <li class="flex">
            <x-ui.avatar
                :name="$person['name']"
                :src="$person['src'] ?? null"
                :size="$size"
                :shape="$shape"
                :class="$ringClasses"
            />
        </li>
    @endforeach

    @if ($more > 0)
        <li class="flex" data-avatar-more="{{ $more }}">
            <span class="inline-flex items-center justify-center bg-inverse font-bold text-on-inverse {{ $radius }} {{ $ringClasses }} {{ $moreSizes[$size] ?? $moreSizes['md'] }}">
                <span aria-hidden="true">+{{ $more }}</span>
                <span class="sr-only">{{ trans_choice('ui_content.avatar.more', $more, ['count' => $more]) }}</span>
            </span>
        </li>
    @endif
</ul>
