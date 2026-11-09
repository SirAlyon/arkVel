{{--
 | SEO del <head> (UI-3), incluso dal layout pubblico con @include('partials.seo.head').
 | Legge $seo (App\Support\Seo\SeoData, passato dalle pagine via layoutData); senza $seo
 | non stampa nulla e il layout resta con i suoi default.
 |
 | Niente hreflang: IT/EN condividono lo stesso URL (lingua in sessione), quindi non ci
 | sono alternative per lingua da dichiarare. Il JSON-LD NON è qui ma in
 | partials/seo/breadcrumbs (nel <body>): con wire:navigate Livewire tratta gli <script>
 | del <head> come asset e non li rimuove mai, quindi i dati strutturati delle pagine
 | precedenti si accumulerebbero; il <body> invece viene sostituito.
--}}
@php($seo = $seo ?? null)
@if ($seo instanceof \App\Support\Seo\SeoData)
    <title>{{ $seo->title }}</title>
    <meta name="description" content="{{ $seo->description }}">
    <link rel="canonical" href="{{ $seo->canonical }}">
    @if ($seo->robots() !== null)
        <meta name="robots" content="{{ $seo->robots() }}">
    @endif

    <meta property="og:site_name" content="{{ __('seo.site_name') }}">
    <meta property="og:locale" content="{{ $seo->openGraphLocale() }}">
    <meta property="og:type" content="{{ $seo->type->value }}">
    <meta property="og:title" content="{{ $seo->title }}">
    <meta property="og:description" content="{{ $seo->description }}">
    <meta property="og:url" content="{{ $seo->canonical }}">
    @if ($seo->image !== null)
        <meta property="og:image" content="{{ $seo->image }}">
        @if ($seo->imageAlt !== null)
            <meta property="og:image:alt" content="{{ $seo->imageAlt }}">
        @endif
    @endif
    @if ($seo->type === \App\Support\Seo\OpenGraphType::Article && $seo->modifiedAt !== null)
        <meta property="article:modified_time" content="{{ $seo->modifiedAt->toIso8601String() }}">
    @endif

    {{-- X/Twitter legge titolo, descrizione e immagine dai tag Open Graph. --}}
    <meta name="twitter:card" content="{{ $seo->twitterCard() }}">
@endif
