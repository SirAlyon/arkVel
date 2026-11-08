{{--
 | Impaginazione comune delle pagine di autenticazione (resources/views/livewire/auth):
 | a sinistra la card del modulo (superficie, titolo h1 + introduzione, slot, piè con i link
 | alle altre pagine), a destra da `lg` un blocco menta con i vantaggi dell'account
 | (complementare: sotto `lg` non compare). Solo token semantici: segue il tema.
 |
 | - `title` (obbligatorio): h1 della pagina (id `auth-title`);
 | - `intro`: testo sotto il titolo (o lo slot `intro`, per un testo con markup);
 | - slot: il modulo; slot `footer`: link alle altre pagine di auth.
 |
 | <x-site.auth-shell :title="__('…')" :intro="__('…')">
 |     <form wire:submit="…">…</form>
 |     <x-slot:footer>…</x-slot:footer>
 | </x-site.auth-shell>
--}}
@props([
    'title',
    'intro' => null,
])

@php
    $perks = ['favorites' => 'heart', 'apply' => 'file-heart', 'visits' => 'calendar-check'];
@endphp

<div {{ $attributes->class('mx-auto grid w-full max-w-page gap-6 px-gutter py-section lg:grid-cols-[minmax(0,7fr)_minmax(0,5fr)]') }} data-auth-page>
    <section aria-labelledby="auth-title" class="flex min-w-0 flex-col gap-8 rounded-panel bg-surface p-panel" data-auth-card>
        <header class="flex flex-col gap-3">
            <h1 id="auth-title" class="text-h2">{{ $title }}</h1>
            @if (filled($intro))
                {{-- overflow-wrap:anywhere: un'email lunga nell'introduzione va a capo invece di allargare la pagina a 320 px. --}}
                <p class="max-w-prose text-lead text-fg-muted [overflow-wrap:anywhere]" data-auth-intro>{{ $intro }}</p>
            @endif
        </header>

        {{ $slot }}

        @isset($footer)
            <div class="flex flex-col gap-3 border-t-2 border-line pt-6" data-auth-footer>
                {{ $footer }}
            </div>
        @endisset
    </section>

    <aside aria-labelledby="auth-aside-title" class="hidden min-w-0 flex-col justify-start gap-10 rounded-panel bg-block-mint p-panel text-on-block-mint lg:flex" data-auth-aside>
        <div class="flex flex-col gap-5">
            <x-ui.badge variant="highlight" icon="paw-print" class="-rotate-2 self-start">{{ __('auth_public.aside.eyebrow') }}</x-ui.badge>
            <h2 id="auth-aside-title" class="text-h2">{{ __('auth_public.aside.title') }}</h2>
        </div>
        <ul class="flex flex-col gap-4">
            @foreach ($perks as $perk => $icon)
                <li class="flex items-center gap-4 text-lead font-semibold">
                    <span class="inline-flex size-12 shrink-0 items-center justify-center rounded-icon bg-canvas text-fg"><x-ui.icon :name="$icon" /></span>
                    {{ __('auth_public.aside.perks.'.$perk) }}
                </li>
            @endforeach
        </ul>
    </aside>
</div>
