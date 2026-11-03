{{--
 | Selettore del tema (chiaro / scuro / automatico), persistito in localStorage.
 | Richiede resources/views/partials/theme-script.blade.php nel <head>.
 | La transizione si attiva dopo il primo frame: lo stato iniziale (letto da
 | localStorage) non deve animarsi al caricamento.
 | Sotto i 640 px mostra solo le icone: il testo resta come nome accessibile.
 | Icone Lucide (sun, moon, monitor) inline finché UI-2 non introduce x-ui.icon.
--}}
@php
    $modes = [
        'light' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/>',
        'dark' => '<path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>',
        'system' => '<rect width="20" height="14" x="2" y="3" rx="2"/><path d="M8 21h8"/><path d="M12 17v4"/>',
    ];
@endphp

<div
    x-data="{
        mode: window.arkvelTheme?.get() ?? 'system',
        animated: false,
        choose(mode) {
            window.arkvelTheme?.set(mode);
            this.mode = mode;
        },
    }"
    x-init="requestAnimationFrame(() => requestAnimationFrame(() => animated = true))"
    x-on:arkvel-theme.document="mode = $event.detail.mode"
    role="group"
    aria-label="{{ __('public.theme.label') }}"
    {{ $attributes->class('inline-flex gap-1 rounded-full bg-surface p-1') }}
>
    @foreach ($modes as $mode => $icon)
        <button
            type="button"
            data-theme-option="{{ $mode }}"
            x-on:click="choose('{{ $mode }}')"
            x-bind:aria-pressed="(mode === '{{ $mode }}').toString()"
            aria-pressed="false"
            x-bind:class="animated && 'transition-colors duration-(--duration-fast) ease-out'"
            class="inline-flex min-h-11 min-w-11 items-center justify-center gap-2 rounded-full px-3 text-small sm:px-4 font-semibold text-fg hover:bg-surface-raised aria-pressed:bg-primary aria-pressed:text-on-primary"
        >
            <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icon !!}</svg>
            <span class="sr-only sm:not-sr-only">{{ __('public.theme.'.$mode) }}</span>
        </button>
    @endforeach
</div>
