{{--
 | Widget di donazione della pagina ente (dentro la vista di App\Livewire\OrganizationPage:
 | wire:model e wire:submit parlano con quel componente). Solo markup: validazione,
 | condizioni (Stripe configurato, mensile solo per gli utenti autenticati) e avvio del
 | Checkout restano nel componente e nelle Action StartDonationCheckout /
 | StartRecurringDonationCheckout.
 |
 | - frequenza: x-ui.radio-group (una tantum / mensile). Agli ospiti la mensile è un radio
 |   disabilitato con la nota «Accedi per donare mensilmente…» e il link di accesso;
 | - importi preimpostati: x-ui.tag con aria-pressed. Impostano `donationAmount` sul client
 |   ($wire, nessuna richiesta), lo stesso campo dell'importo libero, che si aggiorna subito;
 | - importo libero: x-ui.input number con prefisso €; errori sotto i campi e nel riepilogo
 |   (x-ui.error-summary, che prende il focus a ogni tentativo fallito);
 | - invio: spinner del bottone (data-loading) e «Reindirizzamento…» annunciato.
 |
 | Props: `organization` (nome nel titolo), `presets` (euro), `guest`, `monthly` (la
 | mensile è disponibile), `error` (errore del modulo, es. Stripe non configurato),
 | `attempts` (chiave del riepilogo errori).
--}}
@props([
    'organization',
    'presets' => [],
    'guest' => true,
    'monthly' => false,
    'error' => null,
    'attempts' => 0,
    'amount' => '',
])

@use('Illuminate\Support\Number')
@use('App\Actions\Donations\StartDonationCheckout')

@php
    $locale = app()->getLocale();
    $euro = static fn (int|float $value): string => Number::currency($value, in: 'EUR', locale: $locale, precision: 0);
    $fields = $guest
        ? ['donationAmount', 'donorName', 'donorEmail', 'donationMessage']
        : ['donationAmount', 'donationMessage'];
@endphp

<section aria-labelledby="donation-title" {{ $attributes->class('flex min-w-0 flex-col gap-8 rounded-panel bg-surface p-panel text-fg') }} data-donation-widget>
    <header class="flex flex-col gap-4">
        <span class="inline-flex size-14 items-center justify-center rounded-icon-lg bg-block-leaf text-on-block-leaf">
            <x-ui.icon name="hand-heart" size="xl" />
        </span>
        <h2 id="donation-title" class="text-h3 [overflow-wrap:anywhere]">{{ __('donations.public.heading', ['name' => $organization->name]) }}</h2>
        <p class="text-fg-muted">{{ __('donations.public.subheading') }}</p>
    </header>

    <form wire:submit="donate" novalidate class="flex flex-col gap-6" data-donation-form>
        @if (filled($error))
            <x-ui.error-summary :messages="[$error]" wire:key="donation-error-{{ $attempts }}" />
        @else
            <x-ui.error-summary :errors="$errors" :fields="$fields" wire:key="donation-errors-{{ $attempts }}" />
        @endif

        <div class="flex flex-col gap-3">
            <x-ui.radio-group name="donationFrequency" :legend="__('donations.public.frequency_label')" orientation="horizontal">
                <x-ui.radio value="one_off" wire:model="donationFrequency">{{ __('donations.public.frequency_one_off') }}</x-ui.radio>
                <x-ui.radio
                    value="monthly"
                    wire:model="donationFrequency"
                    :disabled="! $monthly"
                    :description="$monthly ? __('page_organizations.donation.monthly_note') : null"
                >{{ __('donations.public.frequency_monthly') }}</x-ui.radio>
            </x-ui.radio-group>

            @unless ($monthly)
                <p class="flex flex-wrap items-baseline gap-x-2 gap-y-1 text-small text-fg-muted" data-donation-monthly-login>
                    <span>{{ __('donations.public.monthly_requires_login') }}</span>
                    <x-ui.link :href="app(\App\Support\Site\AccountLinks::class)->login()" wire:navigate.hover>{{ __('page_organizations.donation.login') }}</x-ui.link>
                </p>
            @endunless
        </div>

        <div class="flex flex-col gap-4">
            <div role="group" aria-labelledby="donation-presets-label" class="flex flex-col gap-3">
                <p id="donation-presets-label" class="font-semibold">{{ __('donations.public.amount_label') }}</p>
                <ul role="list" class="flex flex-wrap gap-2" data-donation-presets>
                    @foreach ($presets as $preset)
                        <li wire:key="preset-{{ $preset }}">
                            <x-ui.tag
                                :pressed="(string) $amount === (string) $preset"
                                x-on:click="$wire.donationAmount = '{{ $preset }}'"
                                x-bind:aria-pressed="String($wire.donationAmount) === '{{ $preset }}' ? 'true' : 'false'"
                                data-donation-preset="{{ $preset }}"
                            >{{ $euro($preset) }}</x-ui.tag>
                        </li>
                    @endforeach
                </ul>
            </div>

            <x-ui.field
                name="donationAmount"
                :label="__('donations.public.custom_amount_label')"
                :help="__('page_organizations.donation.amount_help', [
                    'min' => $euro(StartDonationCheckout::MIN_AMOUNT / 100),
                    'max' => $euro(StartDonationCheckout::MAX_AMOUNT / 100),
                ])"
            >
                <x-ui.input
                    type="number"
                    prefix="€"
                    wire:model="donationAmount"
                    min="{{ StartDonationCheckout::MIN_AMOUNT / 100 }}"
                    max="{{ StartDonationCheckout::MAX_AMOUNT / 100 }}"
                    step="0.01"
                    inputmode="decimal"
                    placeholder="25"
                    class="sm:max-w-60"
                />
            </x-ui.field>
        </div>

        @if ($guest)
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field name="donorName" :label="__('donations.public.name_label')">
                    <x-ui.input wire:model="donorName" autocomplete="name" />
                </x-ui.field>
                <x-ui.field name="donorEmail" :label="__('donations.public.email_label')" required>
                    <x-ui.input type="email" wire:model="donorEmail" autocomplete="email" inputmode="email" spellcheck="false" />
                </x-ui.field>
            </div>
        @endif

        <x-ui.field name="donationMessage" :label="__('donations.public.message_label')">
            <x-ui.textarea wire:model="donationMessage" rows="3" maxlength="500" counter autoresize />
        </x-ui.field>

        <div class="flex flex-col gap-3">
            <x-ui.button
                type="submit"
                size="lg"
                icon="heart"
                class="w-full sm:w-auto sm:self-start"
                wire:loading.attr="data-loading"
                wire:target="donate"
                data-donation-submit
            >{{ __('donations.public.submit') }}</x-ui.button>
            <p role="status" class="text-small font-semibold" data-donation-status>
                <span wire:loading wire:target="donate">{{ __('donations.public.submitting') }}</span>
            </p>
            <p class="flex items-start gap-2 text-small text-fg-muted">
                <x-ui.icon name="lock" size="sm" class="mt-0.5" />
                <span>{{ __('donations.public.secure_note') }}</span>
            </p>
        </div>
    </form>
</section>
