{{--
 | Scheda pubblica di un animale (App\Livewire\AnimalDetail, UI-6c), direzione "B · Pop
 | affettuoso", tutta a token (themeable).
 |
 | Desktop (≥ lg): a sinistra galleria e schede informative, a destra intestazione e
 | pannello azioni che resta visibile scorrendo (sticky sotto l'header). Mobile: galleria,
 | intestazione, schede; il pannello vive in un bottom sheet (x-ui.drawer side=bottom)
 | aperto dalla barra fissa in fondo.
 |
 | Il pannello (candidatura e affido, due componenti Livewire figli) esiste una volta sola:
 | Alpine lo sposta nel DOM tra la colonna desktop e il bottom sheet al cambio di
 | breakpoint, così i figli non sono mai duplicati e tengono il loro stato. La pagina non ha
 | azioni proprie e non si ri-renderizza; wire:ignore sui due contenitori lo garantisce
 | comunque. Prenotazione e contatto stanno in due drawer (laterali su desktop, bottom sheet
 | sotto i 640 px), aperti dai bottoni del pannello.
 |
 | Galleria: x-ui.gallery con la prima foto come LCP (priority); senza foto, la foto
 | profilo o la zampa con le stesse proporzioni (nessun CLS).
 | Transizione animata card → scheda (UI-7): galleria (o foto di riserva) con il nome di View
 | Transition dell'animale (App\Support\Site\AnimalPhotoTransition), lo stesso della sua
 | card: con wire:navigate la foto vola dalla card a qui. Gli animali simili escludono
 | questo animale, quindi i nomi restano unici nella pagina.
 | Reveal allo scroll (x-reveal) sulle sezioni sotto la galleria e sulle card simili: mai
 | sopra la piega, mai senza JS o con reduced-motion (resources/js/ui/motion.js).
--}}
@use('App\Enums\OrganizationStatus')
@use('App\Support\Site\AnimalPhotoTransition')

@php
    $name = $animal->name;
    $organization = $animal->organization;
    $location = collect([$organization->city, $organization->region])->filter(filled(...))->implode(', ');

    $traits = array_values(array_filter([
        $animal->species->getLabel(),
        $animal->sex->getLabel(),
        $age,
        $animal->size ? __('page_animal.header.size', ['size' => mb_strtolower($animal->size->getLabel())]) : null,
    ], filled(...)));

    // Compatibilità tri-stato (null = l'ente non lo sa): testo + icona, mai il solo colore.
    $compatibility = static fn (?bool $value): array => match ($value) {
        true => ['circle-check', __('page_animal.facts.compatible.yes')],
        false => ['circle-x', __('page_animal.facts.compatible.no')],
        null => ['circle-help', __('page_animal.facts.compatible.unknown')],
    };
    $health = static fn (bool $done): array => $done
        ? ['circle-check', __('page_animal.facts.done')]
        : ['circle-help', __('page_animal.facts.not_done')];
    $notSpecified = __('page_animal.facts.not_specified');

    // Larghezza mostrata della foto: colonna di 7/12 (max-w-page, gap-x-12) su desktop.
    $gallerySizes = '(min-width: 1240px) 675px, (min-width: 1024px) 55vw, calc(100vw - 2rem)';
@endphp

<div
    data-animal-detail
    x-data="{
        query: null,
        onChange: null,
        init() {
            this.query = window.matchMedia('(min-width: 64rem)');
            this.onChange = () => this.place();
            this.query.addEventListener('change', this.onChange);
            {{-- Dopo l'inizializzazione di tutto l'albero: spostare un nodo mentre Alpine lo
                 percorre potrebbe saltarne una parte. --}}
            this.$nextTick(() => this.place());
        },
        destroy() {
            this.query?.removeEventListener('change', this.onChange);
        },
        place() {
            const desktop = this.query.matches;
            const panel = this.$root.querySelector('[data-animal-actions]');
            const host = this.$root.querySelector(desktop ? '[data-animal-actions-dock]' : '[data-animal-actions-sheet]');

            if (desktop) {
                this.$dispatch('close-drawer', 'animal-actions');
            }

            if (panel && host && panel.parentElement !== host) {
                host.append(panel);
            }
        },
    }"
