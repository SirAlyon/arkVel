{{--
 | Selettore di vista a pillole (es. «Elenco / Mappa» del catalogo): un gruppo di LINK, così
 | senza JavaScript ogni vista è un URL vero; quella attiva ha aria-current="true" e il
 | fondo pieno (non solo il colore: anche il peso del testo e l’icona restano leggibili).
 |
 | - `label` (obbligatoria): nome del gruppo (role="group");
 | - `options`: valore => ['label' => …, 'icon' => …, 'href' => …];
 | - `current`: valore della vista attiva;
 | - `property`: proprietà Livewire da impostare al clic (wire:click.prevent con $set),
 |   invece di seguire il link. Senza, i link navigano.
 |
 | <x-ui.view-switch :label="__('…')" :options="$views" :current="$view" property="display" />
--}}
@props([
    'label',
    'options' => [],
    'current' => null,
    'property' => null,
])

<div role="group" aria-label="{{ $label }}" {{ $attributes->class('inline-flex max-w-full flex-wrap gap-1 rounded-full bg-surface p-1') }} data-view-switch>
    @foreach ($options as $value => $option)
        <a
            href="{{ $option['href'] }}"
            @if ($property !== null) wire:click.prevent="$set('{{ $property }}', '{{ $value }}')" @endif
            @if ((string) $value === (string) $current) aria-current="true" @endif
            @class([
                'inline-flex min-h-11 items-center gap-2 rounded-full px-4 py-2 text-small transition-colors duration-(--duration-fast) ease-out',
                'bg-primary font-bold text-on-primary' => (string) $value === (string) $current,
                'font-semibold text-fg hover:bg-canvas' => (string) $value !== (string) $current,
            ])
            data-view-switch-option="{{ $value }}"
        >
            @if (($option['icon'] ?? null) !== null)
                <x-ui.icon :name="$option['icon']" size="sm" />
            @endif
            <span>{{ $option['label'] }}</span>
        </a>
    @endforeach
</div>
