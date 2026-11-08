{{--
 | Blocco segnaposto di <x-ui.skeleton>: decorativo (aria-hidden), colore `line` (il bordo
 | decorativo, visibile sia su canvas sia su surface), pulsazione lieve.
 |
 | Forme (`shape`), con i raggi dei token:
 | - text (default): riga di testo, pill alta 16 px, larga quanto il contenitore;
 | - heading: titolo, pill alta 32 px;
 | - circle / icon: cerchio o quadrato stondato (rounded-icon), lato da `size`
 |   (sm 32 · md 44 · lg 56 · xl 80 px, come x-ui.avatar);
 | - pill: un bottone (44 × 128 px);
 | - media: immagine 4:3 con rounded-media (la foto della card animale);
 | - card: rettangolo con rounded-card, alto 160 px.
 |
 | La larghezza si regola con le classi (`class="w-2/3"`); i blocchi sono `block`, quindi
 | senza classi occupano tutta la riga. Una classe `w-*` o `size-*` passata sostituisce la
 | misura predefinita della forma (pill, circle, icon), senza conflitti tra utility.
 |
 | <x-ui.skeleton.block shape="circle" size="lg" />
 | <x-ui.skeleton.block class="w-1/2" />
--}}
@props([
    'shape' => 'text',
    'size' => 'md',
])

@php
    $sized = preg_match('/(^|\s)(w|size)-/', (string) $attributes->get('class', '')) === 1;

    $sizes = [
        'sm' => 'size-8',
        'md' => 'size-11',
        'lg' => 'size-14',
        'xl' => 'size-20',
    ];

    $shapes = [
        'text' => 'h-4 rounded-full',
        'heading' => 'h-8 rounded-full',
        'circle' => 'shrink-0 rounded-full '.($sized ? '' : ($sizes[$size] ?? $sizes['md'])),
        'icon' => 'shrink-0 rounded-icon '.($sized ? '' : ($sizes[$size] ?? $sizes['md'])),
        'pill' => 'h-11 shrink-0 rounded-full'.($sized ? '' : ' w-32'),
        'media' => 'aspect-4/3 rounded-media',
        'card' => 'h-40 rounded-card',
    ];
@endphp

<span
    aria-hidden="true"
    data-skeleton-block="{{ array_key_exists($shape, $shapes) ? $shape : 'text' }}"
    {{ $attributes->class(['block animate-pulse bg-line', $shapes[$shape] ?? $shapes['text']]) }}
></span>
