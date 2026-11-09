{{--
 | Una voce della scheda dell'animale dentro una <dl>: etichetta (dt) e valore (dd, nello
 | slot), su un riquadro canvas dentro la superficie del pannello. L'etichetta usa fg-muted
 | (7,5:1 su canvas in chiaro), mai i grigi chiari del vecchio markup.
 |
 | - `label` (obbligatoria): il nome del dato, già tradotto;
 | - `icon`: icona Lucide decorativa accanto al valore (es. lo stato di una compatibilità:
 |   il significato resta nel testo).
 |
 | <dl class="grid gap-3 sm:grid-cols-2">
 |     <x-site.animal.fact :label="__('page_animal.facts.sex')">{{ $animal->sex->getLabel() }}</x-site.animal.fact>
 | </dl>
--}}
@props([
    'label',
    'icon' => null,
])

<div data-animal-fact {{ $attributes->class('flex min-w-0 flex-col gap-1 rounded-step bg-canvas px-5 py-4') }}>
    <dt class="text-small font-semibold text-fg-muted">{{ $label }}</dt>
    <dd class="flex min-w-0 items-center gap-2 text-lead font-bold text-fg [overflow-wrap:anywhere]">
        @if ($icon)
            <x-ui.icon :name="$icon" class="shrink-0" />
        @endif
        <span class="min-w-0">{{ $slot }}</span>
    </dd>
</div>
