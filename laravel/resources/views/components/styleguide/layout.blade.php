{{--
 | Shell delle pagine della styleguide (/_styleguide e /_styleguide/{group}).
 | Solo in ambiente local. Carica i token (app.css), il JS dei componenti x-ui
 | (resources/js/ui.js) e Alpine tramite @livewireScripts: le pagine della
 | styleguide non contengono componenti Livewire, quindi Alpine va incluso a mano.
--}}
@props([
    'title',
    'current' => null,
])

@php
    use App\Enums\AppLocale;
    use App\Support\Styleguide\StyleguideGroups;

    $locale = app()->getLocale();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}"@unless (config('arkvel.reveal')) data-reveal="off"@endunless>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title }}</title>

    @include('partials.theme-script')
    @vite(['resources/css/app.css', 'resources/js/ui.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-canvas font-sans text-body text-fg antialiased">
    <header class="mx-auto flex max-w-page flex-col gap-4 px-gutter py-5">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <a href="{{ route('styleguide') }}" class="flex items-center gap-3 rounded-full text-fg no-underline">
                <span class="inline-flex size-11 items-center justify-center rounded-icon bg-highlight text-on-highlight">
                    <x-ui.icon name="paw-print" size="lg" />
                </span>
                <span class="text-h4">arkvel</span>
                <span class="text-small font-semibold text-fg-muted">{{ __('styleguide.heading') }}</span>
            </a>

            <div class="flex flex-wrap items-center gap-3">
                <x-ui.theme-toggle />

                <div role="group" aria-label="{{ __('styleguide.language') }}" class="inline-flex gap-1 rounded-full bg-surface p-1">
                    @foreach (AppLocale::cases() as $option)
                        <form method="POST" action="{{ route('locale.update', $option) }}">
                            @csrf
                            <button
                                type="submit"
                                lang="{{ $option->value }}"
                                aria-pressed="{{ $option->value === $locale ? 'true' : 'false' }}"
                                class="inline-flex min-h-11 items-center rounded-full px-4 text-small font-semibold text-fg hover:bg-surface-raised aria-pressed:bg-primary aria-pressed:text-on-primary"
                            >{{ $option->getLabel() }}</button>
                        </form>
                    @endforeach
                </div>
            </div>
        </div>

        <nav aria-label="{{ __('styleguide.pages') }}" class="-mx-gutter overflow-x-auto px-gutter">
            <ul class="flex w-max gap-1 rounded-full bg-surface p-1">
                <li>
                    <a
                        href="{{ route('styleguide') }}"
                        @if ($current === null) aria-current="page" @endif
                        class="inline-flex min-h-11 items-center rounded-full px-4 text-small font-semibold text-fg no-underline hover:bg-surface-raised aria-[current=page]:bg-primary aria-[current=page]:text-on-primary"
                    >{{ __('styleguide.tokens') }}</a>
                </li>
                @foreach (StyleguideGroups::available() as $group)
                    <li>
                        <a
                            href="{{ route('styleguide.group', $group) }}"
                            @if ($current === $group) aria-current="page" @endif
                            class="inline-flex min-h-11 items-center rounded-full px-4 text-small font-semibold text-fg no-underline hover:bg-surface-raised aria-[current=page]:bg-primary aria-[current=page]:text-on-primary"
                        >{{ __('styleguide.groups.'.$group.'.title') }}</a>
                    </li>
                @endforeach
            </ul>
        </nav>
    </header>

    <main id="main" {{ $attributes->class('mx-auto flex max-w-page flex-col gap-section px-gutter pb-section') }}>
        {{ $slot }}
    </main>

    @livewireScripts

    {{ $scripts ?? '' }}
</body>
</html>
