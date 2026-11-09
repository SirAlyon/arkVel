{{--
 | /enti/{slug}/grazie (App\Livewire\DonationThankYou): ritorno da Stripe Checkout dopo una
 | donazione. Non conferma il pagamento (lo fa il webhook): lo dice la nota «in attesa».
 | Pannello menta con il ringraziamento (h1), poi una banda con i passi successivi.
 | noindex dalla SEO (PublicPageSeo::donationThanks); breadcrumb dal layout.
--}}
<div class="mx-auto flex w-full max-w-page flex-col gap-6 px-gutter pt-6 pb-section" data-donation-landing="thanks">
    <section aria-labelledby="donation-thanks-title" class="flex flex-col items-center gap-6 rounded-panel bg-block-mint p-panel text-center text-on-block-mint">
        <span class="inline-flex size-20 items-center justify-center rounded-icon-lg bg-canvas text-fg">
            <x-ui.icon name="hand-heart" size="xl" />
        </span>

        <div class="flex max-w-3xl flex-col items-center gap-4">
            <h1 id="donation-thanks-title" class="text-h1">{{ __('donations.public.thanks_title') }}</h1>
            <p class="text-lead [overflow-wrap:anywhere]">{{ __('donations.public.thanks_body', ['name' => $organization->name]) }}</p>
        </div>

        <p class="flex max-w-prose items-start gap-3 rounded-card bg-canvas px-5 py-4 text-start text-fg" data-donation-pending-note>
            <x-ui.icon name="hourglass" class="mt-0.5" />
            <span>{{ __('donations.public.thanks_pending_note') }}</span>
        </p>

        <div class="flex flex-wrap justify-center gap-3">
            <x-ui.button :href="route('organizations.show', $organization)" wire:navigate.hover icon="arrow-left" data-donation-back>
                {{ __('donations.public.back_to_organization') }}
            </x-ui.button>
        </div>
    </section>

    <x-ui.cta-band tone="inverse" :title="__('page_organizations.thanks.next_title')" icon="paw-print" id="donation-thanks-next">
        {{ __('page_organizations.thanks.next_body') }}
        <x-slot:actions>
            <x-ui.button variant="highlight" size="lg" :href="route('catalog')" wire:navigate.hover icon-trailing="arrow-right">
                {{ __('page_organizations.thanks.catalog') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.cta-band>
</div>
