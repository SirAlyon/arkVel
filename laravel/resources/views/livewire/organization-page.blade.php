{{--
 | /enti/{slug} (App\Livewire\OrganizationPage). Il breadcrumb arriva dal layout.
 | - intestazione a blocchi: pannello giallo (in dark una superficie scura) con iniziali,
 |   tipo, badge «Verificato», nome (h1), sede e contatti pubblici; a fianco i numeri
 |   (x-ui.stat: animali in adozione, adozioni concluse se ce ne sono);
 | - «Chi siamo» (descrizione nella lingua corrente, altrimenti in quella in cui è scritta,
 |   con `lang`) e il widget di donazione, se Stripe è configurato
 |   (x-site.organization.donation-widget);
 | - animali dell'ente: griglia di x-site.animal-card (solo publiclyVisible, media in eager
 |   loading, preferiti in una query), x-ui.pagination, stato vuoto; «Sfoglia il catalogo»
 |   apre il catalogo filtrato su questo ente;
 | - «Dove si trova» (UI-7), se l’ente ha una posizione: la località in chiaro e una piccola
 |   x-ui.map con un cerchio sulla zona APPROSSIMATA ($mapArea), mai un punto preciso. La
 |   mappa si carica solo quando sta per entrare in vista.
 | Gerarchia: h1 nome → h2 sezioni (chi siamo, donazione, animali, dove si trova) → h3 card.
--}}
@php
    /** @var \App\Support\Site\TranslatedText|null $description */
    $hasAbout = $description !== null;
    $tones = ['sun', 'peach', 'mint'];
    $catalogUrl = route('catalog', ['organization' => $organization->slug]);
@endphp

