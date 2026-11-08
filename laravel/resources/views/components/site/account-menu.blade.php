{{--
 | Menu account dell'header (da md in su; sotto, le stesse voci sono nel menu mobile:
 | <x-site.account-links>). URL e criteri da App\Support\Site\AccountLinks.
 |
 | - `user`: l'utente autenticato (lo passa <x-site.header>), null per l'ospite;
 | - `login-current` / `register-current`: aria-current dei link Accedi e Registrati
 |   (`page` su quelle pagine, null altrove);
 | - ospite: "Accedi" (pill con bordo, come nel mockup) e, da lg, "Registrati" (CTA navy).
 |   `max-lg:hidden` e non `hidden lg:inline-flex`: la classe inline-flex di base del
 |   bottone viene dopo `.hidden` nel CSS e lo mostrerebbe sempre;
 | - autenticato: <x-ui.dropdown> col nome di battesimo visibile e lo scopo («Il tuo
 |   account») per gli screen reader, dopo il nome (WCAG 2.5.3); senza nome, lo scopo è
 |   la label. Voci: area adottante, gestione dell'ente (solo per i membri verificati di un
 |   ente operabile: `can-manage`) e uscita, un POST con @csrf alla route pubblica di logout.
 |
 | I link ai pannelli Filament non usano wire:navigate: hanno un altro layout.
--}}
@props([
    'links',
    'user' => null,
    'canManage' => false,
    'loginCurrent' => null,
    'registerCurrent' => null,
])

@php
    /** @var \App\Support\Site\AccountLinks $links */
    /** @var \App\Models\User|null $user */
    $firstName = $user !== null
        ? \Illuminate\Support\Str::limit((string) \Illuminate\Support\Str::of((string) $user->name)->trim()->before(' '), 16)
        : '';
@endphp

@if ($user === null)
    <div {{ $attributes->class('flex items-center gap-2') }} data-account-guest>
        <x-ui.button variant="secondary" size="sm" :href="$links->login()" :aria-current="$loginCurrent" data-account-login>{{ __('public.account.login') }}</x-ui.button>
        @if ($register = $links->register())
            <x-ui.button size="sm" :href="$register" class="max-lg:hidden" :aria-current="$registerCurrent" data-account-register>{{ __('public.account.register') }}</x-ui.button>
        @endif
    </div>
@else
    <x-ui.dropdown
        :label="$firstName !== '' ? $firstName : __('public.account.menu')"
        :sr-label="$firstName !== '' ? __('public.account.menu') : null"
        icon="circle-user-round"
        size="sm"
        align="end"
        data-account-menu
        {{ $attributes }}
    >
        <x-ui.dropdown.item icon="heart-handshake" :href="$links->account()">{{ __('public.account.dashboard') }}</x-ui.dropdown.item>
        @if ($canManage)
            <x-ui.dropdown.item icon="building-2" :href="$links->manage()" data-account-manage>{{ __('public.account.manage') }}</x-ui.dropdown.item>
        @endif
        <x-ui.dropdown.separator />
        <form method="POST" action="{{ $links->logout() }}" class="contents">
            @csrf
            <x-ui.dropdown.item icon="log-out" x-on:click="$el.form.requestSubmit()" data-account-logout>{{ __('public.account.logout') }}</x-ui.dropdown.item>
        </form>
    </x-ui.dropdown>
@endif
