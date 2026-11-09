{{--
 | Styleguide · gruppo "domain": animal-card (+ favorite, cta, skeleton), organization-card,
 | stat, section-heading, cta-band. Testi in lang/{it,en}/styleguide_domain.php.
 | I componenti hanno props primitive: qui sono mostrati con dati finti, senza database.
 | Gli attributi data-demo servono ai test browser
 | (tests/Browser/Components/DomainComponentsTest.php).
 |
 | Livelli dei titoli: h1 della pagina, h2 per componente, h3 per esempio, quindi i titoli
 | dei componenti negli esempi sono h4 (heading-level="4").
--}}
@php

    $t = static fn (string $key, array $replace = []): string => __('styleguide_domain.'.$key, $replace);

    /*
     | Ritratti stilizzati per le foto degli esempi (SVG inline, niente richieste esterne).
     | I colori sono quelli della palette (app.css): è il contenuto di un'immagine, non stile.
     */
    $navy = 'oklch(24.04% 0.0582 281.1)';
    $portrait = static fn (string $background, string $fur, string $ears): string => 'data:image/svg+xml,'.rawurlencode(
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 80 100">'
        .'<rect width="80" height="100" fill="'.$background.'"/>'
        .'<path d="M10 100c3-16 15-24 30-24s27 8 30 24z" fill="'.$fur.'"/>'
        .$ears
        .'<ellipse cx="40" cy="56" rx="20" ry="18" fill="'.$fur.'"/>'
        .'<circle cx="32" cy="53" r="2.6" fill="'.$navy.'"/><circle cx="48" cy="53" r="2.6" fill="'.$navy.'"/>'
        .'<ellipse cx="40" cy="62" rx="4" ry="3" fill="'.$navy.'"/>'
        .'</svg>'
    );
    $dogEars = static fn (string $color): string => '<ellipse cx="21" cy="52" rx="7" ry="15" transform="rotate(18 21 52)" fill="'.$color.'"/>'
        .'<ellipse cx="59" cy="52" rx="7" ry="15" transform="rotate(-18 59 52)" fill="'.$color.'"/>';
    $catEars = static fn (string $color): string => '<path d="M22 50 25 28 38 42zM58 50 55 28 42 42z" fill="'.$color.'"/>';

    $photoDog = $portrait('oklch(87.89% 0.1617 90.94)', 'oklch(96.98% 0.0095 93.57)', $dogEars('oklch(48% 0.11 32)'));
    $photoCat = $portrait('oklch(83.62% 0.0918 34)', 'oklch(59% 0.13 31)', $catEars('oklch(59% 0.13 31)'));

    $animals = [
        [
            'key' => 'fido', 'name' => 'Fido', 'tone' => 'sun', 'photo' => $photoDog,
            'species' => __('enums.species.dog'), 'sex' => __('enums.sex.male'), 'age' => $t('demo.age_adult'),
            'size' => __('enums.size.medium'), 'location' => 'Bergamo, Lombardia', 'featured' => true,
        ],
        [
            'key' => 'luna', 'name' => 'Luna', 'tone' => 'peach', 'photo' => $photoCat,
            'species' => __('enums.species.cat'), 'sex' => __('enums.sex.female'), 'age' => $t('demo.age_puppy'),
            'size' => __('enums.size.small'), 'location' => 'Torino, Piemonte', 'featured' => false,
        ],
        [
            'key' => 'birba', 'name' => 'Birba', 'tone' => 'mint', 'photo' => null,
            'species' => __('enums.species.other'), 'sex' => __('enums.sex.female'), 'age' => $t('demo.age_senior'),
            'size' => null, 'location' => 'Lecce, Puglia', 'featured' => false,
        ],
    ];

    $organizations = [
        ['name' => 'Rifugio Il Faro', 'city' => 'Genova', 'region' => 'Liguria', 'type' => __('enums.organization_type.rifugio'), 'verified' => true, 'available' => 1240],
        ['name' => 'Canile comunale di Bergamo', 'city' => 'Bergamo', 'region' => 'Lombardia', 'type' => __('enums.organization_type.canile_comunale'), 'verified' => true, 'available' => 1],
        ['name' => 'Associazione Code Felici', 'city' => 'Bari', 'region' => null, 'type' => __('enums.organization_type.associazione'), 'verified' => false, 'available' => 0],
    ];

    $logo = 'data:image/svg+xml,'.rawurlencode(
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 56 56">'
        .'<rect width="56" height="56" fill="'.$navy.'"/>'
        .'<path d="M28 10 40 44H16z" fill="oklch(87.89% 0.1617 90.94)"/>'
        .'<rect x="22" y="26" width="12" height="4" fill="'.$navy.'"/>'
        .'</svg>'
    );
