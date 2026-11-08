<div>
    {{-- Hero --}}
    <section class="relative overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-br from-brand-100 via-brand-50 to-accent-50" aria-hidden="true"></div>
        <div class="relative mx-auto max-w-6xl px-4 py-20 sm:px-6 sm:py-28">
            <div class="max-w-2xl">
                <span class="inline-flex items-center gap-2 rounded-full bg-white/80 px-3 py-1 text-sm font-medium text-brand-700 ring-1 ring-brand-200">
                    &#128062; {{ __('public.home.badge') }}
                </span>
                <h1 class="mt-5 text-4xl font-bold tracking-tight text-stone-900 sm:text-5xl">
                    {{ __('public.home.headline') }}
                </h1>
                <p class="mt-5 text-lg leading-relaxed text-stone-600">
                    {{ __('public.home.subheadline') }}
                </p>
                <div class="mt-8 flex flex-wrap items-center gap-3">
                    <x-button :href="route('catalog')" wire:navigate.hover variant="primary" size="lg">
                        {{ __('public.home.cta_primary') }}
                    </x-button>
                    <x-button :href="route('organizations.index')" wire:navigate.hover variant="outline" size="lg">
                        {{ __('public.home.cta_secondary') }}
                    </x-button>
                </div>

                <dl class="mt-10 flex flex-wrap gap-8">
                    <div>
                        <dt class="text-sm text-stone-500">{{ __('public.home.stat_animals') }}</dt>
                        <dd class="text-2xl font-bold text-brand-700">{{ $availableCount }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-stone-500">{{ __('public.home.stat_organizations') }}</dt>
                        <dd class="text-2xl font-bold text-brand-700">{{ $organizationCount }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </section>

    {{-- Animali in evidenza --}}
    <section class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
        <div class="flex items-end justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold tracking-tight text-stone-900">{{ __('public.home.featured_title') }}</h2>
                <p class="mt-1 text-stone-500">{{ __('public.home.featured_subtitle') }}</p>
            </div>
            <a href="{{ route('catalog') }}" wire:navigate.hover class="hidden shrink-0 text-sm font-semibold text-brand-700 hover:text-brand-800 sm:inline">
                {{ __('public.home.see_all') }} &rarr;
            </a>
        </div>

        @if ($featuredAnimals->isNotEmpty())
            <div class="mt-8 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($featuredAnimals as $animal)
                    <x-animal-card :animal="$animal" />
                @endforeach
            </div>
        @else
            <p class="mt-8 rounded-2xl border border-dashed border-brand-200 bg-white/60 p-10 text-center text-stone-500">
                {{ __('public.home.no_animals') }}
            </p>
        @endif
    </section>

    {{-- Come funziona --}}
    <section class="bg-white/60">
        <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
            <h2 class="text-2xl font-bold tracking-tight text-stone-900">{{ __('public.home.how_title') }}</h2>
            <div class="mt-8 grid grid-cols-1 gap-6 sm:grid-cols-3">
                @foreach (['browse', 'apply', 'welcome'] as $i => $step)
                    <div class="rounded-2xl border border-brand-100 bg-brand-50 p-6">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-600 text-lg font-bold text-white">
                            {{ $i + 1 }}
                        </span>
                        <h3 class="mt-4 text-lg font-semibold text-stone-800">{{ __("public.home.how_{$step}_title") }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-stone-500">{{ __("public.home.how_{$step}_body") }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
</div>
