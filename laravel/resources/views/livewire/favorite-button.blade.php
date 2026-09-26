<div>
    <button
        type="button"
        wire:click="toggle"
        aria-pressed="{{ $favorited ? 'true' : 'false' }}"
        aria-label="{{ $favorited ? __('public.favorites.remove') : __('public.favorites.add') }}"
        title="{{ $favorited ? __('public.favorites.remove') : __('public.favorites.add') }}"
        @class([
            'inline-flex items-center justify-center rounded-full p-2 shadow-sm ring-1 ring-inset transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2',
            'bg-accent-600 text-white ring-accent-600 hover:bg-accent-700' => $favorited,
            'bg-white/95 text-stone-500 ring-brand-200 hover:text-accent-600 hover:ring-accent-200' => ! $favorited,
        ])
    >
        <svg class="h-5 w-5" viewBox="0 0 20 20"
             fill="{{ $favorited ? 'currentColor' : 'none' }}"
             stroke="currentColor" stroke-width="1.6" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round"
                  d="M9.653 16.915 3.42 10.68a3.75 3.75 0 0 1 5.303-5.303l.977.977.977-.977a3.75 3.75 0 1 1 5.303 5.303l-6.234 6.234a.5.5 0 0 1-.707 0Z" />
        </svg>
    </button>
</div>
