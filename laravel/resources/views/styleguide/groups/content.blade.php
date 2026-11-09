{{--
 | Styleguide · gruppo "content": tabs, accordion, skeleton, empty-state, avatar.
 | Testi in lang/{it,en}/styleguide_content.php; JS di tabs e accordion in
 | resources/js/ui/content.js. Gli attributi data-demo servono ai test browser
 | (tests/Browser/Components/ContentComponentsTest.php).
--}}
@php
    $t = static fn (string $key, array $replace = []): string => __('styleguide_content.'.$key, $replace);

    $animalTabs = ['about' => 'heart', 'health' => 'stethoscope', 'home' => 'house', 'documents' => 'file-text', 'meet' => 'calendar'];
    $faq = ['cost', 'time', 'visit', 'return'];

    /*
     | Ritratti stilizzati per gli esempi di avatar (SVG inline, niente richieste esterne).
     | I colori sono quelli della palette (app.css): è il contenuto di un'immagine, non stile.
     */
    $portrait = static fn (string $background, string $figure): string => 'data:image/svg+xml,'.rawurlencode(
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 80 80">'
        .'<rect width="80" height="80" fill="'.$background.'"/>'
        .'<circle cx="40" cy="31" r="14" fill="'.$figure.'"/>'
        .'<path d="M12 80c3-19 15-28 28-28s25 9 28 28z" fill="'.$figure.'"/>'
        .'</svg>'
    );
    $photoPeach = $portrait('oklch(83.62% 0.0918 34)', 'oklch(34.81% 0.0732 33.06)');
    $photoMint = $portrait('oklch(87.92% 0.0614 156.65)', 'oklch(40% 0.068 163)');

    $people = [
        ['name' => 'Maria Rossi', 'src' => $photoPeach],
        ['name' => 'Luca Bianchi'],
        ['name' => 'Giulia Esposito', 'src' => $photoMint],
        ['name' => 'Ahmed Benali'],
        ['name' => 'Sofia Ricci'],
        ['name' => 'Marco Conti'],
        ['name' => 'Elena Gallo'],
    ];
    $names = ['Maria Rossi', 'Luca Bianchi', 'Giulia Esposito', 'Ahmed Benali', 'Sofia Ricci', 'Marco Conti', 'Elena Gallo', 'Chiara Moretti'];
@endphp
<x-styleguide.layout :title="__('styleguide.page_title', ['group' => __('styleguide.groups.content.title'), 'styleguide' => __('styleguide.title')])" current="content">
    <div class="flex flex-col gap-3 pt-4 md:pt-8">
        <h1 class="text-h1">{{ __('styleguide.groups.content.title') }}</h1>
        <p class="max-w-2xl text-lead text-fg-muted">{{ __('styleguide.groups.content.intro') }}</p>
    </div>

    {{-- ── Tabs ──────────────────────────────────────────────────────────── --}}
    <x-styleguide.component id="tabs" :title="$t('tabs.title')" :description="$t('tabs.description')" :a11y="[$t('tabs.a11y_keyboard'), $t('tabs.a11y_roving'), $t('tabs.a11y_panel'), $t('tabs.a11y_ssr')]">
        <x-styleguide.example :title="$t('tabs.animal')">
            <x-slot:code>@verbatim<x-ui.tabs id="animal" :selected="$tab" wire:model.live="tab">
    <x-ui.tabs.list :label="__('…')">
        <x-ui.tabs.tab name="about" icon="heart">…</x-ui.tabs.tab>
        <x-ui.tabs.tab name="health" icon="stethoscope">…</x-ui.tabs.tab>
    </x-ui.tabs.list>
    <x-ui.tabs.panel name="about">…</x-ui.tabs.panel>
    <x-ui.tabs.panel name="health">…</x-ui.tabs.panel>