@endphp
<x-styleguide.layout :title="__('styleguide.page_title', ['group' => __('styleguide.groups.domain.title'), 'styleguide' => __('styleguide.title')])" current="domain">
    <div class="flex flex-col gap-3 pt-4 md:pt-8">
        <h1 class="text-h1">{{ __('styleguide.groups.domain.title') }}</h1>
        <p class="max-w-2xl text-lead text-fg-muted">{{ __('styleguide.groups.domain.intro') }}</p>
    </div>

    {{-- ── Animal card ───────────────────────────────────────────────────── --}}
    <x-styleguide.component id="animal-card" :title="$t('animal_card.title')" :description="$t('animal_card.description')" :a11y="[$t('animal_card.a11y_link'), $t('animal_card.a11y_favorite'), $t('animal_card.a11y_heading'), $t('animal_card.a11y_featured')]">
        <x-styleguide.example :title="$t('animal_card.grid')">
            <x-slot:code>@verbatim<ul role="list" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    @foreach ($animals as $animal)
        <li>
            <x-ui.animal-card
                :name="$animal->name"
                :href="route('animals.show', $animal)"
                :photo="$animal->getFirstMediaUrl('photos', 'thumb') ?: null"
                :tone="['sun', 'peach', 'mint'][$loop->index % 3]"
                :species="$animal->species->getLabel()"
                :sex="$animal->sex->getLabel()"
                :size="$animal->size?->getLabel()"
                :location="$animal->organization->city"
                :featured="$animal->featured"
                heading-level="2"
            >
                <x-slot:favorite>
                    <livewire:favorite-button :animal="$animal" :key="'fav-'.$animal->id" />
                </x-slot:favorite>
            </x-ui.animal-card>
        </li>
    @endforeach
    <li><x-ui.animal-card.cta :href="route('catalog')" :count="$remaining" heading-level="2" /></li>
</ul>@endverbatim</x-slot:code>
            <p class="w-full text-small text-fg-muted">{{ $t('animal_card.grid_hint') }}</p>
            <ul role="list" aria-label="{{ $t('animal_card.grid_label') }}" class="grid w-full gap-4 sm:grid-cols-2 lg:grid-cols-4" data-demo="animal-grid">
                @foreach ($animals as $animal)
                    <li>
                        <x-ui.animal-card
                            :name="$animal['name']"
                            :href="'#animal-card'"
                            :photo="$animal['photo']"
                            :tone="$animal['tone']"
                            :species="$animal['species']"
                            :sex="$animal['sex']"
                            :age="$animal['age']"
                            :size="$animal['size']"
                            :location="$animal['location']"
                            :featured="$animal['featured']"
                            heading-level="4"
                            data-demo-animal="{{ $animal['key'] }}"
                        >
                            <x-slot:favorite>
                                <x-ui.animal-card.favorite
                                    :name="$animal['name']"
                                    x-data="{ pressed: false }"
                                    x-on:click="pressed = ! pressed"
                                    x-bind:aria-pressed="pressed.toString()"
                                />
                            </x-slot:favorite>
                        </x-ui.animal-card>
                    </li>
                @endforeach
                <li>
                    <x-ui.animal-card.cta :href="'#animal-card'" :count="128" heading-level="4" data-demo="animal-cta" />
                </li>
            </ul>
        </x-styleguide.example>

        <x-styleguide.example :title="$t('animal_card.minimal')">
            <x-slot:code>@verbatim<x-ui.animal-card name="Zeus" href="…" tone="sun" heading-level="2" />@endverbatim</x-slot:code>
            <div class="grid w-full gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-ui.animal-card name="Zeus" :href="'#animal-card'" tone="sun" heading-level="4" data-demo-animal="zeus" />
                <x-ui.animal-card name="Nina" :href="'#animal-card'" tone="peach" heading-level="4" data-demo-animal="nina" />
                <x-ui.animal-card.cta :href="'#animal-card'" heading-level="4" />
            </div>
        </x-styleguide.example>

        <x-styleguide.example :title="$t('animal_card.skeleton')">
            <x-slot:code>@verbatim<x-ui.skeleton :label="__('…')" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    @foreach (range(1, 4) as $i)
        <x-ui.animal-card.skeleton bare />
    @endforeach
