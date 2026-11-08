@props([
    'animal',
])

@php
    /** @var \App\Models\Animal $animal */
    $thumb = $animal->getFirstMediaUrl('photos', 'thumb')
        ?: $animal->getFirstMediaUrl('avatar', 'thumb');
    $organization = $animal->organization;
    $location = collect([$organization?->city, $organization?->region])->filter()->implode(', ');
@endphp

<div class="group relative flex h-full flex-col overflow-hidden rounded-2xl border border-brand-100 bg-white shadow-sm transition duration-200 hover:-translate-y-1 hover:border-brand-200 hover:shadow-md focus-within:ring-2 focus-within:ring-brand-500 focus-within:ring-offset-2">
    {{-- Toggle preferiti: sibling del link (mai <button> annidato in <a>), --}}
    {{-- sopra l'immagine e sopra l'overlay-link (z-20 > z-10). --}}
    <div class="absolute right-3 top-3 z-20">
        <livewire:favorite-button :animal="$animal" :key="'fav-'.$animal->id" />
    </div>

    {{-- Overlay-link a piena card: rende cliccabile l'intera superficie senza --}}
    {{-- annidare interattivi. Il toggle preferiti resta sopra (z superiore). --}}
    <a href="{{ route('animals.show', $animal) }}" wire:navigate.hover
       class="absolute inset-0 z-10 rounded-2xl focus:outline-none"
       aria-label="{{ $animal->name }}"></a>

    <div class="relative aspect-[4/3] overflow-hidden bg-brand-100">
        @if ($thumb)
            <img src="{{ $thumb }}"
                 alt="{{ __('public.animal.photo_alt', ['name' => $animal->name]) }}"
                 loading="lazy"
                 class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
        @else
            <div class="flex h-full w-full items-center justify-center text-brand-300" aria-hidden="true">
                <svg class="h-16 w-16" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M8.5 5.5a2 2 0 1 1-4 0 2 2 0 0 1 4 0Zm7 0a2 2 0 1 1 4 0 2 2 0 0 1-4 0ZM5 11a2 2 0 1 1 0-4 2 2 0 0 1 0 4Zm14 0a2 2 0 1 1 0-4 2 2 0 0 1 0 4Zm-7 1c2 0 3.6 1.3 4.6 3 .8 1.3 1.4 2.7-.1 3.6-.9.6-2.2.1-3.3-.2a4.7 4.7 0 0 0-2.4 0c-1.1.3-2.4.8-3.3.2-1.5-.9-.9-2.3-.1-3.6C8.4 13.3 10 12 12 12Z"/>
                </svg>
            </div>
        @endif

        @if ($animal->featured)
            <span class="absolute left-3 top-3 inline-flex items-center gap-1 rounded-full bg-white/95 px-2.5 py-0.5 text-xs font-semibold text-brand-700 shadow-sm ring-1 ring-brand-200">
                &#9733; {{ __('public.animal.featured') }}
            </span>
        @endif
    </div>

    <div class="flex flex-1 flex-col gap-3 p-4">
        <div class="flex items-start justify-between gap-2">
            <h3 class="text-lg font-semibold text-stone-800 transition group-hover:text-brand-700">
                {{ $animal->name }}
            </h3>
        </div>

        <div class="flex flex-wrap gap-1.5">
            <x-badge color="brand">{{ $animal->species->getLabel() }}</x-badge>
            <x-badge color="stone">{{ $animal->sex->getLabel() }}</x-badge>
            @if ($animal->size)
                <x-badge color="stone">{{ $animal->size->getLabel() }}</x-badge>
            @endif
        </div>

        @if ($animal->breed)
            <p class="text-sm text-stone-500">{{ $animal->breed }}</p>
        @endif

        <div class="mt-auto flex items-center gap-1.5 pt-2 text-xs text-stone-500">
            <svg class="h-4 w-4 shrink-0 text-brand-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M9.69 18.933c.11.058.204.1.256.14.052-.04.146-.082.256-.14a12.14 12.14 0 0 0 3.394-2.63C15.14 14.606 17 11.777 17 8A7 7 0 1 0 3 8c0 3.777 1.86 6.606 3.404 8.303a12.14 12.14 0 0 0 3.286 2.63ZM10 10.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z" clip-rule="evenodd" />
            </svg>
            <span class="truncate">
                {{ $organization?->name }}@if ($location) &middot; {{ $location }}@endif
            </span>
        </div>
    </div>
</div>