</x-ui.tabs>@endverbatim</x-slot:code>
            <x-ui.tabs id="demo-animal" selected="about" class="w-full" data-demo="tabs">
                <x-ui.tabs.list :label="$t('tabs.animal_label')">
                    @foreach ($animalTabs as $name => $icon)
                        <x-ui.tabs.tab :name="$name" :icon="$icon">{{ $t('tabs.tab_'.$name) }}</x-ui.tabs.tab>
                    @endforeach
                </x-ui.tabs.list>
                @foreach ($animalTabs as $name => $icon)
                    <x-ui.tabs.panel :name="$name" class="max-w-2xl">
                        <p>{{ $t('tabs.panel_'.$name) }}</p>
                    </x-ui.tabs.panel>
                @endforeach
            </x-ui.tabs>
        </x-styleguide.example>

        <x-styleguide.example :title="$t('tabs.model')">
            <x-slot:code>@verbatim<x-ui.tabs id="period" :selected="$period" wire:model.live="period">…</x-ui.tabs>@endverbatim</x-slot:code>
            <div x-data="{ period: 'week' }" class="flex w-full min-w-0 flex-col gap-4" data-demo="tabs-model">
                <p class="text-small text-fg-muted">{{ $t('tabs.model_hint') }}</p>
                <x-ui.tabs id="demo-period" selected="week" x-model="period">
                    <x-ui.tabs.list :label="$t('tabs.period_label')">
                        @foreach (['week', 'month', 'year'] as $name)
                            <x-ui.tabs.tab :name="$name">{{ $t('tabs.period_'.$name) }}</x-ui.tabs.tab>
                        @endforeach
                    </x-ui.tabs.list>
                    @foreach (['week', 'month', 'year'] as $name)
                        <x-ui.tabs.panel :name="$name">
                            <p>{{ $t('tabs.period_panel', ['period' => $t('tabs.period_'.$name)]) }}</p>
                        </x-ui.tabs.panel>
                    @endforeach
                </x-ui.tabs>
                <div class="flex flex-wrap items-center gap-3">
                    <p class="text-small">{{ $t('tabs.model_value') }} <code class="font-semibold" data-demo="tabs-model-value" x-text="period">week</code></p>
                    <x-ui.button variant="secondary" size="sm" x-on:click="period = 'year'" data-demo="tabs-model-set">{{ $t('tabs.model_set') }}</x-ui.button>
                </div>
            </div>
        </x-styleguide.example>
    </x-styleguide.component>

    {{-- ── Accordion ─────────────────────────────────────────────────────── --}}
    <x-styleguide.component id="accordion" :title="$t('accordion.title')" :description="$t('accordion.description')" :a11y="[$t('accordion.a11y_heading'), $t('accordion.a11y_state'), $t('accordion.a11y_keyboard')]">
        <x-styleguide.example :title="$t('accordion.single')">
            <x-slot:code>@verbatim<x-ui.accordion heading-level="2">
    <x-ui.accordion.item name="cost" :title="__('…')" open>…</x-ui.accordion.item>
    <x-ui.accordion.item name="time" :title="__('…')">…</x-ui.accordion.item>
</x-ui.accordion>@endverbatim</x-slot:code>
            <x-ui.accordion id="demo-faq" heading-level="4" class="w-full max-w-3xl" data-demo="accordion-single">
                @foreach ($faq as $question)
                    <x-ui.accordion.item :name="$question" :title="$t('accordion.q_'.$question)" :open="$loop->first">
                        <p>{{ $t('accordion.a_'.$question) }}</p>
                    </x-ui.accordion.item>
                @endforeach
            </x-ui.accordion>
        </x-styleguide.example>

        <x-styleguide.example :title="$t('accordion.multiple')">
            <x-slot:code>@verbatim<x-ui.accordion multiple>…</x-ui.accordion>@endverbatim</x-slot:code>
            <x-ui.accordion id="demo-filters" multiple heading-level="4" class="w-full max-w-3xl" data-demo="accordion-multiple">
                @foreach (['species', 'size', 'age'] as $filter)
                    <x-ui.accordion.item :name="$filter" :title="$t('accordion.f_'.$filter)" :open="$filter !== 'age'">
                        <p>{{ $t('accordion.f_'.$filter.'_body') }}</p>
                    </x-ui.accordion.item>
                @endforeach
            </x-ui.accordion>
        </x-styleguide.example>
    </x-styleguide.component>

    {{-- ── Skeleton ──────────────────────────────────────────────────────── --}}
    <x-styleguide.component id="skeleton" :title="$t('skeleton.title')" :description="$t('skeleton.description')" :a11y="[$t('skeleton.a11y_status'), $t('skeleton.a11y_motion')]">
        <x-styleguide.example :title="$t('skeleton.card')">
            <x-slot:code>@verbatim<x-ui.skeleton :label="__('…')" class="flex flex-col gap-4 rounded-card bg-surface p-3">
    <x-ui.skeleton.block shape="media" />
    <x-ui.skeleton.block shape="heading" class="w-2/3" />
    <x-ui.skeleton.block class="w-1/2" />
