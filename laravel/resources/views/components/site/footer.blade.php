{{--
 | Footer del sito pubblico (token semantici: funziona in chiaro e in scuro), a blocchi come
 | il resto della direzione B: un pannello surface con logo, tagline, link e la card della
 | newsletter (canvas), poi una riga su canvas con copyright, note legali e preferenze.
 |
 | - link: stesse voci della navigazione principale + account;
 | - newsletter: SEGNAPOSTO onesto. Il modulo è disattivato (fieldset disabled) e il testo
 |   d'aiuto dice che l'iscrizione non è ancora attiva: nessuna logica dietro;
 | - note legali: TODO pagine legali (informativa privacy, termini di servizio). Finché non
 |   esistono le route sono testo con l'etichetta "In arrivo", non link rotti;
 | - preferenze: lingua e, solo per le pagine `themeable`, tema; copyright;
 | - `guest`: variante ospite anche per chi ha fatto l'accesso (anteprima nella styleguide).
--}}
@props([
    'themeable' => false,
    'guest' => false,
])

@inject('navigation', 'App\Support\Site\SiteNavigation')
@inject('accountLinks', 'App\Support\Site\AccountLinks')

@php
    $guest = $guest || auth()->guest();
    $register = $guest ? $accountLinks->register() : null;
@endphp

<footer class="mt-section" data-site-footer>
    <div class="mx-auto max-w-page px-gutter">
        <div class="grid gap-10 rounded-panel bg-surface p-panel text-fg md:grid-cols-12">
            <div class="flex flex-col gap-4 md:col-span-6 lg:col-span-4">
                <x-site.logo class="self-start" />
                <p class="max-w-sm text-small text-fg-muted">{{ __('public.footer.tagline') }}</p>
            </div>

            <nav aria-label="{{ __('public.footer.links') }}" class="grid grid-cols-2 gap-8 md:col-span-6 lg:col-span-4">
                <div class="flex flex-col gap-3">
                    <h2 class="text-h5">{{ __('public.footer.explore') }}</h2>
                    <ul class="flex flex-col gap-2">
                        @foreach ($navigation->primary() as $item)
                            <li><x-ui.link :href="$item->url" wire:navigate.hover>{{ $item->label }}</x-ui.link></li>
                        @endforeach
                    </ul>
                </div>

                <div class="flex flex-col gap-3">
                    <h2 class="text-h5">{{ __('public.footer.account') }}</h2>
                    <ul class="flex flex-col gap-2">
                        <li><x-ui.link :href="$accountLinks->account()">{{ __('public.account.dashboard') }}</x-ui.link></li>
                        @if ($guest)
                            <li><x-ui.link :href="$accountLinks->login()">{{ __('public.account.login') }}</x-ui.link></li>
                            @if ($register)
                                <li><x-ui.link :href="$register">{{ __('public.account.register') }}</x-ui.link></li>
                            @endif
                        @endif
                    </ul>
                </div>
            </nav>

            <section aria-labelledby="footer-newsletter" class="flex flex-col gap-3 rounded-card bg-canvas p-6 md:col-span-12 lg:col-span-4" data-newsletter>
                <div class="flex flex-wrap items-center gap-2">
                    <h2 id="footer-newsletter" class="text-h5">{{ __('public.footer.newsletter.title') }}</h2>
                    <x-ui.badge variant="info">{{ __('public.footer.coming_soon') }}</x-ui.badge>
                </div>
                <p class="text-small text-fg-muted">{{ __('public.footer.newsletter.body') }}</p>
                <form x-on:submit.prevent>
                    <fieldset disabled class="flex flex-col gap-3">
                        <x-ui.field name="newsletter_email" :label="__('public.footer.newsletter.email')" :help="__('public.footer.newsletter.help')">
                            <x-ui.input type="email" icon="mail" autocomplete="email" disabled />
                        </x-ui.field>
                        <x-ui.button type="submit" size="sm" disabled class="self-start">{{ __('public.footer.newsletter.submit') }}</x-ui.button>
                    </fieldset>
                </form>
            </section>
        </div>

        <div class="flex flex-col gap-6 py-8 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex flex-col gap-3 text-small text-fg-muted">
                <p>{{ __('public.footer.copyright', ['year' => now()->year]) }} {{ __('public.footer.rights') }}</p>
                {{-- TODO(pagine legali): sostituire con link alle route privacy e termini quando esistono. --}}
                <ul aria-label="{{ __('public.footer.legal') }}" class="flex flex-wrap items-center gap-x-6 gap-y-2" data-legal-links>
                    <li class="flex items-center gap-2">{{ __('public.footer.privacy') }} <x-ui.badge>{{ __('public.footer.coming_soon') }}</x-ui.badge></li>
                    <li class="flex items-center gap-2">{{ __('public.footer.terms') }} <x-ui.badge>{{ __('public.footer.coming_soon') }}</x-ui.badge></li>
                </ul>
            </div>

            <div class="flex flex-wrap items-center gap-3" data-footer-preferences>
                <x-ui.language-switcher />
                @if ($themeable)
                    <x-ui.theme-toggle />
                @endif
            </div>
        </div>
    </div>
</footer>
