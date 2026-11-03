{{--
 | Bottone (o link con l'aspetto di un bottone, se c'è `href`). Sempre "pill".
 |
 | Varianti: primary (default) · secondary (bordo) · highlight (CTA gialla) · ghost · danger.
 | Dimensioni: sm 44 px · md 48 px (default) · lg 56 px: tutte ≥ 44 px di target.
 |
 | - `icon` / `icon-trailing`: nome di un'icona Lucide prima/dopo il testo;
 | - solo icona: nessuno slot e `label` obbligatoria (diventa aria-label);
 | - `loading`: stato di attesa statico (aria-busy, disattivato). Con Livewire non serve:
 |   l'elemento che lancia la richiesta riceve `data-loading` e mostra lo spinner da solo;
 | - `disabled`: su <button> usa l'attributo nativo, su <a> toglie l'href e mette
 |   aria-disabled.
 |
 | <x-ui.button wire:click="save" icon="check">{{ __('…') }}</x-ui.button>
 | <x-ui.button variant="highlight" size="lg" :href="route('catalog')">…</x-ui.button>
 | <x-ui.button variant="ghost" icon="x" :label="__('…')" />
--}}
@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'type' => 'button',
    'icon' => null,
    'iconTrailing' => null,
    'label' => null,
    'loading' => false,
    'disabled' => false,
])

@php
    $iconOnly = $slot->isEmpty();
    $inactive = $disabled || $loading;
    $tag = $href !== null ? 'a' : 'button';

    $variants = [
        'primary' => ['bg-primary text-on-primary', 'hover:bg-primary-hover'],
        'secondary' => ['border-2 border-fg text-fg', 'hover:bg-surface'],
        'highlight' => ['bg-highlight text-on-highlight', 'hover:bg-highlight-hover'],
        'ghost' => ['text-fg', 'hover:bg-surface'],
        'danger' => ['bg-danger text-on-danger', 'hover:bg-danger-hover'],
    ];

    $sizes = [
        'sm' => [$iconOnly ? 'size-11' : 'min-h-11 px-4', 'text-small', 'sm'],
        'md' => [$iconOnly ? 'size-12' : 'min-h-12 px-6', 'text-body', 'md'],
        'lg' => [$iconOnly ? 'size-14' : 'min-h-14 px-7', 'text-lead', 'lg'],
    ];

    [$variantClasses, $hoverClasses] = $variants[$variant] ?? $variants['primary'];
    [$boxClasses, $textClasses, $iconSize] = $sizes[$size] ?? $sizes['md'];

    $classes = [
        'group/button relative inline-flex shrink-0 select-none items-center justify-center gap-2 rounded-full font-bold no-underline',
        'transition-colors duration-(--duration-fast) ease-out',
        $boxClasses,
        $textClasses,
        $disabled ? 'cursor-not-allowed bg-surface text-fg-muted' : $variantClasses,
        $inactive ? '' : $hoverClasses,
        $loading ? 'cursor-progress' : '',
    ];
@endphp

<{{ $tag }}
    @if ($tag === 'a')
        @unless ($disabled) href="{{ $href }}" @endunless
        @if ($disabled) role="link" aria-disabled="true" @endif
    @else
        type="{{ $type }}"
        @disabled($inactive)
    @endif
    @if ($iconOnly && $label) aria-label="{{ $label }}" @endif
    @if ($loading) data-loading aria-busy="true" @endif
    data-variant="{{ $variant }}"
    {{ $attributes->class($classes) }}
>
    <span class="inline-flex items-center justify-center gap-2 group-data-loading/button:invisible">
        @if ($icon)
            <x-ui.icon :name="$icon" :size="$iconSize" />
        @endif
        @unless ($iconOnly)
            <span>{{ $slot }}</span>
        @endunless
        @if ($iconTrailing)
            <x-ui.icon :name="$iconTrailing" :size="$iconSize" />
        @endif
    </span>
    <span class="absolute inset-0 hidden items-center justify-center group-data-loading/button:flex" data-button-spinner>
        <x-ui.icon name="loader-circle" :size="$iconSize" class="animate-spin" />
        <span class="sr-only">{{ __('ui.button.loading') }}</span>
    </span>
</{{ $tag }}>
