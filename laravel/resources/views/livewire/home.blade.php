{{--
 | Home pubblica (UI-6a, direzione B · Pop affettuoso, §1-bis del piano UI).
 |
 | Struttura: hero a blocchi (blocco giallo con bollino ruotato, H1 e ricerca a pillola;
 | blocco foto pesca e due numeri) → vetrina «In cerca di casa» con i chip delle specie e
 | la card finale del catalogo → come funziona in 4 passi → storie (segnaposto onesto) →
 | banda donazione verde → invito agli enti. Titoli h1 → h2 → h3 senza salti.
 |
 | - Header trasparente (layoutData transparentHeader): la hero lascia in alto lo spazio
 |   dell'header fisso (h-18 + bordo) con pt-22.
 | - Dark mode: block-sun diventa una superficie scura (token), il giallo resta accento.
 | - I chip filtrano la vetrina sul posto (bottoni con aria-pressed, x-ui.tag) e aggiornano
 |   solo l'island `showcase`; l'esito è annunciato da una regione live.
 | - Immagine LCP: la foto della hero (priority). Se manca, la priorità passa alla prima
 |   card della vetrina. Nell'island si usa solo $spotlightId (bloccato al mount), così un
 |   chip non ricalcola la hero.
 | - Ricerca della hero con label visibile (x-ui.field), come quella del catalogo.
--}}
@use('App\Enums\Species')

@php
    $stats = $this->stats;
    $spotlight = $this->spotlight;
    $spotlightMedia = $spotlight?->getFirstMedia('photos') ?? $spotlight?->getFirstMedia('avatar');
    $steps = ['browse', 'apply', 'meet', 'welcome'];
    $stepTones = ['bg-highlight text-on-highlight', 'bg-block-peach text-on-block-peach', 'bg-block-mint text-on-block-mint', 'bg-block-leaf text-on-block-leaf'];
@endphp

