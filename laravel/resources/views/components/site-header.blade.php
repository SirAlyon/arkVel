@php
    $navLinks = [
        ['route' => 'catalog', 'label' => __('public.nav.catalog')],
        ['route' => 'organizations.index', 'label' => __('public.nav.organizations')],
    ];
@endphp

<header class="sticky top-0 z-40 border-b border-brand-100/80 bg-brand-50/90 backdrop-blur">
    <nav class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4 sm:px-6"
         aria-label="{{ __('public.nav.primary') }}">
        <a href="{{ route('home') }}"
           class="group flex items-center gap-2 text-lg font-semibold tracking-tight text-brand-800">
            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-600 text-white shadow-sm transition group-hover:bg-brand-700"
                  aria-hidden="true">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M8.5 5.5a2 2 0 1 1-4 0 2 2 0 0 1 4 0Zm7 0a2 2 0 1 1 4 0 2 2 0 0 1-4 0ZM5 11a2 2 0 1 1 0-4 2 2 0 0 1 0 4Zm14 0a2 2 0 1 1 0-4 2 2 0 0 1 0 4Zm-7 1c2 0 3.6 1.3 4.6 3 .8 1.3 1.4 2.7-.1 3.6-.9.6-2.2.1-3.3-.2a4.7 4.7 0 0 0-2.4 0c-1.1.3-2.4.8-3.3.2-1.5-.9-.9-2.3-.1-3.6C8.4 13.3 10 12 12 12Z"/>
                </svg>
            </span>
            <span>Ark<span class="text-brand-600">Vel</span></span>
        </a>

        <div class="flex items-center gap-1 sm:gap-2">
            @foreach ($navLinks as $link)
                <a href="{{ route($link['route']) }}"
                   @class([
                       'rounded-lg px-3 py-2 text-sm font-medium transition hover:bg-brand-100 hover:text-brand-800',
                       'text-brand-800' => request()->routeIs($link['route']),
                       'text-stone-600' => ! request()->routeIs($link['route']),
                   ])
                   @if (request()->routeIs($link['route'])) aria-current="page" @endif>
                    {{ $link['label'] }}
                </a>
            @endforeach

            <a href="/account"
               class="ml-1 inline-flex items-center gap-1.5 rounded-lg bg-brand-600 px-3.5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2">
                {{ __('public.nav.account') }}
            </a>
        </div>
    </nav>
</header>
