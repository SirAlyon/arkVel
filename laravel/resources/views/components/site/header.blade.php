{{--
 | Header del sito pubblico (direzione B, §1-bis del piano UI).
 |
 | - logo, navigazione principale come gruppo di pillole su surface (voce attiva navy piena,
 |   aria-current), selettore lingua compatto e account (da md in su);
 | - sotto md la navigazione passa nel menu mobile (<x-ui.drawer>, stesso elenco di voci,
 |   account, lingua e tema): niente overflow orizzontale a 320 px;
 | - sticky, senza ombra: allo scroll compare il bordo `line` (siteHeader, in
 |   resources/js/ui/shell.js, osserva la sentinella in cima al <body>);
 | - `transparent` (opt-in di layout, `transparentHeader`): fisso sopra la pagina, senza
 |   fondo finché non si scorre, poi canvas + bordo. La prima sezione della pagina (la hero)
 |   ci passa sotto: deve lasciare in alto lo spazio dell'header (h-18 + bordo);
 | - `themeable`: mostra il selettore del tema nel menu mobile;
 | - `guest`: mostra la variante ospite anche a chi ha fatto l'accesso (anteprima statica
 |   nella styleguide, /_styleguide/shell). Le pagine non lo usano.
 |
 | Voci da App\Support\Site\SiteNavigation, URL dell'account da App\Support\Site\AccountLinks.
--}}
@props([
    'transparent' => false,
    'themeable' => false,
    'guest' => false,
])

@inject('navigation', 'App\Support\Site\SiteNavigation')
@inject('accountLinks', 'App\Support\Site\AccountLinks')

@php
    $items = $navigation->primary();
    $user = $guest ? null : auth()->user();
    $canManage = $accountLinks->canManage($user);
@endphp

<header
    x-data="siteHeader"
    x-bind:data-scrolled="scrolled ? 'true' : null"
    data-site-header
    data-transparent="{{ $transparent ? 'true' : 'false' }}"
    @class([
        'top-0 z-40 border-b-2 border-transparent transition-colors duration-(--duration-base) ease-out data-scrolled:border-line',
        'sticky bg-canvas' => ! $transparent,
        'fixed inset-x-0 bg-transparent data-scrolled:bg-canvas' => $transparent,
    ])
>
    <div class="mx-auto flex h-18 max-w-page items-center gap-3 px-gutter md:gap-6">
        <x-site.logo />

        <nav aria-label="{{ __('public.nav.primary') }}" class="hidden md:block" data-site-nav>
            <ul class="flex items-center gap-1 rounded-full bg-surface p-1">
                @foreach ($items as $item)
                    <li><x-ui.nav-link :href="$item->url" :current="$item->current" data-nav-item="{{ $item->key }}">{{ $item->label }}</x-ui.nav-link></li>
                @endforeach
            </ul>
        </nav>

        <div class="ms-auto flex items-center gap-2">
            <div class="hidden md:block" data-header-language>
                <x-ui.language-switcher compact />
            </div>

            <div class="hidden md:block" data-header-account>
                <x-site.account-menu :links="$accountLinks" :user="$user" :can-manage="$canManage" />
            </div>

            <x-ui.drawer name="site-menu" :title="__('public.nav.menu')" data-site-menu>
                <x-slot:trigger>
                    <x-ui.button
                        variant="ghost"
                        icon="menu"
                        :label="__('public.nav.open_menu')"
                        aria-haspopup="dialog"
                        class="md:hidden"
                        data-site-menu-trigger
                    />
                </x-slot:trigger>

                <nav aria-label="{{ __('public.nav.primary') }}">
                    <ul class="flex flex-col gap-1">
                        @foreach ($items as $item)
                            <li><x-ui.nav-link layout="block" :href="$item->url" :current="$item->current" data-nav-item="{{ $item->key }}">{{ $item->label }}</x-ui.nav-link></li>
                        @endforeach
                    </ul>
                </nav>

                <x-site.account-links :links="$accountLinks" :user="$user" :can-manage="$canManage" class="border-t-2 border-line pt-6" />

                <section aria-labelledby="site-menu-preferences" class="flex flex-col gap-3 border-t-2 border-line pt-6">
                    <h3 id="site-menu-preferences" class="text-h5">{{ __('public.footer.preferences') }}</h3>
                    <x-ui.language-switcher class="self-start" />
                    @if ($themeable)
                        <x-ui.theme-toggle class="self-start" />
                    @endif
                </section>
            </x-ui.drawer>
        </div>
    </div>
</header>
