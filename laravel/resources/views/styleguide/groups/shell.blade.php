{{--
 | Styleguide · gruppo "shell": nav-link (pill/block, stati attivi), language-switcher
 | (normale/compatto) e un'anteprima statica di header e footer del sito pubblico
 | (components/site/*). Testi in lang/{it,en}/styleguide_shell.php.
 |
 | Header e footer sono i componenti veri, nella variante ospite (`guest`): l'anteprima non
 | cambia se chi guarda ha fatto l'accesso. Il contenitore overflow-hidden rende lo sticky
 | dell'header inerte (resta al suo posto nell'esempio); il menu mobile (dialog nel top
 | layer) funziona come sul sito. La styleguide è themeable: in scuro si vedono i token.
 | Gli attributi data-demo servono ai test browser (tests/Browser/Components/ShellComponentsTest.php).
--}}
@php
    $t = static fn (string $key, array $replace = []): string => __('styleguide_shell.'.$key, $replace);
@endphp
<x-styleguide.layout :title="__('styleguide.page_title', ['group' => __('styleguide.groups.shell.title'), 'styleguide' => __('styleguide.title')])" current="shell">
    <div class="flex flex-col gap-3 pt-4 md:pt-8">
        <h1 class="text-h1">{{ __('styleguide.groups.shell.title') }}</h1>
        <p class="max-w-2xl text-lead text-fg-muted">{{ __('styleguide.groups.shell.intro') }}</p>
    </div>

    {{-- ── Nav link ──────────────────────────────────────────────────────── --}}
    <x-styleguide.component id="nav-link" :title="$t('nav_link.title')" :description="$t('nav_link.description')" :a11y="[$t('nav_link.a11y_current'), $t('nav_link.a11y_target'), $t('nav_link.a11y_navigate')]">
        <x-styleguide.example :title="$t('nav_link.pill')">
            <x-slot:code>@verbatim<ul class="flex items-center gap-1 rounded-full bg-surface p-1">
    <li><x-ui.nav-link :href="route('catalog')" current="page">{{ __('public.nav.catalog') }}</x-ui.nav-link></li>
    <li><x-ui.nav-link :href="route('organizations.index')">{{ __('public.nav.organizations') }}</x-ui.nav-link></li>
</ul>@endverbatim</x-slot:code>
            <ul class="flex items-center gap-1 rounded-full bg-surface p-1" data-demo="nav-pill">
                <li><x-ui.nav-link :href="route('catalog')" current="page">{{ __('public.nav.catalog') }}</x-ui.nav-link></li>
                <li><x-ui.nav-link :href="route('organizations.index')">{{ __('public.nav.organizations') }}</x-ui.nav-link></li>
            </ul>
        </x-styleguide.example>

        <x-styleguide.example :title="$t('nav_link.section')">
            <x-slot:code>@verbatim<x-ui.nav-link :href="route('organizations.index')" current="true">{{ __('public.nav.organizations') }}</x-ui.nav-link>@endverbatim</x-slot:code>
            <ul class="flex items-center gap-1 rounded-full bg-surface p-1" data-demo="nav-section">
                <li><x-ui.nav-link :href="route('catalog')">{{ __('public.nav.catalog') }}</x-ui.nav-link></li>
                <li><x-ui.nav-link :href="route('organizations.index')" current="true">{{ __('public.nav.organizations') }}</x-ui.nav-link></li>
            </ul>
        </x-styleguide.example>

        <x-styleguide.example :title="$t('nav_link.block')" background="surface-raised">
            <x-slot:code>@verbatim<x-ui.nav-link layout="block" icon="heart-handshake" :href="…" :navigate="false">{{ __('public.account.dashboard') }}</x-ui.nav-link>@endverbatim</x-slot:code>
            <ul class="flex w-full max-w-sm flex-col gap-1" data-demo="nav-block">
                <li><x-ui.nav-link layout="block" icon="paw-print" :href="route('catalog')" current="page">{{ __('public.nav.catalog') }}</x-ui.nav-link></li>
                <li><x-ui.nav-link layout="block" icon="building-2" :href="route('organizations.index')">{{ __('public.nav.organizations') }}</x-ui.nav-link></li>
                <li><x-ui.nav-link layout="block" icon="heart-handshake" :href="route('filament.adopter.home')" :navigate="false">{{ __('public.account.dashboard') }}</x-ui.nav-link></li>
            </ul>
        </x-styleguide.example>
    </x-styleguide.component>

    {{-- ── Language switcher ─────────────────────────────────────────────── --}}
    <x-styleguide.component id="language-switcher" :title="$t('language_switcher.title')" :description="$t('language_switcher.description')" :a11y="[$t('language_switcher.a11y_pressed'), $t('language_switcher.a11y_lang'), $t('language_switcher.a11y_compact')]">
        <x-styleguide.example :title="$t('language_switcher.full')">
            <x-slot:code>@verbatim<x-ui.language-switcher />@endverbatim</x-slot:code>
            <x-ui.language-switcher data-demo="language-full" />
        </x-styleguide.example>

        <x-styleguide.example :title="$t('language_switcher.compact')">
            <x-slot:code>@verbatim<x-ui.language-switcher compact />@endverbatim</x-slot:code>
            <x-ui.language-switcher compact data-demo="language-compact" />
        </x-styleguide.example>
    </x-styleguide.component>

    {{-- ── Header ────────────────────────────────────────────────────────── --}}
    <x-styleguide.component id="site-header" tag="x-site.header" :title="$t('header.title')" :description="$t('header.description')" :a11y="[$t('header.a11y_logo'), $t('header.a11y_menu'), $t('header.a11y_account')]">
        <x-styleguide.example :title="$t('header.guest')">
            <x-slot:code>@verbatim<x-site.header />
<x-site.header :transparent="true" :themeable="true" />@endverbatim</x-slot:code>
            <div class="w-full overflow-hidden" data-demo="site-header">
                <x-site.header guest />
            </div>
        </x-styleguide.example>
    </x-styleguide.component>

    {{-- ── Footer ────────────────────────────────────────────────────────── --}}
    <x-styleguide.component id="site-footer" tag="x-site.footer" :title="$t('footer.title')" :description="$t('footer.description')" :a11y="[$t('footer.a11y_newsletter'), $t('footer.a11y_legal')]">
        <x-styleguide.example :title="$t('footer.guest')">
            <x-slot:code>@verbatim<x-site.footer :themeable="true" />@endverbatim</x-slot:code>
            <div class="w-full overflow-hidden" data-demo="site-footer">
                <x-site.footer guest />
            </div>
        </x-styleguide.example>
    </x-styleguide.component>
</x-styleguide.layout>