</x-ui.skeleton>@endverbatim</x-slot:code>
            <div class="grid w-full gap-4 sm:grid-cols-2 lg:grid-cols-3" data-demo="skeleton-cards">
                @foreach (range(1, 3) as $card)
                    <x-ui.skeleton :label="$t('skeleton.card_label')" class="flex flex-col gap-4 rounded-card bg-surface p-3">
                        <x-ui.skeleton.block shape="media" />
                        <div class="flex flex-col gap-3 px-2 pb-2">
                            <x-ui.skeleton.block shape="heading" class="w-2/3" />
                            <x-ui.skeleton.block class="w-1/2" />
                            <div class="flex gap-2">
                                <x-ui.skeleton.block shape="pill" class="w-20" />
                                <x-ui.skeleton.block shape="pill" class="w-24" />
                            </div>
                        </div>
                    </x-ui.skeleton>
                @endforeach
            </div>
        </x-styleguide.example>

        <x-styleguide.example :title="$t('skeleton.row')">
            <x-slot:code>@verbatim<x-ui.skeleton class="flex items-center gap-4">
    <x-ui.skeleton.block shape="circle" size="lg" />
    …
</x-ui.skeleton>@endverbatim</x-slot:code>
            <x-ui.skeleton :label="$t('skeleton.row_label')" class="flex w-full flex-col gap-3" data-demo="skeleton-rows">
                @foreach (range(1, 3) as $row)
                    <div class="flex items-center gap-4 rounded-card bg-surface p-4">
                        <x-ui.skeleton.block shape="circle" size="lg" />
                        <div class="flex min-w-0 flex-1 flex-col gap-2">
                            <x-ui.skeleton.block class="w-2/5" />
                            <x-ui.skeleton.block class="w-3/5" />
                        </div>
                        <div class="hidden sm:block"><x-ui.skeleton.block shape="pill" /></div>
                    </div>
                @endforeach
            </x-ui.skeleton>
        </x-styleguide.example>

        <x-styleguide.example :title="$t('skeleton.shapes')">
            @foreach (['text', 'heading', 'circle', 'icon', 'pill', 'media', 'card'] as $shape)
                <span class="inline-flex w-36 flex-col items-start gap-2 text-caption text-fg-muted">
                    <x-ui.skeleton.block :shape="$shape" class="{{ in_array($shape, ['circle', 'icon', 'pill'], true) ? '' : 'w-full' }}" />
                    {{ $shape }}
                </span>
            @endforeach
        </x-styleguide.example>
    </x-styleguide.component>

    {{-- ── Empty state ───────────────────────────────────────────────────── --}}
    <x-styleguide.component id="empty-state" :title="$t('empty.title')" :description="$t('empty.description')" :a11y="[$t('empty.a11y_heading'), $t('empty.a11y_icon')]">
        <x-styleguide.example :title="$t('empty.search')">
            <x-slot:code>@verbatim<x-ui.empty-state variant="search">
    <x-slot:actions>
        <x-ui.button variant="secondary" wire:click="resetFilters">…</x-ui.button>
    </x-slot:actions>
