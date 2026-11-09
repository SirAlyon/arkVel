{{--
 | Voci account del menu mobile (stesse di <x-site.account-menu>, ma come elenco: dentro un
 | drawer un menu a tendina annidato non serve). URL da App\Support\Site\AccountLinks.
 |
 | - `user`: l'utente autenticato (lo passa <x-site.header>), null per l'ospite;
 | - `login-current` / `register-current`: aria-current dei link Accedi e Registrati;
 | - ospite: "Accedi" e "Registrati", bottoni a tutta larghezza (≥ 44 px);
 | - autenticato: nome con avatar (decorativo: il nome è scritto accanto), link all'area
 |   adottante, alla gestione dell'ente (`can-manage`) e uscita (POST con @csrf alla route
 |   pubblica di logout).
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
@endphp

<section aria-labelledby="site-menu-account" {{ $attributes->class('flex flex-col gap-3') }} data-account-links>
    <h3 id="site-menu-account" class="text-h5">{{ __('public.footer.account') }}</h3>

    @if ($user === null)
        <x-ui.button variant="secondary" size="sm" :href="$links->login()" icon="log-in" class="w-full" :aria-current="$loginCurrent" data-account-login>{{ __('public.account.login') }}</x-ui.button>
        @if ($register = $links->register())
            <x-ui.button size="sm" :href="$register" icon="user-plus" class="w-full" :aria-current="$registerCurrent" data-account-register>{{ __('public.account.register') }}</x-ui.button>
        @endif
    @else
        <p class="flex min-w-0 items-center gap-3 font-semibold">
            <x-ui.avatar :name="$user->name" size="sm" decorative />
            <span class="min-w-0 truncate">{{ $user->name }}</span>
        </p>
        <ul class="flex flex-col gap-1">
            <li><x-ui.nav-link layout="block" icon="heart-handshake" :href="$links->account()" :navigate="false">{{ __('public.account.dashboard') }}</x-ui.nav-link></li>
            @if ($canManage)
                <li><x-ui.nav-link layout="block" icon="building-2" :href="$links->manage()" :navigate="false" data-account-manage>{{ __('public.account.manage') }}</x-ui.nav-link></li>
            @endif
        </ul>
        <form method="POST" action="{{ $links->logout() }}">
            @csrf
            <x-ui.button type="submit" variant="secondary" size="sm" icon="log-out" class="w-full" data-account-logout>{{ __('public.account.logout') }}</x-ui.button>
        </form>
    @endif
</section>