<div class="flex flex-col gap-section pb-section" data-home>
    {{-- Hero a blocchi --}}
    <section aria-labelledby="home-title" class="mx-auto w-full max-w-page px-gutter pt-22" data-home-hero>
        <div class="grid gap-4 lg:grid-cols-12">
            <div class="flex flex-col justify-center gap-6 rounded-panel bg-block-sun p-panel text-on-block-sun lg:col-span-7" data-home-hero-block>
                <p>
                    <x-ui.badge variant="stamp" icon="heart" class="-rotate-2">{{ __('page_home.hero.badge') }}</x-ui.badge>
                </p>

                <h1 id="home-title" class="text-display text-balance">{{ __('page_home.hero.title') }}</h1>

                <p class="max-w-xl text-lead">{{ __('page_home.hero.lead') }}</p>

                <form
                    role="search"
                    method="get"
                    action="{{ route('catalog') }}"
                    aria-label="{{ __('page_home.hero.search_label') }}"
                    class="flex w-full max-w-xl flex-col gap-3 sm:flex-row sm:items-end"
                    data-home-search
                >
                    {{-- Label visibile come nel catalogo (stesso testo del nome della ricerca). --}}
                    <x-ui.field name="q" for="home-search" :label="__('page_home.hero.search_label')" class="sm:flex-1">
                        <x-ui.input
                            type="search"
                            icon="search"
                            :placeholder="__('page_home.hero.search_placeholder')"
                            maxlength="{{ \App\Support\Catalog\ResolveCatalogFilters::MAX_SEARCH_LENGTH }}"
                            enterkeyhint="search"
                        />
                    </x-ui.field>
                    <x-ui.button type="submit" icon="search" class="w-full sm:w-auto">{{ __('page_home.hero.search_submit') }}</x-ui.button>
                </form>

                <p>
                    <x-ui.link :href="route('catalog')" variant="inherit" wire:navigate.hover>{{ __('page_home.hero.browse') }}</x-ui.link>
                </p>
            </div>

            <div class="flex flex-col gap-4 md:grid md:grid-cols-2 lg:col-span-5 lg:flex">
                <div class="flex flex-col gap-3 rounded-panel bg-block-peach p-3 text-on-block-peach lg:flex-1" data-home-spotlight>
                    <div class="relative aspect-3/2 overflow-hidden rounded-media lg:aspect-auto lg:min-h-64 lg:flex-1">
                        <div class="absolute inset-0">
                            @if ($spotlight !== null)
                                <x-ui.picture
                                    :media="$spotlightMedia"
                                    conversion="hero"
                                    fill
                                    :rounded="false"
                                    tone="peach"
                                    :alt="__('page_home.hero.spotlight_alt', ['name' => $spotlight->name])"
                                    sizes="(min-width: 1240px) 500px, (min-width: 1024px) 40vw, (min-width: 768px) 50vw, 100vw"
                                    priority
                                />
                            @else
                                <x-ui.picture fill :rounded="false" tone="mint" decorative />
                            @endif
                        </div>
                    </div>

                    @if ($spotlight !== null)
                        <div class="flex items-center justify-between gap-3 ps-3" data-home-spotlight-caption>
                            <p class="flex min-w-0 flex-col">
                                <span class="text-small font-semibold">{{ __('page_home.hero.spotlight_label') }}</span>
                                <span class="truncate text-h4">{{ $spotlight->name }}</span>
                            </p>
                            <x-ui.button
                                :href="route('animals.show', $spotlight)"
                                icon="arrow-right"
                                :label="__('page_home.hero.spotlight_action', ['name' => $spotlight->name])"
                                wire:navigate.hover
                                data-home-spotlight-link
                            />
                        </div>
                    @endif
                </div>

                <ul role="list" aria-label="{{ __('page_home.hero.stats_label') }}" class="grid gap-4 sm:grid-cols-2 md:grid-cols-1 lg:grid-cols-2" data-home-stats>
                    <li>
                        <x-ui.stat :value="$stats->availableAnimals" :label="trans_choice('page_home.stats.animals', $stats->availableAnimals)" tone="leaf" icon="paw-print" class="h-full" data-home-stat="animals" />
                    </li>
                    <li>
                        <x-ui.stat :value="$stats->verifiedOrganizations" :label="trans_choice('page_home.stats.organizations', $stats->verifiedOrganizations)" tone="surface" icon="building-2" class="h-full" data-home-stat="organizations" />
                    </li>
                </ul>
            </div>
        </div>
    </section>

    {{-- Vetrina «In cerca di casa» --}}
    <section aria-labelledby="home-showcase-title" class="mx-auto flex w-full max-w-page flex-col gap-8 px-gutter" data-home-showcase>
        <x-ui.section-heading
            id="home-showcase-title"
            :eyebrow="__('page_home.showcase.eyebrow')"
            :title="__('page_home.showcase.title')"
            :description="__('page_home.showcase.description')"
        >
            <x-slot:action>
                <x-ui.button variant="secondary" :href="route('catalog')" icon-trailing="arrow-right" wire:navigate.hover>{{ __('page_home.showcase.see_all') }}</x-ui.button>
            </x-slot:action>
        </x-ui.section-heading>

        @island(name: 'showcase')
            @php
                $showcase = $this->showcase;
                $favoriteIds = $this->favoriteIds;
                $selected = $this->selectedSpecies;
                $selectedLabel = $selected !== null ? __('page_home.showcase.species.'.$selected->value) : null;
                $count = $showcase->animals->count();
                $catalogUrl = route('catalog', $showcase->catalogQuery());
            @endphp

            <div class="flex flex-col gap-6" data-home-showcase-island>
                @if (! $showcase->isEmpty() || $selected !== null)
                    <div role="group" aria-label="{{ __('page_home.showcase.filter_label') }}" class="flex flex-wrap gap-2" data-home-filters>
                        <x-ui.tag :pressed="$selected === null" wire:click="$set('species', '')" data-home-filter="all">{{ __('page_home.showcase.all') }}</x-ui.tag>
                        @foreach (Species::cases() as $option)
                            <x-ui.tag :pressed="$selected === $option" wire:click="$set('species', '{{ $option->value }}')" data-home-filter="{{ $option->value }}">{{ __('page_home.showcase.species.'.$option->value) }}</x-ui.tag>
                        @endforeach
                    </div>
                @endif

                <p class="sr-only" aria-live="polite" aria-atomic="true" data-home-results>
                    @if ($selectedLabel !== null)
                        {{ trans_choice('page_home.showcase.results_species', $count, ['count' => $count, 'species' => $selectedLabel]) }}
                    @else
                        {{ trans_choice('page_home.showcase.results', $count, ['count' => $count]) }}
                    @endif
                </p>

                @if (! $showcase->isEmpty())
                    <ul role="list" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" data-home-grid>
                        @foreach ($showcase->animals as $animal)
                            <li wire:key="home-animal-{{ $animal->id }}">
                                <x-site.animal-card
                                    :animal="$animal"
                                    :favorited="in_array($animal->id, $favoriteIds, true)"
                                    :priority="$loop->first && $spotlightId === null"
                                    :tone="['sun', 'peach', 'mint'][$loop->index % 3]"
                                    heading-level="3"
                                />
                            </li>
                        @endforeach
                        <li wire:key="home-animal-cta">
                            <x-ui.animal-card.cta :href="$catalogUrl" :count="$showcase->remaining" heading-level="3" data-home-cta />
                        </li>
                    </ul>
                @elseif ($selected !== null)
                    <x-ui.empty-state
                        icon="search-x"
                        block="mint"
                        heading-level="3"
                        :title="__('page_home.showcase.empty_filtered_title')"
                        :description="__('page_home.showcase.empty_filtered_body')"
                        data-home-empty="filtered"
                    >
                        <x-slot:actions>
                            <x-ui.button variant="secondary" :href="$catalogUrl" icon-trailing="arrow-right" wire:navigate.hover>{{ __('page_home.showcase.empty_action') }}</x-ui.button>
                        </x-slot:actions>
                    </x-ui.empty-state>
                @else
                    <x-ui.empty-state
                        icon="paw-print"
                        block="peach"
                        heading-level="3"
                        :title="__('page_home.showcase.empty_title')"
                        :description="__('page_home.showcase.empty_body')"
                        data-home-empty="all"
                    />
                @endif
            </div>
        @endisland
    </section>

    {{-- Come funziona --}}
    <section aria-labelledby="home-how-title" class="mx-auto w-full max-w-page px-gutter" data-home-how>
        <div class="flex flex-col gap-10 rounded-panel bg-surface p-panel">
            <x-ui.section-heading
                id="home-how-title"
                :eyebrow="__('page_home.how.eyebrow')"
                :title="__('page_home.how.title')"
                :description="__('page_home.how.description')"
            />

            <ol role="list" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" data-home-steps>
                @foreach ($steps as $index => $step)
                    <li class="flex flex-col gap-4 rounded-step bg-surface-raised p-6" data-home-step="{{ $step }}">
                        <span aria-hidden="true" class="inline-flex size-14 items-center justify-center rounded-icon-lg text-h4 tabular-nums {{ $stepTones[$index] }}">{{ $index + 1 }}</span>
                        <h3 class="text-h5">{{ __('page_home.how.steps.'.$step.'.title') }}</h3>
                        <p class="text-fg-muted">{{ __('page_home.how.steps.'.$step.'.body') }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- Storie di adozione: segnaposto onesto finché non ci sono storie vere --}}
    <section aria-labelledby="home-stories-title" class="mx-auto w-full max-w-page px-gutter" data-home-stories>
        <div class="grid gap-4 lg:grid-cols-12">
            <div @class([
                'flex flex-col items-start gap-8 rounded-panel border-2 border-dashed border-line p-panel',
                'lg:col-span-8' => $stats->hasCompletedAdoptions(),
                'lg:col-span-12' => ! $stats->hasCompletedAdoptions(),
            ])>
                <x-ui.section-heading
                    id="home-stories-title"
                    :eyebrow="__('page_home.stories.eyebrow')"
                    :title="__('page_home.stories.title')"
                    :description="__('page_home.stories.body')"
                />
                <x-ui.button variant="secondary" :href="route('catalog')" icon="heart" wire:navigate.hover>{{ __('page_home.stories.action') }}</x-ui.button>
            </div>

            @if ($stats->hasCompletedAdoptions())
                <x-ui.stat
                    :value="$stats->completedAdoptions"
                    :label="trans_choice('page_home.stats.adoptions', $stats->completedAdoptions)"
                    tone="mint"
                    icon="house-heart"
                    class="justify-end lg:col-span-4"
                    data-home-stat="adoptions"
                />
            @endif
        </div>
    </section>

    {{-- Banda donazione --}}
    <div class="mx-auto w-full max-w-page px-gutter" data-home-support>
        <x-ui.cta-band id="home-support-title" :title="__('page_home.support.title')" icon="hand-heart">
            {{ __('page_home.support.body') }}
            <x-slot:actions>
                <x-ui.button variant="highlight" size="lg" :href="route('organizations.index')" icon-trailing="arrow-right" wire:navigate.hover>{{ __('page_home.support.action') }}</x-ui.button>
            </x-slot:actions>
        </x-ui.cta-band>
    </div>

    {{-- Invito agli enti --}}
    <section aria-labelledby="home-organizations-title" class="mx-auto w-full max-w-page px-gutter" data-home-organizations>
        <x-ui.section-heading
            id="home-organizations-title"
            size="h3"
            :title="__('page_home.organizations.title')"
            :description="__('page_home.organizations.body')"
        >
            <x-slot:action>
                {{-- Pannello Filament degli enti: niente wire:navigate (non è una pagina del sito). --}}
                <x-ui.button variant="secondary" :href="route('filament.shelter.auth.register')" icon-trailing="arrow-right" data-home-organizations-link>{{ __('page_home.organizations.action') }}</x-ui.button>
            </x-slot:action>
        </x-ui.section-heading>
    </section>
</div>
