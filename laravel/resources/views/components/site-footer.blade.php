<footer class="mt-16 border-t border-brand-100 bg-white/60">
    <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
        <div class="flex flex-col gap-8 sm:flex-row sm:items-start sm:justify-between">
            <div class="max-w-sm">
                <div class="flex items-center gap-2 text-lg font-semibold text-brand-800">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-600 text-white" aria-hidden="true">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M8.5 5.5a2 2 0 1 1-4 0 2 2 0 0 1 4 0Zm7 0a2 2 0 1 1 4 0 2 2 0 0 1-4 0ZM5 11a2 2 0 1 1 0-4 2 2 0 0 1 0 4Zm14 0a2 2 0 1 1 0-4 2 2 0 0 1 0 4Zm-7 1c2 0 3.6 1.3 4.6 3 .8 1.3 1.4 2.7-.1 3.6-.9.6-2.2.1-3.3-.2a4.7 4.7 0 0 0-2.4 0c-1.1.3-2.4.8-3.3.2-1.5-.9-.9-2.3-.1-3.6C8.4 13.3 10 12 12 12Z"/>
                        </svg>
                    </span>
                    Ark<span class="text-brand-600">Vel</span>
                </div>
                <p class="mt-3 text-sm leading-relaxed text-stone-500">
                    {{ __('public.footer.tagline') }}
                </p>
            </div>

            <nav class="flex flex-col gap-2 text-sm" aria-label="{{ __('public.footer.links') }}">
                <a href="{{ route('catalog') }}" class="text-stone-600 transition hover:text-brand-700">{{ __('public.nav.catalog') }}</a>
                <a href="{{ route('organizations.index') }}" class="text-stone-600 transition hover:text-brand-700">{{ __('public.nav.organizations') }}</a>
                <a href="/account" class="text-stone-600 transition hover:text-brand-700">{{ __('public.nav.account') }}</a>
            </nav>
        </div>

        <div class="mt-8 border-t border-brand-100 pt-6 text-xs text-stone-400">
            &copy; {{ date('Y') }} ArkVel. {{ __('public.footer.rights') }}
        </div>
    </div>
</footer>