</x-ui.skeleton>@endverbatim</x-slot:code>
            <x-ui.skeleton :label="$t('animal_card.skeleton_label')" class="grid w-full gap-4 sm:grid-cols-2 lg:grid-cols-4" data-demo="animal-skeleton">
                @foreach (range(1, 4) as $i)
                    <x-ui.animal-card.skeleton bare />
                @endforeach
            </x-ui.skeleton>
        </x-styleguide.example>
    </x-styleguide.component>

    {{-- ── Organization card ─────────────────────────────────────────────── --}}
    <x-styleguide.component id="organization-card" :title="$t('organization_card.title')" :description="$t('organization_card.description')" :a11y="[$t('organization_card.a11y_link'), $t('organization_card.a11y_logo'), $t('organization_card.a11y_count')]">
        <x-styleguide.example :title="$t('organization_card.grid')">
            <x-slot:code>@verbatim<x-ui.organization-card
    :name="$organization->name"
    :href="route('organizations.show', $organization)"
    :logo="$organization->getFirstMediaUrl('logo') ?: null"
    :city="$organization->city"
    :region="$organization->region"
    :type="$organization->type->getLabel()"
    :verified="$organization->isVerified()"
    :available="$organization->available_animals_count"
/>@endverbatim</x-slot:code>
            <ul role="list" aria-label="{{ $t('organization_card.grid_label') }}" class="grid w-full gap-4 md:grid-cols-2 lg:grid-cols-3" data-demo="organization-grid">
                @foreach ($organizations as $organization)
                    <li>
                        <x-ui.organization-card
                            :name="$organization['name']"
                            :href="'#organization-card'"
                            :logo="$loop->first ? $logo : null"
                            :city="$organization['city']"
                            :region="$organization['region']"
                            :type="$organization['type']"
                            :verified="$organization['verified']"
                            :available="$organization['available']"
                            heading-level="4"
                        />
                    </li>
                @endforeach
            </ul>
        </x-styleguide.example>
    </x-styleguide.component>

    {{-- ── Stat ──────────────────────────────────────────────────────────── --}}
    <x-styleguide.component id="stat" :title="$t('stat.title')" :description="$t('stat.description')" :a11y="[$t('stat.a11y_semantics'), $t('stat.a11y_icon')]">
        <x-styleguide.example :title="$t('stat.bento')">
            <x-slot:code>@verbatim<ul role="list" class="grid gap-4 sm:grid-cols-2">
    <li><x-ui.stat :value="1240" :label="__('…')" icon="heart" /></li>
    <li><x-ui.stat :value="86" :label="__('…')" tone="surface" /></li>
</ul>@endverbatim</x-slot:code>
            <ul role="list" aria-label="{{ $t('stat.bento_label') }}" class="grid w-full gap-4 sm:grid-cols-2" data-demo="stat-bento">
                <li><x-ui.stat :value="1240" :label="$t('stat.adopted')" icon="heart" class="h-full" /></li>
                <li><x-ui.stat :value="86" :label="$t('stat.organizations')" tone="surface" icon="house-heart" class="h-full" /></li>
            </ul>
        </x-styleguide.example>

        <x-styleguide.example :title="$t('stat.tones')">
            <x-slot:code>@verbatim<x-ui.stat :value="4.8" :label="__('…')" tone="mint" size="md" />@endverbatim</x-slot:code>
            <ul role="list" class="grid w-full gap-4 sm:grid-cols-2 lg:grid-cols-4" data-demo="stat-tones">
                <li><x-ui.stat value="98%" :label="$t('stat.success')" tone="sun" size="md" class="h-full" /></li>
                <li><x-ui.stat :value="4.8" :label="$t('stat.rating')" tone="mint" size="md" class="h-full" data-demo-stat="rating" /></li>
                <li><x-ui.stat :value="14" :label="$t('stat.regions')" tone="peach" size="md" class="h-full" /></li>
                <li><x-ui.stat :value="3200" :label="$t('stat.volunteers')" tone="inverse" size="md" class="h-full" data-demo-stat="volunteers" /></li>
            </ul>
        </x-styleguide.example>
    </x-styleguide.component>

    {{-- ── Section heading ───────────────────────────────────────────────── --}}
    <x-styleguide.component id="section-heading" :title="$t('section_heading.title')" :description="$t('section_heading.description')" :a11y="[$t('section_heading.a11y_level'), $t('section_heading.a11y_labelledby')]">
        <x-styleguide.example :title="$t('section_heading.with_action')">
            <x-slot:code>@verbatim<section aria-labelledby="new-arrivals">
    <x-ui.section-heading id="new-arrivals" :title="__('…')" :eyebrow="__('…')" :description="__('…')">
        <x-slot:action>
            <x-ui.button variant="secondary" :href="route('catalog')" icon-trailing="arrow-right">{{ __('…') }}</x-ui.button>
        </x-slot:action>
    </x-ui.section-heading>
