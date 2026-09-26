<x-filament-panels::page>
    @if ($this->favorites->isNotEmpty())
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($this->favorites as $animal)
                @php
                    /** @var \App\Models\Animal $animal */
                    $thumb = $animal->getFirstMediaUrl('photos', 'thumb')
                        ?: $animal->getFirstMediaUrl('avatar', 'thumb');
                    $organization = $animal->organization;
                    $location = collect([$organization?->city, $organization?->region])->filter()->implode(', ');
                @endphp

                <div wire:key="fav-{{ $animal->id }}"
                     class="flex flex-col overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <div class="aspect-[4/3] overflow-hidden bg-gray-100 dark:bg-gray-800">
                        @if ($thumb)
                            <img src="{{ $thumb }}"
                                 alt="{{ __('public.animal.photo_alt', ['name' => $animal->name]) }}"
                                 loading="lazy"
                                 class="h-full w-full object-cover">
                        @else
                            <div class="flex h-full w-full items-center justify-center text-gray-300 dark:text-gray-600" aria-hidden="true">
                                <svg class="h-14 w-14" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M8.5 5.5a2 2 0 1 1-4 0 2 2 0 0 1 4 0Zm7 0a2 2 0 1 1 4 0 2 2 0 0 1-4 0ZM5 11a2 2 0 1 1 0-4 2 2 0 0 1 0 4Zm14 0a2 2 0 1 1 0-4 2 2 0 0 1 0 4Zm-7 1c2 0 3.6 1.3 4.6 3 .8 1.3 1.4 2.7-.1 3.6-.9.6-2.2.1-3.3-.2a4.7 4.7 0 0 0-2.4 0c-1.1.3-2.4.8-3.3.2-1.5-.9-.9-2.3-.1-3.6C8.4 13.3 10 12 12 12Z"/>
                                </svg>
                            </div>
                        @endif
                    </div>

                    <div class="flex flex-1 flex-col gap-3 p-4">
                        <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                            {{ $animal->name }}
                        </h3>

                        <div class="flex flex-wrap gap-1.5 text-xs">
                            <span class="inline-flex items-center rounded-md bg-primary-50 px-2 py-0.5 font-medium text-primary-700 ring-1 ring-inset ring-primary-600/20 dark:bg-primary-500/10 dark:text-primary-400">
                                {{ $animal->species->getLabel() }}
                            </span>
                            @if ($animal->size)
                                <span class="inline-flex items-center rounded-md bg-gray-50 px-2 py-0.5 font-medium text-gray-600 ring-1 ring-inset ring-gray-500/20 dark:bg-white/5 dark:text-gray-300">
                                    {{ $animal->size->getLabel() }}
                                </span>
                            @endif
                        </div>

                        @if ($organization)
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $organization->name }}@if ($location) · {{ $location }}@endif
                            </p>
                        @endif

                        <div class="mt-auto flex items-center gap-2 pt-2">
                            <x-filament::button
                                tag="a"
                                href="{{ route('animals.show', $animal) }}"
                                size="sm"
                                icon="heroicon-m-arrow-top-right-on-square"
                            >
                                {{ __('public.favorites.view') }}
                            </x-filament::button>

                            <x-filament::button
                                color="danger"
                                size="sm"
                                icon="heroicon-m-heart"
                                wire:click="removeFavorite({{ $animal->id }})"
                                wire:loading.attr="disabled"
                                wire:target="removeFavorite({{ $animal->id }})"
                            >
                                {{ __('public.favorites.remove') }}
                            </x-filament::button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <x-filament::section>
            <div class="flex flex-col items-center justify-center gap-3 py-12 text-center">
                <div class="flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 text-gray-400 dark:bg-white/5" aria-hidden="true">
                    <x-filament::icon icon="heroicon-o-heart" class="h-7 w-7" />
                </div>
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                    {{ __('public.favorites.empty_title') }}
                </h3>
                <p class="max-w-sm text-sm text-gray-500 dark:text-gray-400">
                    {{ __('public.favorites.empty_body') }}
                </p>
                <x-filament::button
                    tag="a"
                    href="{{ route('catalog') }}"
                    class="mt-2"
                >
                    {{ __('public.favorites.browse_catalog') }}
                </x-filament::button>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
