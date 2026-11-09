{{--
 | Percorso di navigazione (breadcrumb).
 |
 | - <nav> con nome tradotto e lista ordinata: lo screen reader annuncia «Percorso di
 |   navigazione, elenco, 3 elementi»;
 | - l'ultima voce è la pagina corrente: testo (non link) con aria-current="page";
 | - separatori chevron decorativi (aria-hidden), fuori dal nome delle voci;
 | - link con wire:navigate (prefetch al passaggio del mouse), target di almeno 24 px
 |   (WCAG 2.5.8: sono link testuali, non controlli) e sottolineati: mai solo colore;
 | - su una riga, senza overflow orizzontale a 320 px (il nome intero resta nel title):
 |   - la home e le voci intermedie non si restringono (shrink-0): sono i link per risalire,
 |     devono restare leggibili. Sotto md le intermedie hanno però un tetto in ch
 |     (`max-w-crumb`, token in app.css) oltre il quale vanno coi puntini (es. il nome lungo
 |     di un ente); da md lo spazio basta e il nome resta intero;
 |   - la pagina corrente (già nel titolo della pagina) prende lo spazio che resta
 |     (min-w-0 flex-1) e va coi puntini lei.
 |
 | <x-ui.breadcrumb :items="[
 |     ['name' => __('seo.breadcrumb.home'), 'url' => route('home')],
 |     ['name' => $animal->name, 'url' => route('animals.show', $animal)],
 | ]" />
--}}
@props([
    'items' => [],
])

@php
    /** @var list<array{name: string, url: string}> $items */
    $items = array_values($items);
    $last = count($items) - 1;
@endphp

@if ($items !== [])
    <nav aria-label="{{ __('seo.breadcrumb.label') }}" {{ $attributes->class('min-w-0 text-small') }} data-breadcrumb>
        <ol class="flex min-w-0 items-center gap-1 text-fg-muted">
            @foreach ($items as $index => $item)
                <li @class(['flex items-center gap-1', 'min-w-0 flex-1' => $index === $last, 'shrink-0' => $index !== $last])>
                    @if ($index > 0)
                        <x-ui.icon name="chevron-right" size="sm" />
                    @endif

                    @if ($index === $last)
                        <span aria-current="page" title="{{ $item['name'] }}" class="truncate font-semibold text-fg">{{ $item['name'] }}</span>
                    @else
                        <a
                            href="{{ $item['url'] }}"
                            wire:navigate.hover
                            data-text-link
                            title="{{ $item['name'] }}"
                            @class(['inline-flex min-h-6 min-w-6 items-center rounded-sm underline decoration-1 underline-offset-4 transition-colors duration-(--duration-fast) ease-out hover:text-fg hover:decoration-2', 'max-md:max-w-crumb' => $index > 0])
                        ><span class="truncate">{{ $item['name'] }}</span></a>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
@endif
