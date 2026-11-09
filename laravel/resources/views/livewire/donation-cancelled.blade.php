{{--
 | /enti/{slug}/annulla (App\Livewire\DonationCancelled): ritorno da Stripe Checkout senza
 | pagamento. Tono rassicurante, non di errore: nessun addebito, si può riprovare. Azioni:
 | riprovare (pagina dell'ente, dove c'è il widget) o tornare al catalogo.
 | Il blocco è x-ui.empty-state con il titolo h1 (è il contenuto principale della pagina).
 | noindex dalla SEO (PublicPageSeo::donationCancelled); breadcrumb dal layout.
--}}
<div class="mx-auto flex w-full max-w-page flex-col px-gutter pt-6 pb-section" data-donation-landing="cancelled">
    <x-ui.empty-state
        id="donation-cancel"
        icon="circle-x"
        block="peach"
        heading-level="1"
        :title="__('donations.public.cancel_title')"
    >
        <div class="flex flex-col gap-4">
            <p class="text-lead">{{ __('donations.public.cancel_body') }}</p>
            <p class="[overflow-wrap:anywhere]">{{ __('page_organizations.cancelled.help', ['name' => $organization->name]) }}</p>
        </div>

        <x-slot:actions>
            <x-ui.button :href="route('organizations.show', $organization)" wire:navigate.hover icon="rotate-ccw" data-donation-retry>
                {{ __('donations.public.cancel_retry') }}
            </x-ui.button>
            <x-ui.button variant="secondary" :href="route('catalog')" wire:navigate.hover icon-trailing="arrow-right">
                {{ __('page_organizations.cancelled.catalog') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.empty-state>
</div>
