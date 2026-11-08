<div>
    <section class="mx-auto flex max-w-2xl flex-col items-center px-4 py-20 text-center sm:px-6">
        <div class="flex h-16 w-16 items-center justify-center rounded-full bg-stone-100 text-stone-500">
            <svg class="h-8 w-8" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16ZM8.28 7.22a.75.75 0 0 0-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 1 0 1.06 1.06L10 11.06l1.72 1.72a.75.75 0 1 0 1.06-1.06L11.06 10l1.72-1.72a.75.75 0 0 0-1.06-1.06L10 8.94 8.28 7.22Z" clip-rule="evenodd" />
            </svg>
        </div>

        <h1 class="mt-6 text-3xl font-bold tracking-tight text-stone-900">
            {{ __('donations.public.cancel_title') }}
        </h1>

        <p class="mt-3 text-stone-600">
            {{ __('donations.public.cancel_body') }}
        </p>

        <div class="mt-8">
            <x-button :href="route('organizations.show', $organization)" wire:navigate.hover variant="primary">
                {{ __('donations.public.cancel_retry') }}
            </x-button>
        </div>
    </section>
</div>
