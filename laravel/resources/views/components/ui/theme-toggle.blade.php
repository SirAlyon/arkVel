{{--
 | Selettore del tema (chiaro / scuro / automatico), persistito in localStorage.
 | Richiede resources/views/partials/theme-script.blade.php nel <head>.
 | La transizione si attiva dopo il primo frame: lo stato iniziale (letto da
 | localStorage) non deve animarsi al caricamento.
 | Sotto i 640 px mostra solo le icone: il testo resta come nome accessibile.
 | aria-pressed: il server non conosce la scelta (sta in localStorage) e rende "false";
 | lo script in fondo (window.arkvelTheme.syncToggle) lo corregge appena il markup è
 | letto, prima che parta Alpine, che poi lo tiene aggiornato.
 | Icone Lucide (sun, moon, monitor) via <x-ui.icon>.
--}}
@php
    $modes = [
        'light' => 'sun',
        'dark' => 'moon',
        'system' => 'monitor',
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
            <x-ui.icon :name="$icon" />
            <span class="sr-only sm:not-sr-only">{{ __('public.theme.'.$mode) }}</span>
        </button>
    @endforeach
    <script>window.arkvelTheme?.syncToggle(document.currentScript.parentElement)</script>
</div>
