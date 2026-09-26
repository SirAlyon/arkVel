<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name', 'ArkVel') }}</title>

    <meta name="description" content="{{ $metaDescription ?? __('public.meta.default_description') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-brand-50 font-sans text-stone-800 antialiased flex flex-col">
    <a href="#main"
       class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-brand-600 focus:px-4 focus:py-2 focus:text-white">
        {{ __('public.nav.skip_to_content') }}
    </a>

    <x-site-header />

    <main id="main" class="flex-1">
        {{ $slot }}
    </main>

    <x-site-footer />
    {{-- Livewire 4 inietta automaticamente styles/scripts (config livewire.inject_assets = true). --}}
</body>
</html>