<div class="mx-auto flex w-full max-w-page flex-col gap-section px-gutter pt-6 pb-section" data-organization-page>
    {{-- Intestazione --}}
    <div class="grid gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
        <header class="flex min-w-0 flex-col gap-6 rounded-panel bg-block-sun p-panel text-on-block-sun" data-organization-hero>
            <div class="flex flex-wrap items-center gap-4">
                <x-ui.avatar :name="$organization->name" shape="square" size="xl" color="mint" decorative />
                <ul role="list" class="flex flex-wrap gap-2">
                    <li><x-ui.badge class="bg-canvas text-fg">{{ $organization->type->getLabel() }}</x-ui.badge></li>
                    <li><x-ui.badge variant="success" icon="badge-check" data-organization-verified>{{ __('page_organizations.show.verified') }}</x-ui.badge></li>
                </ul>
            </div>

            <div class="flex flex-col gap-3">
                <h1 class="text-h1 [overflow-wrap:anywhere]">{{ $organization->name }}</h1>
                @if ($location !== null)
                    <p class="flex items-center gap-2 text-lead font-semibold">
                        <x-ui.icon name="map-pin" />
                        <span class="sr-only">{{ __('page_organizations.show.location') }}</span>
                        <span class="min-w-0">{{ $location }}</span>
                    </p>
                @endif
            </div>

            @if ($organization->email || $organization->phone || $website !== null)
                <div class="flex flex-col gap-3">
                    <h2 class="sr-only">{{ __('page_organizations.show.contacts_title') }}</h2>
                    <ul role="list" class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:gap-x-6" data-organization-contacts>
                        @if ($organization->email)
                            <li class="flex min-w-0 items-center gap-3">
                                <span class="inline-flex size-10 shrink-0 items-center justify-center rounded-icon bg-canvas text-fg"><x-ui.icon name="mail" /></span>
                                <span class="sr-only">{{ __('page_organizations.show.email') }}</span>
                                <x-ui.link :href="'mailto:'.$organization->email" variant="inherit" class="min-w-0 [overflow-wrap:anywhere]">{{ $organization->email }}</x-ui.link>
                            </li>
                        @endif
                        @if ($organization->phone)
                            <li class="flex min-w-0 items-center gap-3">
                                <span class="inline-flex size-10 shrink-0 items-center justify-center rounded-icon bg-canvas text-fg"><x-ui.icon name="phone" /></span>
                                <span class="sr-only">{{ __('page_organizations.show.phone') }}</span>
                                <x-ui.link :href="'tel:'.$organization->phone" variant="inherit">{{ $organization->phone }}</x-ui.link>
                            </li>
                        @endif
                        @if ($website !== null)
                            <li class="flex min-w-0 items-center gap-3" data-organization-website>
                                <span class="inline-flex size-10 shrink-0 items-center justify-center rounded-icon bg-canvas text-fg"><x-ui.icon name="globe" /></span>
                                <x-ui.link :href="$website" variant="inherit" external>{{ __('public.organization.website') }}</x-ui.link>
                            </li>
                        @endif
                    </ul>
                </div>
            @endif
        </header>

        <section aria-labelledby="organization-stats-title" class="min-w-0">
            <h2 id="organization-stats-title" class="sr-only">{{ __('page_organizations.show.stats_title') }}</h2>
            <ul role="list" class="grid h-full gap-4 sm:grid-cols-2 lg:grid-cols-1" data-organization-stats>
                <li>
                    <x-ui.stat
                        :value="$summary->available"
                        :label="trans_choice('page_organizations.show.stats.available', $summary->available)"
                        icon="paw-print"
                        class="h-full"
                    />
                </li>
                @if ($summary->adopted > 0)
                    <li>
                        <x-ui.stat
                            :value="$summary->adopted"
                            :label="trans_choice('page_organizations.show.stats.adopted', $summary->adopted)"
                            tone="mint"
                            icon="house-heart"
                            class="h-full"
                        />
                    </li>
                @endif
            </ul>
        </section>
    </div>

    {{-- Chi siamo + donazione --}}
    @if ($hasAbout || $donationsEnabled)
        <div @class([
            'grid gap-6',
            'lg:grid-cols-[minmax(0,6fr)_minmax(0,5fr)] lg:items-start' => $hasAbout && $donationsEnabled,
        ])>
            @if ($hasAbout)
                <section aria-labelledby="organization-about-title" class="flex min-w-0 flex-col gap-4 lg:pt-panel" data-organization-about>
                    <h2 id="organization-about-title" class="text-h3 text-fg">{{ __('page_organizations.show.about_title') }}</h2>
                    <p class="max-w-prose whitespace-pre-line text-lead text-fg-muted [overflow-wrap:anywhere]" @if ($description->differsFrom($locale)) lang="{{ $description->locale }}" @endif data-organization-description>{{ $description->text }}</p>
                </section>
            @endif

            @if ($donationsEnabled)
                <x-site.organization.donation-widget
                    :organization="$organization"
                    :presets="$donationPresets"
                    :guest="$isGuestDonor"
                    :monthly="$canDonateMonthly"
                    :error="$donationError"
                    :attempts="$donationAttempts"
                    :amount="$donationAmount"
                    @class(['lg:max-w-3xl' => ! $hasAbout])
                />
            @endif
        </div>
    @endif

    {{-- Animali dell'ente --}}
    <section id="organization-animals" aria-labelledby="organization-animals-title" class="flex scroll-mt-24 flex-col gap-8" data-organization-animals>
        <x-ui.section-heading
            id="organization-animals-title"
            size="h3"
            :title="__('page_organizations.show.animals_title')"
            :description="trans_choice('public.organization.animals_count', $animals->total(), ['count' => \Illuminate\Support\Number::format($animals->total(), locale: app()->getLocale())])"
        >
            @if ($animals->total() > 0)
                <x-slot:action>
                    <x-ui.button variant="secondary" :href="$catalogUrl" wire:navigate.hover icon-trailing="arrow-right" data-organization-catalog-link>{{ __('page_organizations.show.browse_catalog') }}</x-ui.button>
                </x-slot:action>
            @endif
        </x-ui.section-heading>

        @if ($animals->isNotEmpty())
            <ul
                role="list"
                class="grid grid-cols-1 gap-4 transition-opacity duration-(--duration-fast) ease-out sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
                wire:loading.delay.class="opacity-60"
                wire:target="gotoPage,previousPage,nextPage"
                data-organization-animal-grid
            >
                @foreach ($animals as $animal)
                    <li wire:key="animal-{{ $animal->id }}">
                        <x-site.animal-card
                            :animal="$animal"
                            :favorited="in_array($animal->id, $favoriteIds, true)"
                            :tone="$tones[$loop->index % 3]"
                            :with-location="false"
                            heading-level="3"
                        />
                    </li>
                @endforeach
            </ul>

            <x-ui.pagination :paginator="$animals" mode="livewire" scroll-to="organization-animals" />
        @else
            <x-ui.empty-state
                icon="paw-print"
                block="peach"
                heading-level="3"
                :title="__('page_organizations.show.animals_empty_title')"
                :description="__('public.organization.no_animals')"
            >
                <x-slot:actions>
                    <x-ui.button variant="secondary" :href="route('catalog')" wire:navigate.hover icon-trailing="arrow-right">{{ __('page_organizations.show.browse_catalog') }}</x-ui.button>
                </x-slot:actions>
            </x-ui.empty-state>
        @endif
    </section>

    {{-- Dove si trova: zona approssimata, mai l’indirizzo. --}}
    @if ($mapArea !== null)
        <section aria-labelledby="organization-map-title" class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,3fr)] lg:items-start" data-organization-map>
            <div class="flex min-w-0 flex-col gap-3">
                <h2 id="organization-map-title" class="text-h3 text-fg">{{ __('page_organizations.show.map.title') }}</h2>
                @if ($location !== null)
                    <p class="flex items-center gap-2 text-lead font-semibold text-fg" data-organization-map-location>
                        <x-ui.icon name="map-pin" />
                        <span class="min-w-0">{{ $location }}</span>
                    </p>
                @endif
                <p class="max-w-prose text-body text-fg-muted">{{ __('page_organizations.show.map.description') }}</p>
            </div>

            <x-ui.map
                wire:key="organization-map"
                id="organization-map"
                :label="__('page_organizations.show.map.label', ['name' => $organization->name])"
                :area="$mapArea"
                size="sm"
            />
        </section>
    @endif
</div>
