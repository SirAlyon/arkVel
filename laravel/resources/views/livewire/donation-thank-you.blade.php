<div>
    <section class="mx-auto flex max-w-2xl flex-col items-center px-4 py-20 text-center sm:px-6">
        <div class="flex h-16 w-16 items-center justify-center rounded-full bg-accent-100 text-accent-700">
            <svg class="h-8 w-8" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
            </svg>
        </div>

        <h1 class="mt-6 text-3xl font-bold tracking-tight text-stone-900">
            {{ __('donations.public.thanks_title') }}
        </h1>

        <p class="mt-3 text-stone-600">
            {{ __('donations.public.thanks_body', ['name' => $organization->name]) }}
        </p>

        <p class="mt-2 text-sm text-stone-500">
            {{ __('donations.public.thanks_pending_note') }}
        </p>

        <div class="mt-8">
            <x-button :href="route('organizations.show', $organization)" wire:navigate.hover variant="outline">
                {{ __('donations.public.back_to_organization') }}
            </x-button>
        </div>
    </section>
</div>
