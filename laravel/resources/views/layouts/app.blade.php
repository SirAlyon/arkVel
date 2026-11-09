{{--
 | Layout del sito pubblico (layouts::app, il layout delle pagine Livewire full-page).
 |
 | Dati di layout (dalle pagine: ->title(), ->layoutData([...])):
 | - $seo: dati SEO della pagina (App\Support\Seo\SeoData), stampati da partials/seo/head
 |   (title, description, Open Graph) e partials/seo/breadcrumbs (breadcrumb e JSON-LD,
 |   sopra il contenuto);
 | - $title, $metaDescription: fallback di <title> e meta description per una pagina che
 |   non passa $seo;
 | - $themeable (default false): la pagina usa i token semantici e segue il tema scelto
 |   (chiaro / scuro / automatico). Una pagina a colori fissi (palette grezza, bg-white)
 |   resta senza opt-in: il tema è forzato chiaro (data-theme-forced su <html>, letto da
 |   partials/theme-script) e il selettore del tema non compare. Dal UI-6 tutte le pagine
 |   pubbliche lo attivano: ->layoutData(['themeable' => true]);
 | - $transparentHeader (default false): header trasparente sopra la hero, solido allo
 |   scroll (vedi components/site/header). Per la Home in UI-6a.
 | - reveal allo scroll spento da config('arkvel.reveal'): data-reveal="off" su <html>, letto
 |   da resources/js/ui/motion.js.
 |
 | Asset: solo app.css e ui.js (componenti x-ui e shell). resources/js/app.js (Echo/Reverb)
 | serve ai pannelli Filament e lo caricano loro (filament/realtime-assets): qui darebbe
 | errori WebSocket inutili.
 |
 | Navigazione SPA (wire:navigate): <main> è la regione delle View Transitions di Livewire
 | (wire:transition.navigate, animazione in app.css); header e footer restano fermi.
 | Barra di progresso: config/livewire.php (navigate). L'annuncio della nuova pagina per gli
 | screen reader è una regione live persistente (@persist), aggiornata da ui/shell.js.
--}}
@inject('fontPreload', 'App\Support\Site\FontPreload')
@php
    $seo = $seo ?? null;
    $themeable = (bool) ($themeable ?? false);
    $transparentHeader = (bool) ($transparentHeader ?? false);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-pt-24" @unless ($themeable) data-theme-forced="light" @endunless @unless (config('arkvel.reveal')) data-reveal="off" @endunless>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{-- SEO della pagina (partials/seo/head, da $seo); senza $seo restano i fallback. --}}
    @include('partials.seo.head')
    @unless ($seo instanceof \App\Support\Seo\SeoData)
        <title>{{ $title ?? config('app.name', 'ArkVel') }}</title>
        <meta name="description" content="{{ $metaDescription ?? __('public.meta.default_description') }}">
    @endunless

    @include('partials.theme-script')

    {{-- Font del testo (subset latin): preload per evitare il CLS dello swap. --}}
    @if ($fontPreloadUrl = $fontPreload->url())
        <link rel="preload" href="{{ $fontPreloadUrl }}" as="font" type="font/woff2" crossorigin data-font-preload>
    @endif
    @vite(['resources/css/app.css', 'resources/js/ui.js'])
    @livewireStyles
</head>
<body class="relative flex min-h-dvh flex-col bg-canvas font-sans text-body text-fg antialiased">
    {{-- Sentinella dello scroll per l'header (siteHeader in resources/js/ui/shell.js). --}}
    <div aria-hidden="true" class="pointer-events-none absolute inset-x-0 top-0 h-2" data-site-header-sentinel></div>

    <a
        href="#main"
        class="sr-only focus:not-sr-only focus:fixed focus:start-4 focus:top-4 focus:z-50 focus:inline-flex focus:min-h-11 focus:items-center focus:rounded-full focus:bg-primary focus:px-5 focus:font-bold focus:text-on-primary"
        data-skip-link
    >{{ __('public.nav.skip_to_content') }}</a>

    <x-site.header :transparent="$transparentHeader" :themeable="$themeable" />

    <main id="main" tabindex="-1" class="flex-1 outline-none" wire:transition.navigate>
        @include('partials.seo.breadcrumbs')

        {{ $slot }}
    </main>

    <x-site.footer :themeable="$themeable" />

    @persist('route-announcer')
        <div aria-live="polite" aria-atomic="true" class="sr-only" data-route-announcer data-template="{{ __('public.nav.navigated', ['title' => ':title']) }}"></div>
    @endpersist

    <x-ui.toaster />

    @livewireScripts
</body>
</html>
