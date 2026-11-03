{{--
 | Esempio di un componente nella styleguide: titolo h3, anteprima su un fondo a
 | scelta (canvas, surface, inverse, block-*) e, se passato, il codice Blade d'uso.
 | Il codice va nello slot `code` dentro @verbatim, altrimenti Blade compila i tag x-*:
 |
 | <x-styleguide.example :title="__('…')" background="surface">
 |     <x-slot:code>@verbatim<x-ui.button>…</x-ui.button>@endverbatim</x-slot:code>
 |     <x-ui.button>…</x-ui.button>
 | </x-styleguide.example>
--}}
@props([
    'title',
    'background' => 'canvas',
])

@php
    $backgrounds = [
        'canvas' => 'bg-canvas text-fg border-2 border-line',
        'surface' => 'bg-surface text-fg',
        'surface-raised' => 'bg-surface-raised text-fg border-2 border-line',
        'inverse' => 'bg-inverse text-on-inverse',
        'block-sun' => 'bg-block-sun text-on-block-sun',
        'block-peach' => 'bg-block-peach text-on-block-peach',
        'block-mint' => 'bg-block-mint text-on-block-mint',
        'block-leaf' => 'bg-block-leaf text-on-block-leaf',
    ];
@endphp

<div class="flex flex-col gap-3" data-styleguide-example>
    <h3 class="text-h5">{{ $title }}</h3>
    <div {{ $attributes->class(['flex flex-wrap items-center gap-4 rounded-card p-6', $backgrounds[$background] ?? $backgrounds['canvas']]) }}>
        {{ $slot }}
    </div>
    @if (isset($code) && $code->isNotEmpty())
        <details class="group rounded-card bg-surface">
            <summary class="flex min-h-11 cursor-pointer list-none items-center gap-2 rounded-card px-5 text-small font-semibold [&::-webkit-details-marker]:hidden">
                <x-ui.icon name="code" size="sm" />
                {{ __('styleguide.show_code') }}
                <x-ui.icon name="chevron-down" size="sm" class="transition-transform duration-(--duration-fast) ease-out group-open:rotate-180" />
            </summary>
            <pre class="overflow-x-auto px-5 pb-5 text-caption" tabindex="0"><code>{{ trim($code->toHtml()) }}</code></pre>
        </details>
    @endif
</div>