</x-ui.empty-state>@endverbatim</x-slot:code>
            <x-ui.empty-state variant="search" heading-level="4" class="w-full" data-demo="empty-search">
                <x-slot:actions>
                    <x-ui.button variant="secondary" icon="rotate-ccw">{{ $t('empty.reset') }}</x-ui.button>
                    <x-ui.button :href="'#empty-state'">{{ $t('empty.browse') }}</x-ui.button>
                </x-slot:actions>
            </x-ui.empty-state>
        </x-styleguide.example>

        <x-styleguide.example :title="$t('empty.favorites')">
            <x-slot:code>@verbatim<x-ui.empty-state variant="favorites">…</x-ui.empty-state>@endverbatim</x-slot:code>
            <x-ui.empty-state variant="favorites" heading-level="4" class="w-full">
                <x-slot:actions>
                    <x-ui.button :href="'#empty-state'" icon-trailing="arrow-right">{{ $t('empty.browse') }}</x-ui.button>
                </x-slot:actions>
            </x-ui.empty-state>
        </x-styleguide.example>

        <x-styleguide.example :title="$t('empty.error')">
            <x-slot:code>@verbatim<x-ui.empty-state variant="error">…</x-ui.empty-state>@endverbatim</x-slot:code>
            <x-ui.empty-state variant="error" heading-level="4" class="w-full">
                <x-slot:actions>
                    <x-ui.button icon="rotate-ccw">{{ $t('empty.retry') }}</x-ui.button>
                </x-slot:actions>
            </x-ui.empty-state>
        </x-styleguide.example>

        <x-styleguide.example :title="$t('empty.custom')" background="surface">
            <x-slot:code>@verbatim<x-ui.empty-state icon="calendar" block="leaf" :title="__('…')" :framed="false">…</x-ui.empty-state>@endverbatim</x-slot:code>
            <x-ui.empty-state icon="calendar" block="leaf" :title="$t('empty.custom_title')" :description="$t('empty.custom_description')" heading-level="4" :framed="false" class="w-full" />
        </x-styleguide.example>
    </x-styleguide.component>

    {{-- ── Avatar ────────────────────────────────────────────────────────── --}}
    <x-styleguide.component id="avatar" :title="$t('avatar.title')" :description="$t('avatar.description')" :a11y="[$t('avatar.a11y_name'), $t('avatar.a11y_decorative'), $t('avatar.a11y_more')]">
        <x-styleguide.example :title="$t('avatar.sizes')">
            <x-slot:code>@verbatim<x-ui.avatar name="Maria Rossi" src="…" size="lg" />@endverbatim</x-slot:code>
            @foreach (['sm', 'md', 'lg', 'xl'] as $size)
                <span class="inline-flex flex-col items-center gap-2 text-caption text-fg-muted">
                    <x-ui.avatar name="Maria Rossi" :src="$photoPeach" :size="$size" />
                    {{ $size }}
                </span>
            @endforeach
            @foreach (['sm', 'md', 'lg', 'xl'] as $size)
                <span class="inline-flex flex-col items-center gap-2 text-caption text-fg-muted">
                    <x-ui.avatar name="Luca Bianchi" :size="$size" />
                    {{ $size }}
                </span>
            @endforeach
        </x-styleguide.example>

        <x-styleguide.example :title="$t('avatar.initials')">
            <x-slot:code>@verbatim<x-ui.avatar :name="$adopter->name" />@endverbatim</x-slot:code>
            <p class="w-full text-small text-fg-muted">{{ $t('avatar.initials_hint') }}</p>
            <div class="flex flex-wrap gap-3" data-demo="avatar-initials">
                @foreach ($names as $name)
                    <x-ui.avatar :name="$name" />
                @endforeach
            </div>
        </x-styleguide.example>

        <x-styleguide.example :title="$t('avatar.with_name')">
            <x-slot:code>@verbatim<x-ui.avatar :name="$user->name" decorative /> {{ $user->name }}@endverbatim</x-slot:code>
            <div class="flex items-center gap-3" data-demo="avatar-decorative">
                <x-ui.avatar name="Giulia Esposito" :src="$photoMint" decorative />
                <span class="flex flex-col">
                    <span class="font-semibold">Giulia Esposito</span>
                    <span class="text-small text-fg-muted">{{ $t('avatar.volunteer') }}</span>
                </span>
            </div>
            <div class="flex items-center gap-3">
                <x-ui.avatar name="Ahmed Benali" decorative />
                <span class="flex flex-col">
                    <span class="font-semibold">Ahmed Benali</span>
                    <span class="text-small text-fg-muted">{{ $t('avatar.adopter') }}</span>
                </span>
            </div>
        </x-styleguide.example>

        <x-styleguide.example :title="$t('avatar.logos')" background="surface">
            <x-slot:code>@verbatim<x-ui.avatar :name="$organization->name" shape="square" size="lg" />@endverbatim</x-slot:code>
            @foreach (['Rifugio Il Faro' => null, 'Canile di Borgo Sole' => null, 'Gattile Sette Vite' => null, 'Amici a Quattro Zampe' => 'sun'] as $organization => $color)
                <div class="flex items-center gap-3">
                    <x-ui.avatar :name="$organization" :color="$color" shape="square" size="lg" decorative />
                    <span class="font-semibold">{{ $organization }}</span>
                </div>
            @endforeach
        </x-styleguide.example>

        <x-styleguide.example :title="$t('avatar.group')">
            <x-slot:code>@verbatim<x-ui.avatar.group :people="$volunteers" :max="4" :label="__('…')" />@endverbatim</x-slot:code>
            <x-ui.avatar.group :people="$people" :max="4" :label="$t('avatar.group_label')" data-demo="avatar-group" />
            <x-ui.avatar.group :people="array_slice($people, 0, 3)" :total="42" size="lg" :label="$t('avatar.group_label')" />
        </x-styleguide.example>

        <x-styleguide.example :title="$t('avatar.group_surface')" background="surface">
            <x-slot:code>@verbatim<x-ui.avatar.group :people="$people" :max="3" ring="surface" size="sm" />@endverbatim</x-slot:code>
            <x-ui.avatar.group :people="$people" :max="3" ring="surface" size="sm" :label="$t('avatar.group_label')" />
            <span class="text-small text-fg-muted">{{ $t('avatar.group_caption') }}</span>
        </x-styleguide.example>
    </x-styleguide.component>
</x-styleguide.layout>
