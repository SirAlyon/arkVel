<div class="mx-auto max-w-6xl px-4 py-12 sm:px-6">
    <header class="max-w-2xl">
        <h1 class="text-3xl font-bold tracking-tight text-stone-900">{{ __('public.catalog.title') }}</h1>
        <p class="mt-2 text-stone-500">{{ __('public.catalog.subtitle') }}</p>
    </header>

    {{-- Ricerca + filtri --}}
    <div class="mt-8 rounded-2xl border border-brand-100 bg-white p-4 shadow-sm sm:p-6">
        <div class="relative">
            <svg class="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-stone-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 1 0 3.4 9.82l3.14 3.13a.75.75 0 1 0 1.06-1.06l-3.13-3.13A5.5 5.5 0 0 0 9 3.5ZM5 9a4 4 0 1 1 8 0 4 4 0 0 1-8 0Z" clip-rule="evenodd" />
            </svg>
            <input
                type="search"
                wire:model.live.debounce.400ms="search"
                placeholder="{{ __('public.catalog.search_placeholder') }}"
                aria-label="{{ __('public.catalog.search_label') }}"
                class="w-full rounded-xl border-stone-200 bg-brand-50/50 py-3 pl-11 pr-4 text-sm text-stone-800 shadow-sm focus:border-brand-500 focus:ring-brand-500"
            >
        </div>

        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
            <x-filter-select :label="__('public.filters.species')" :options="$speciesOptions" wire:model.live="species" />
            <x-filter-select :label="__('public.filters.size')" :options="$sizeOptions" wire:model.live="size" />
            <x-filter-select :label="__('public.filters.sex')" :options="$sexOptions" wire:model.live="sex" />
        </div>
    </div>

    {{-- Barra risultati --}}
    <div class="mt-6 flex items-center justify-between gap-4">
        <p class="text-sm text-stone-500" aria-live="polite">
            {{ trans_choice('public.catalog.result_count', $animals->total(), ['count' => $animals->total()]) }}
        </p>
        @if ($hasActiveFilters)
            <button type="button" wire:click="resetFilters"
                    class="text-sm font-medium text-brand-700 underline-offset-2 hover:underline">
                {{ __('public.catalog.reset_filters') }}
            </button>
        @endif
    </div>

    {{-- Griglia --}}
    @if ($animals->isNotEmpty())
        <div class="mt-4 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @foreach ($animals as $animal)
                <x-animal-card :animal="$animal" :wire:key="'animal-'.$animal->id" />
            @endforeach
        </div>

        <div class="mt-10">
            {{ $animals->onEachSide(1)->links() }}
        </div>
    @else
        <div class="mt-4 flex flex-col items-center justify-center rounded-2xl border border-dashed border-brand-200 bg-white/60 px-6 py-20 text-center">
            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-100 text-brand-500" aria-hidden="true">
                <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <circle cx="11" cy="11" r="7" />
                    <path stroke-linecap="round" d="m20 20-3-3" />
                </svg>
            </div>
            <h2 class="mt-4 text-lg font-semibold text-stone-800">{{ __('public.catalog.empty_title') }}</h2>
            <p class="mt-1 max-w-sm text-sm text-stone-500">{{ __('public.catalog.empty_body') }}</p>
            @if ($hasActiveFilters)
                <x-button type="button" wire:click="resetFilters" variant="outline" size="sm" class="mt-5">
                    {{ __('public.catalog.reset_filters') }}
                </x-button>
            @endif
        </div>
    @endif
</div>