</section>@endverbatim</x-slot:code>
            <x-ui.section-heading
                id="demo-new-arrivals"
                :title="$t('section_heading.demo_title')"
                :eyebrow="$t('section_heading.demo_eyebrow')"
                :description="$t('section_heading.demo_description')"
                heading-level="4"
                class="w-full"
                data-demo="section-heading"
            >
                <x-slot:action>
                    <x-ui.button variant="secondary" :href="'#section-heading'" icon-trailing="arrow-right">{{ $t('demo.see_all') }}</x-ui.button>
                </x-slot:action>
            </x-ui.section-heading>
        </x-styleguide.example>

        <x-styleguide.example :title="$t('section_heading.text_eyebrow')" background="surface">
            <x-slot:code>@verbatim<x-ui.section-heading :title="__('…')" :eyebrow="__('…')" eyebrow-variant="text" size="h3" />@endverbatim</x-slot:code>
            <x-ui.section-heading
                :title="$t('section_heading.demo_title_small')"
                :eyebrow="$t('section_heading.demo_eyebrow_text')"
                eyebrow-variant="text"
                size="h3"
                heading-level="4"
                class="w-full"
            />
        </x-styleguide.example>
    </x-styleguide.component>

    {{-- ── CTA band ──────────────────────────────────────────────────────── --}}
    <x-styleguide.component id="cta-band" :title="$t('cta_band.title')" :description="$t('cta_band.description')" :a11y="[$t('cta_band.a11y_tone'), $t('cta_band.a11y_yellow')]">
        <x-styleguide.example :title="$t('cta_band.donation')">
            <x-slot:code>@verbatim<x-ui.cta-band :title="__('…')" icon="hand-heart">
    {{ __('…') }}
    <x-slot:actions>
        <x-ui.button variant="highlight" size="lg" :href="route('donate')">{{ __('…') }}</x-ui.button>
    </x-slot:actions>
</x-ui.cta-band>@endverbatim</x-slot:code>
            <x-ui.cta-band :title="$t('cta_band.donation_title')" icon="hand-heart" heading-level="4" data-demo="cta-leaf">
                {{ $t('cta_band.donation_text') }}
                <x-slot:actions>
                    <x-ui.button variant="highlight" size="lg" :href="'#cta-band'" icon="heart">{{ $t('cta_band.donate') }}</x-ui.button>
                </x-slot:actions>
            </x-ui.cta-band>
        </x-styleguide.example>

        <x-styleguide.example :title="$t('cta_band.shelter')">
            <x-slot:code>@verbatim<x-ui.cta-band tone="inverse" :title="__('…')">…</x-ui.cta-band>@endverbatim</x-slot:code>
            <x-ui.cta-band tone="inverse" :title="$t('cta_band.shelter_title')" heading-level="4" data-demo="cta-inverse">
                {{ $t('cta_band.shelter_text') }}
                <x-slot:actions>
                    <x-ui.button variant="highlight" :href="'#cta-band'" icon-trailing="arrow-right">{{ $t('cta_band.shelter_action') }}</x-ui.button>
                </x-slot:actions>
            </x-ui.cta-band>
        </x-styleguide.example>

        <x-styleguide.example :title="$t('cta_band.tones')">
            <x-slot:code>@verbatim<x-ui.cta-band tone="peach" size="h3" :title="__('…')">…</x-ui.cta-band>@endverbatim</x-slot:code>
            <div class="flex w-full flex-col gap-4">
                @foreach (['sun', 'peach', 'mint'] as $tone)
                    <x-ui.cta-band :tone="$tone" size="h3" icon="paw-print" :title="$t('cta_band.tone_title', ['tone' => $t('cta_band.tone_names.'.$tone)])" heading-level="4" :data-demo="'cta-'.$tone">
                        {{ $t('cta_band.tone_text') }}
                        <x-slot:actions>
                            <x-ui.button :href="'#cta-band'">{{ $t('cta_band.tone_action') }}</x-ui.button>
                        </x-slot:actions>
                    </x-ui.cta-band>
                @endforeach
            </div>
        </x-styleguide.example>
    </x-styleguide.component>
</x-styleguide.layout>
