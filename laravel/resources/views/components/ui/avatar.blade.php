{{--
 | Avatar di una persona o logo di un ente. Adattato da Pines/Sheaf UI (MIT).
 |
 | - `name` (obbligatorio): nome della persona o dell'ente. Diventa l'alt della foto o, senza
 |   foto, il nome accessibile delle iniziali (role="img");
 | - `src`: foto o logo. Se l'immagine non si carica restano le iniziali, con lo stesso nome;
 | - `decorative`: quando il nome è già scritto accanto. La foto ha alt="" e le iniziali
 |   sono aria-hidden, così lo screen reader non lo legge due volte;
 | - fallback: le iniziali (prima lettera della prima e dell'ultima parola), in testo
 |   on-block-* su un blocco scelto in modo deterministico dal nome: stessa persona, stesso
 |   colore. `color` (sun · peach · mint · leaf) lo forza. Il tono sun usa highlight, che
 |   in dark resta giallo e non si confonde con le superfici;
 | - `size`: sm 32 · md 44 (default) · lg 56 · xl 80 px;
 | - `shape`: circle (default) o square, il quadrato stondato (rounded-icon) per i loghi.
 |
 | Per più avatar sovrapposti: <x-ui.avatar.group>.
 |
 | <x-ui.avatar name="Maria Rossi" src="…" />
 | <x-ui.avatar name="Rifugio Il Faro" shape="square" size="lg" />
 | <x-ui.avatar :name="$user->name" decorative /> {{ $user->name }}
--}}
@props([
    'name',
    'src' => null,
    'size' => 'md',
    'shape' => 'circle',
    'decorative' => false,
    'color' => null,
])

@php
    // Toni dei blocchi colorati. `sun` usa highlight: in chiaro è identico a block-sun
    // (sun-300 con testo navy-900), in dark resta giallo (piccolo accento, ammesso), mentre
    // block-sun in dark diventa navy-800 come bg-surface e le iniziali sparirebbero su una
    // card (es. x-ui.organization-card). Stessa persona, stesso colore, in entrambi i temi.
    $blocks = [
        'sun' => 'bg-highlight text-on-highlight',
        'peach' => 'bg-block-peach text-on-block-peach',
        'mint' => 'bg-block-mint text-on-block-mint',
        'leaf' => 'bg-block-leaf text-on-block-leaf',
    ];

    $sizes = [
        'sm' => ['size-8 text-caption', 32],
        'md' => ['size-11 text-small', 44],
        'lg' => ['size-14 text-h5', 56],
        'xl' => ['size-20 text-h4', 80],
    ];

    $name = trim((string) $name);
    $words = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $initials = mb_strtoupper(collect([reset($words), count($words) > 1 ? end($words) : null])
        ->filter()
        ->map(static fn (string $word): string => mb_substr($word, 0, 1))
        ->implode(''));

    $color = array_key_exists((string) $color, $blocks)
        ? $color
        : array_keys($blocks)[crc32(mb_strtolower($name)) % count($blocks)];

    [$sizeClasses, $pixels] = $sizes[$size] ?? $sizes['md'];
    // Stesso calcolo nel "+N" di x-ui.avatar.group: le due bolle devono avere la stessa forma.
    $radius = $shape === 'square' ? (in_array($size, ['lg', 'xl'], true) ? 'rounded-icon-lg' : 'rounded-icon') : 'rounded-full';
    $named = ! $decorative && $src === null;
@endphp

<span
    @if ($named) role="img" aria-label="{{ $name }}" @endif
    @if ($decorative && $src === null) aria-hidden="true" @endif
    data-avatar="{{ $color }}"
    {{ $attributes->class(['relative inline-flex shrink-0 select-none items-center justify-center overflow-hidden font-bold', $sizeClasses, $radius, $blocks[$color]]) }}
>
    <span aria-hidden="true" data-avatar-initials>{{ $initials }}</span>
    @if ($src !== null)
        <img
            src="{{ $src }}"
            alt="{{ $decorative ? '' : $name }}"
            width="{{ $pixels }}"
            height="{{ $pixels }}"
            loading="lazy"
            decoding="async"
            class="absolute inset-0 size-full object-cover"
            x-data="{
                fail() {
                    this.$el.hidden = true;
                    if (this.$el.alt) {
                        this.$el.parentElement.setAttribute('role', 'img');
                        this.$el.parentElement.setAttribute('aria-label', this.$el.alt);
                    }
                },
            }"
            x-init="$el.complete && $el.naturalWidth === 0 && fail()"
            x-on:error="fail()"
        >
    @endif
</span>
