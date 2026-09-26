<div class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
    {{-- Breadcrumb --}}
    <nav class="mb-6 flex items-center gap-2 text-sm text-stone-500" aria-label="{{ __('public.detail.breadcrumb') }}">
        <a href="{{ route('catalog') }}" class="hover:text-brand-700">{{ __('public.nav.catalog') }}</a>
        <span aria-hidden="true">/</span>
        <span class="text-stone-700">{{ $animal->name }}</span>
    </nav>

    <div class="grid grid-cols-1 gap-10 lg:grid-cols-2">
        {{-- Galleria --}}
        <div>
            @php
                $cover = $animal->getFirstMediaUrl('photos', 'thumb') ?: $animal->getFirstMediaUrl('avatar', 'thumb');
            @endphp
            <div class="aspect-[4/3] overflow-hidden rounded-2xl border border-brand-100 bg-brand-100">
                @if ($cover)
                    <img src="{{ $cover }}" alt="{{ __('public.animal.photo_alt', ['name' => $animal->name]) }}"
                         class="h-full w-full object-cover">
                @else
                    <div class="flex h-full w-full items-center justify-center text-brand-300" aria-hidden="true">
                        <svg class="h-24 w-24" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M8.5 5.5a2 2 0 1 1-4 0 2 2 0 0 1 4 0Zm7 0a2 2 0 1 1 4 0 2 2 0 0 1-4 0ZM5 11a2 2 0 1 1 0-4 2 2 0 0 1 0 4Zm14 0a2 2 0 1 1 0-4 2 2 0 0 1 0 4Zm-7 1c2 0 3.6 1.3 4.6 3 .8 1.3 1.4 2.7-.1 3.6-.9.6-2.2.1-3.3-.2a4.7 4.7 0 0 0-2.4 0c-1.1.3-2.4.8-3.3.2-1.5-.9-.9-2.3-.1-3.6C8.4 13.3 10 12 12 12Z"/>
                        </svg>
                    </div>
                @endif
            </div>

            @if ($photos->count() > 1)
                <div class="mt-3 grid grid-cols-4 gap-3">
                    @foreach ($photos->take(4) as $photo)
                        <div class="aspect-square overflow-hidden rounded-xl border border-brand-100 bg-brand-100">
                            <img src="{{ $photo->getUrl('thumb') }}" alt="{{ __('public.animal.photo_alt', ['name' => $animal->name]) }}"
                                 loading="lazy" class="h-full w-full object-cover">
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Dettagli --}}
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <x-badge color="brand">{{ $animal->species->getLabel() }}</x-badge>
                @if ($animal->featured)
                    <x-badge color="accent">&#9733; {{ __('public.animal.featured') }}</x-badge>
                @endif
            </div>

            <div class="mt-3 flex items-start justify-between gap-3">
                <h1 class="text-3xl font-bold tracking-tight text-stone-900">{{ $animal->name }}</h1>
                <livewire:favorite-button :animal="$animal" :key="'fav-detail-'.$animal->id" />
            </div>

            @if ($animal->breed)
                <p class="mt-1 text-stone-500">{{ $animal->breed }}</p>
            @endif

            {{-- Anagrafica --}}
            <dl class="mt-6 grid grid-cols-2 gap-4 rounded-2xl border border-brand-100 bg-white p-5 sm:grid-cols-3">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-stone-400">{{ __('public.detail.sex') }}</dt>
                    <dd class="mt-0.5 text-sm font-medium text-stone-800">{{ $animal->sex->getLabel() }}</dd>
                </div>
                @if ($animal->size)
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-stone-400">{{ __('public.detail.size') }}</dt>
                        <dd class="mt-0.5 text-sm font-medium text-stone-800">{{ $animal->size->getLabel() }}</dd>
                    </div>
                @endif
                @if ($animal->energy_level)
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-stone-400">{{ __('public.detail.energy') }}</dt>
                        <dd class="mt-0.5 text-sm font-medium text-stone-800">{{ $animal->energy_level->getLabel() }}</dd>
                    </div>
                @endif
                @if ($animal->estimated_age_months)
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-stone-400">{{ __('public.detail.age') }}</dt>
                        <dd class="mt-0.5 text-sm font-medium text-stone-800">
                            {{ trans_choice('public.detail.age_months', $animal->estimated_age_months, ['count' => $animal->estimated_age_months]) }}
                        </dd>
                    </div>
                @endif
            </dl>

            {{-- Temperamento / compatibilità --}}
            @if ($animal->temperament || $animal->good_with_kids !== null || $animal->good_with_dogs !== null || $animal->good_with_cats !== null)
                <div class="mt-5">
                    <h2 class="text-sm font-semibold text-stone-700">{{ __('public.detail.character') }}</h2>
                    <div class="mt-2 flex flex-wrap gap-1.5">
                        @if ($animal->temperament)
                            <x-badge color="stone">{{ $animal->temperament }}</x-badge>
                        @endif
                        @if ($animal->good_with_kids)
                            <x-badge color="accent">{{ __('public.detail.good_with_kids') }}</x-badge>
                        @endif
                        @if ($animal->good_with_dogs)
                            <x-badge color="accent">{{ __('public.detail.good_with_dogs') }}</x-badge>
                        @endif
                        @if ($animal->good_with_cats)
                            <x-badge color="accent">{{ __('public.detail.good_with_cats') }}</x-badge>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Descrizione (locale corrente) --}}
            @php
                $description = $animal->getTranslation('description', app()->getLocale(), false);
            @endphp
            @if ($description)
                <div class="mt-6">
                    <h2 class="text-sm font-semibold text-stone-700">{{ __('public.detail.about') }}</h2>
                    <p class="mt-2 whitespace-pre-line text-stone-600">{{ $description }}</p>
                </div>
            @endif

            {{-- CTA --}}
            <div class="mt-8 rounded-2xl bg-gradient-to-br from-brand-600 to-brand-700 p-6 text-white shadow-sm">
                <h2 class="text-lg font-semibold">{{ __('public.detail.cta_title', ['name' => $animal->name]) }}</h2>
                <p class="mt-1 text-sm text-brand-100">{{ __('public.detail.cta_body') }}</p>
                <x-button :href="$applyUrl" variant="accent" size="lg" class="mt-4">
                    {{ __('public.detail.cta_button') }}
                </x-button>
            </div>

            {{-- Ente --}}
            @if ($animal->organization)
                <a href="{{ route('organizations.show', $animal->organization) }}"
                   class="mt-5 flex items-center gap-3 rounded-2xl border border-brand-100 bg-white p-4 transition hover:border-brand-200 hover:shadow-sm">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-100 text-brand-600" aria-hidden="true">
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M3 4a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1h-3v-4H7v4H4a1 1 0 0 1-1-1V4Zm3 2h2v2H6V6Zm6 0h2v2h-2V6ZM6 9h2v2H6V9Zm6 0h2v2h-2V9Z"/></svg>
                    </span>
                    <span class="min-w-0">
                        <span class="block text-xs text-stone-400">{{ __('public.detail.managed_by') }}</span>
                        <span class="block truncate font-medium text-stone-800">{{ $animal->organization->name }}</span>
                    </span>
                    <span class="ml-auto text-brand-500" aria-hidden="true">&rarr;</span>
                </a>
            @endif
        </div>
    </div>
</div>
