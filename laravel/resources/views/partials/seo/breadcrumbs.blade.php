{{--
 | Percorso di navigazione visibile + dati strutturati della pagina (UI-3), incluso dal
 | layout pubblico sopra il contenuto con @include('partials.seo.breadcrumbs').
 |
 | - breadcrumb: solo se $seo ne ha (la home no), con x-ui.breadcrumb;
 | - JSON-LD: un solo <script type="application/ld+json"> con l'@graph della pagina.
 |   Sta nel <body> e non nel <head> di proposito (vedi partials/seo/head): con
 |   wire:navigate il <body> viene sostituito, quindi i dati restano quelli della pagina
 |   corrente. Il JSON è codificato da App\Support\Seo\JsonLd (JSON_HEX_TAG: nessun testo
 |   può chiudere lo <script>), per questo è stampato senza escape.
--}}
@php($seo = $seo ?? null)
@if ($seo instanceof \App\Support\Seo\SeoData)
    @if ($seo->hasBreadcrumbs())
        <div class="mx-auto w-full max-w-page px-gutter pt-4">
            <x-ui.breadcrumb :items="array_map(static fn (\App\Support\Seo\Breadcrumb $breadcrumb): array => $breadcrumb->toArray(), $seo->breadcrumbs)" />
        </div>
    @endif

    @if (($jsonLd = $seo->jsonLdScript()) !== null)
        <script type="application/ld+json">{!! $jsonLd !!}</script>
    @endif
@endif
