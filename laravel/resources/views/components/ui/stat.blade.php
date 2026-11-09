{{--
 | Numero in evidenza con etichetta (le stat del bento della home: «1.240 animali
 | adottati»). Blocco piatto stondato su un fondo a scelta, con il testo on-* del fondo.
 |
 | - `value` (obbligatorio): un numero (int/float), formattato nella lingua corrente con
 |   Number::format (1.240 in italiano, 1,240 in inglese), o un testo già pronto («+30%»);
 | - `label` (obbligatorio): cosa misura il numero;
 | - `tone`: leaf (default, verde) · surface · sun · peach · mint · inverse;
 | - `icon`: icona Lucide decorativa sopra il numero;
 | - `size`: lg (default, text-h2) · md (text-h3);
 | - `reveal` (default true): compare allo scroll (x-reveal, resources/js/ui/motion.js), mai
 |   sopra la piega, mai senza JS o con reduced-motion.
 |
 | Semantica: numero ed etichetta stanno nello stesso <p>, quindi lo screen reader li legge
 | insieme («1.240 animali adottati»). Più stat affiancate: una lista (<ul role="list">).
 |
 | <x-ui.stat :value="1240" :label="__('…')" />
 | <x-ui.stat value="98%" :label="__('…')" tone="surface" icon="heart" />
--}}
@props([
    'value',
    'label',
    'tone' => 'leaf',
    'icon' => null,
    'size' => 'lg',
    'reveal' => true,
])

@use('Illuminate\Support\Number')

@php
    $tones = [
        'leaf' => 'bg-block-leaf text-on-block-leaf',
        'surface' => 'bg-surface text-fg',
        'sun' => 'bg-block-sun text-on-block-sun',
        'peach' => 'bg-block-peach text-on-block-peach',
        'mint' => 'bg-block-mint text-on-block-mint',
        'inverse' => 'bg-inverse text-on-inverse',
    ];
    $tone = array_key_exists((string) $tone, $tones) ? $tone : 'leaf';

    $formatted = is_int($value) || is_float($value)
        ? Number::format($value, maxPrecision: is_float($value) ? 1 : null, locale: app()->getLocale())
        : (string) $value;
@endphp

<div data-stat="{{ $tone }}" @if ($reveal) x-reveal @endif {{ $attributes->class(['flex flex-col gap-4 rounded-card p-6', $tones[$tone]]) }}>
    @if ($icon)
        <x-ui.icon :name="$icon" size="lg" />
    @endif
    <p class="flex flex-col gap-1">
        <span @class(['block tabular-nums', 'text-h2' => $size !== 'md', 'text-h3' => $size === 'md'])>{{ $formatted }}</span>
        <span class="block font-semibold">{{ $label }}</span>
    </p>
</div>
