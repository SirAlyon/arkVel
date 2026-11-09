{{--
 | Layout minimo per i test SEO: include i partial come il layout pubblico, così le
 | pagine si verificano anche indipendentemente dalla shell (layouts/app).
--}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    @include('partials.seo.head')
</head>
<body>
    @include('partials.seo.breadcrumbs')
    {{ $slot }}
</body>
</html>