>
    <div class="mx-auto flex w-full max-w-page flex-col gap-section px-gutter pt-6 pb-section">
        <div class="grid gap-8 lg:grid-cols-12 lg:grid-rows-[auto_1fr] lg:gap-x-12 lg:gap-y-section">
            {{-- Galleria (prima foto = LCP) --}}
            <div class="min-w-0 lg:col-span-7" data-animal-gallery>
                @if ($photos->isNotEmpty())
                    <x-ui.gallery id="animal-gallery" :media="$photos" :name="$name" priority :sizes="$gallerySizes" :transition-name="AnimalPhotoTransition::name($animal)" />
                @else
                    <x-ui.picture
                        :media="$avatar"
                        conversion="hero"
                        :alt="__('public.animal.photo_alt', ['name' => $name])"
                        :sizes="$gallerySizes"
                        priority
                        data-animal-photo-fallback
                        wire:transition.navigate="{{ AnimalPhotoTransition::name($animal) }}"
                        data-photo-transition=""
                    />
                @endif
            </div>

            {{-- Intestazione e pannello azioni (sticky su desktop) --}}
            <div class="flex min-w-0 flex-col gap-8 lg:col-span-5 lg:row-span-2">
                <header class="flex flex-col gap-5" data-animal-header>
                    @if ($animal->featured)
                        <p><x-ui.badge variant="stamp" icon="star" class="-rotate-2">{{ __('public.animal.featured') }}</x-ui.badge></p>
                    @endif

                    <div class="flex items-start justify-between gap-4">
                        <div class="flex min-w-0 flex-col gap-2">
                            <h1 class="text-h1 [overflow-wrap:anywhere]">{{ $name }}</h1>
                            @if (filled($animal->breed))
                                <p class="text-lead text-fg-muted [overflow-wrap:anywhere]">{{ $animal->breed }}</p>
                            @endif
                        </div>
                        <div class="shrink-0 rounded-full bg-surface p-1" data-animal-favorite>
                            <livewire:favorite-button :animal="$animal" :favorited="in_array($animal->id, $favoriteIds, true)" :key="'fav-detail-'.$animal->id" />
                        </div>
                    </div>

                    <ul role="list" aria-label="{{ __('page_animal.header.traits', ['name' => $name]) }}" class="flex flex-wrap gap-2" data-animal-traits>
                        @foreach ($traits as $trait)
                            <li><x-ui.badge>{{ $trait }}</x-ui.badge></li>
                        @endforeach
                    </ul>

                    <div class="flex flex-col gap-2 text-fg-muted">
                        @if ($location !== '')
                            <p class="flex items-center gap-2" data-animal-location>
                                <x-ui.icon name="map-pin" class="shrink-0" />
                                <span class="sr-only">{{ __('page_animal.header.location') }}</span>
                                <span class="min-w-0">{{ $location }}</span>
                            </p>
                        @endif
                        <p class="flex items-center gap-2" data-animal-organization>
                            <x-ui.icon name="house" class="shrink-0" />
                            <span class="sr-only">{{ __('page_animal.header.organization') }}</span>
                            <x-ui.link :href="route('organizations.show', $organization)" wire:navigate.hover class="min-w-0 [overflow-wrap:anywhere]">{{ $organization->name }}</x-ui.link>
                        </p>
                    </div>
                </header>

                <aside aria-labelledby="animal-actions-title" class="hidden lg:sticky lg:top-24 lg:block" data-animal-actions-dock wire:ignore>
                    {{-- Il pannello: su mobile Alpine lo sposta nel bottom sheet qui sotto. --}}
                    <div class="flex flex-col gap-6 rounded-panel bg-surface p-6 [dialog_&]:rounded-none [dialog_&]:bg-transparent [dialog_&]:p-0" data-animal-actions>
                        {{-- Nel bottom sheet titolo e presentazione sono quelli del drawer. --}}
                        <div class="flex flex-col gap-2 [dialog_&]:hidden">
                            <h2 id="animal-actions-title" class="text-h4">{{ __('public.detail.cta_title', ['name' => $name]) }}</h2>
                            <p class="text-fg-muted">{{ __('public.detail.cta_body') }}</p>
                        </div>

                        <livewire:animal-detail.adoption-application-form :animal="$animal" :key="'apply-'.$animal->id" />

                        <livewire:animal-detail.foster-request-form :animal="$animal" :key="'foster-'.$animal->id" />

                        <div class="flex flex-col gap-3 border-t-2 border-line pt-6">
                            <h3 class="text-h5">{{ __('page_animal.actions.meet_title') }}</h3>
                            <p class="text-small text-fg-muted">{{ __('page_animal.actions.meet_body', ['name' => $name]) }}</p>
                            <x-ui.button variant="secondary" icon="calendar-days" class="w-full" aria-haspopup="dialog" x-on:click="$dispatch('open-drawer', 'animal-booking')" data-animal-open="booking">
                                {{ __('page_animal.actions.book') }}
                            </x-ui.button>
                            <x-ui.button variant="secondary" icon="message-circle" class="w-full" aria-haspopup="dialog" x-on:click="$dispatch('open-drawer', 'animal-contact')" data-animal-open="contact">
                                {{ __('page_animal.actions.contact') }}
                            </x-ui.button>
                        </div>
                    </div>
                </aside>
            </div>

            {{-- Presentazione, schede informative, ente --}}
            <div class="flex min-w-0 flex-col gap-section lg:col-span-7">
                <section aria-labelledby="animal-about-title" class="flex flex-col gap-5" data-animal-about x-reveal>
                    <x-ui.section-heading id="animal-about-title" size="h3" :reveal="false" :title="__('page_animal.about.title', ['name' => $name])" />
                    @if ($description !== null)
                        <p class="max-w-prose whitespace-pre-line text-lead [overflow-wrap:anywhere]" @if ($description->differsFrom($locale)) lang="{{ $description->locale }}" @endif data-animal-description>{{ $description->text }}</p>
                    @else
                        <p class="max-w-prose text-fg-muted">{{ __('page_animal.about.empty', ['name' => $name]) }}</p>
                    @endif
                </section>

                <section aria-labelledby="animal-facts-title" class="flex flex-col gap-5" data-animal-facts x-reveal>
                    <x-ui.section-heading id="animal-facts-title" size="h3" :reveal="false" :title="__('page_animal.facts.title', ['name' => $name])" />

                    <x-ui.tabs id="animal-facts" selected="profile">
                        <x-ui.tabs.list :label="__('page_animal.facts.tabs', ['name' => $name])">
                            <x-ui.tabs.tab name="profile">{{ __('page_animal.facts.tab.profile') }}</x-ui.tabs.tab>
                            <x-ui.tabs.tab name="character">{{ __('page_animal.facts.tab.character') }}</x-ui.tabs.tab>
                            <x-ui.tabs.tab name="compatibility">{{ __('page_animal.facts.tab.compatibility') }}</x-ui.tabs.tab>
                            <x-ui.tabs.tab name="health">{{ __('page_animal.facts.tab.health') }}</x-ui.tabs.tab>
                        </x-ui.tabs.list>

                        <x-ui.tabs.panel name="profile" class="rounded-panel bg-surface p-3 sm:p-4">
                            <dl class="grid gap-3 sm:grid-cols-2">
                                <x-site.animal.fact :label="__('page_animal.facts.species')">{{ $animal->species->getLabel() }}</x-site.animal.fact>
                                <x-site.animal.fact :label="__('page_animal.facts.breed')">{{ filled($animal->breed) ? $animal->breed : $notSpecified }}</x-site.animal.fact>
                                <x-site.animal.fact :label="__('page_animal.facts.sex')">{{ $animal->sex->getLabel() }}</x-site.animal.fact>
                                <x-site.animal.fact :label="__('page_animal.facts.age')">{{ $age ?? $notSpecified }}</x-site.animal.fact>
                                <x-site.animal.fact :label="__('page_animal.facts.size')">{{ $animal->size?->getLabel() ?? $notSpecified }}</x-site.animal.fact>
                            </dl>
                        </x-ui.tabs.panel>

                        <x-ui.tabs.panel name="character" class="rounded-panel bg-surface p-3 sm:p-4">
                            <dl class="grid gap-3 sm:grid-cols-2">
                                <x-site.animal.fact :label="__('page_animal.facts.temperament')">{{ filled($animal->temperament) ? $animal->temperament : $notSpecified }}</x-site.animal.fact>
                                <x-site.animal.fact :label="__('page_animal.facts.energy')">{{ $animal->energy_level?->getLabel() ?? $notSpecified }}</x-site.animal.fact>
                            </dl>
                        </x-ui.tabs.panel>

                        <x-ui.tabs.panel name="compatibility" class="flex flex-col gap-3 rounded-panel bg-surface p-3 sm:p-4">
                            <dl class="grid gap-3 sm:grid-cols-3">
                                @foreach (['kids' => $animal->good_with_kids, 'dogs' => $animal->good_with_dogs, 'cats' => $animal->good_with_cats] as $key => $value)
                                    <x-site.animal.fact :label="__('page_animal.facts.'.$key)" :icon="$compatibility($value)[0]" :data-compatibility="$key">{{ $compatibility($value)[1] }}</x-site.animal.fact>
                                @endforeach
                            </dl>
                            <p class="px-2 pb-1 text-small text-fg-muted">{{ __('page_animal.facts.compatibility_note') }}</p>
                        </x-ui.tabs.panel>

                        <x-ui.tabs.panel name="health" class="flex flex-col gap-3 rounded-panel bg-surface p-3 sm:p-4">
                            <dl class="grid gap-3 sm:grid-cols-3">
                                @foreach (['vaccinated' => $animal->vaccinated, 'sterilized' => $animal->sterilized, 'dewormed' => $animal->dewormed] as $key => $value)
                                    <x-site.animal.fact :label="__('page_animal.facts.'.$key)" :icon="$health((bool) $value)[0]" :data-health="$key">{{ $health((bool) $value)[1] }}</x-site.animal.fact>
                                @endforeach
                            </dl>
                            <p class="px-2 pb-1 text-small text-fg-muted">{{ __('page_animal.facts.health_note') }}</p>
                        </x-ui.tabs.panel>
                    </x-ui.tabs>
                </section>

                <section aria-labelledby="animal-organization-title" class="flex flex-col gap-5" data-animal-organization-section x-reveal>
                    <x-ui.section-heading id="animal-organization-title" size="h3" :reveal="false" :title="__('page_animal.organization.title', ['name' => $name])" />
                    <x-ui.organization-card
                        :name="$organization->name"
                        :href="route('organizations.show', $organization)"
                        :city="$organization->city"
                        :region="$organization->region"
                        :type="$organization->type->getLabel()"
                        :verified="$organization->status === OrganizationStatus::Verified"
                        heading-level="3"
                    />
                    @if ($organizationDescription !== null)
                        <p class="line-clamp-6 max-w-prose whitespace-pre-line text-fg-muted [overflow-wrap:anywhere]" @if ($organizationDescription->differsFrom($locale)) lang="{{ $organizationDescription->locale }}" @endif data-organization-description>{{ $organizationDescription->text }}</p>
                    @endif
                </section>
            </div>
        </div>

        {{-- Animali simili (App\Actions\Public\FindSimilarAnimals) --}}
        @if ($similarAnimals->isNotEmpty())
            <section aria-labelledby="animal-similar-title" class="flex flex-col gap-8" data-animal-similar>
                <x-ui.section-heading id="animal-similar-title" :title="__('page_animal.similar.title')" :description="__('page_animal.similar.description')">
                    <x-slot:action>
                        <x-ui.button variant="secondary" :href="route('catalog')" wire:navigate.hover icon-trailing="arrow-right">{{ __('page_animal.similar.browse') }}</x-ui.button>
                    </x-slot:action>
                </x-ui.section-heading>

                <ul role="list" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($similarAnimals as $similar)
                        <li>
                            <x-site.animal-card
                                :animal="$similar"
                                :favorited="in_array($similar->id, $favoriteIds, true)"
                                :tone="['sun', 'peach', 'mint'][$loop->index % 3]"
                                heading-level="3"
                            />
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>

    {{-- Prenotazione e contatto: drawer laterali su desktop, bottom sheet sotto i 640 px. --}}
    <x-ui.drawer
        name="animal-booking"
        sheet
        icon="calendar-days"
        :title="__('appointments.booking.cta_title')"
        :description="__('appointments.booking.cta_body', ['name' => $name])"
    >
        <livewire:animal-detail.appointment-booking :animal="$animal" :key="'booking-'.$animal->id" />
    </x-ui.drawer>

    <x-ui.drawer
        name="animal-contact"
        sheet
        icon="message-circle"
        :title="__('messaging.contact.cta_title')"
        :description="__('messaging.contact.cta_body', ['name' => $name])"
    >
        <livewire:animal-detail.contact-organization-form :animal="$animal" :key="'contact-'.$animal->id" />
    </x-ui.drawer>

    {{-- Mobile: il pannello azioni in un bottom sheet, aperto dalla barra in fondo. --}}
    <x-ui.drawer
        name="animal-actions"
        side="bottom"
        :title="__('page_animal.actions.sheet_title', ['name' => $name])"
        :description="__('page_animal.actions.sheet_description')"
    >
        <div data-animal-actions-sheet wire:ignore></div>
    </x-ui.drawer>

    {{-- Barra fissa in fondo (mobile): resta in vista scorrendo la scheda e si ferma sopra il
         footer. scroll-padding sull'html: un elemento con il focus non finisce sotto la barra
         (WCAG 2.4.11). --}}
    <div class="sticky bottom-0 z-30 border-t-2 border-line bg-canvas lg:hidden max-lg:[html:has(&)]:scroll-pb-24" data-animal-actions-bar>
        <div class="mx-auto flex max-w-page items-center gap-3 px-gutter py-3">
            <p class="flex min-w-0 flex-1 flex-col">
                <span class="truncate font-bold">{{ $name }}</span>
                <span class="truncate text-small text-fg-muted">{{ $organization->name }}</span>
            </p>
            <x-ui.button aria-haspopup="dialog" x-on:click="$dispatch('open-drawer', 'animal-actions')" data-animal-actions-open>
                {{ __('page_animal.actions.bar_open') }}
            </x-ui.button>
        </div>
    </div>
</div>
