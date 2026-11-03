<div class="mx-auto max-w-6xl px-4 py-12 sm:px-6">
    <header class="max-w-2xl">
        <h1 class="text-3xl font-bold tracking-tight text-stone-900">{{ __('public.organizations.title') }}</h1>
        <p class="mt-2 text-stone-500">{{ __('public.organizations.subtitle') }}</p>
    </header>

    @if ($organizations->isNotEmpty())
        <div class="mt-8 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($organizations as $organization)
                @php
                    $location = collect([$organization->city, $organization->region])->filter()->implode(', ');
                @endphp
                <a href="{{ route('organizations.show', $organization) }}" wire:key="org-{{ $organization->id }}"
                   class="group flex flex-col rounded-2xl border border-brand-100 bg-white p-5 shadow-sm transition hover:-translate-y-1 hover:border-brand-200 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2">
                    <div class="flex items-start gap-3">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-brand-100 text-brand-600" aria-hidden="true">
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M3 4a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1h-3v-4H7v4H4a1 1 0 0 1-1-1V4Zm3 2h2v2H6V6Zm6 0h2v2h-2V6ZM6 9h2v2H6V9Zm6 0h2v2h-2V9Z"/></svg>
                        </span>
                        <div class="min-w-0">
                            <h2 class="truncate font-semibold text-stone-800 transition group-hover:text-brand-700">{{ $organization->name }}</h2>
                            <p class="mt-0.5 text-sm text-stone-500">{{ $organization->type->getLabel() }}</p>
                        </div>
                    </div>

                    @if ($location)
                        <p class="mt-4 flex items-center gap-1.5 text-sm text-stone-500">
                            <svg class="h-4 w-4 text-brand-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M9.69 18.933c.11.058.204.1.256.14.052-.04.146-.082.256-.14a12.14 12.14 0 0 0 3.394-2.63C15.14 14.606 17 11.777 17 8A7 7 0 1 0 3 8c0 3.777 1.86 6.606 3.404 8.303a12.14 12.14 0 0 0 3.286 2.63ZM10 10.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z" clip-rule="evenodd" /></svg>
                            {{ $location }}
                        </p>
                    @endif

                    <div class="mt-4 border-t border-brand-50 pt-4">
                        <span class="text-sm font-medium text-brand-700">
                            {{ trans_choice('public.organizations.animals_available', $organization->available_animals_count, ['count' => $organization->available_animals_count]) }}
                        </span>
                    </div>
                </a>
            @endforeach
        </div>

        <div class="mt-10">
            {{ $organizations->onEachSide(1)->links() }}
        </div>
    @else
        <p class="mt-8 rounded-2xl border border-dashed border-brand-200 bg-white/60 p-10 text-center text-stone-500">
            {{ __('public.organizations.empty') }}
        </p>
    @endif
</div>
