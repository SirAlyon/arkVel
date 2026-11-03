{{--
 | Styleguide · gruppo "actions": icon, button, link, badge, tag.
 | Testi in lang/{it,en}/styleguide_actions.php. Modello per le pagine degli altri gruppi:
 | un <x-styleguide.component> per componente, un <x-styleguide.example> per variante o stato.
--}}
@php
    $t = static fn (string $key, array $replace = []): string => __('styleguide_actions.'.$key, $replace);

    $icons = ['paw-print', 'heart', 'search', 'map-pin', 'calendar', 'mail', 'circle-check', 'triangle-alert', 'info', 'x'];
    $variants = ['primary', 'secondary', 'highlight', 'ghost', 'danger'];
    $badges = ['neutral', 'primary', 'highlight', 'stamp', 'success', 'warning', 'danger', 'info'];
    $badgeIcons = ['success' => 'circle-check', 'warning' => 'triangle-alert', 'danger' => 'circle-x', 'info' => 'info'];
@endphp
<x-styleguide.layout :title="__('styleguide.groups.actions.title').' — '.__('styleguide.title')" current="actions">
    <div class="flex flex-col gap-3 pt-4 md:pt-8">
        <h1 class="text-h1">{{ __('styleguide.groups.actions.title') }}</h1>
        <p class="max-w-2xl text-lead text-fg-muted">{{ __('styleguide.groups.actions.intro') }}</p>
    </div>

    <x-styleguide.component id="icon" :title="$t('icon.title')" :description="$t('icon.description')" :a11y="[$t('icon.a11y_decorative'), $t('icon.a11y_label')]">
        <x-styleguide.example :title="$t('icon.sizes')">
            <x-slot:code>@verbatim<x-ui.icon name="paw-print" size="lg" />@endverbatim</x-slot:code>
            @foreach (['xs', 'sm', 'md', 'lg', 'xl'] as $size)
                <span class="inline-flex flex-col items-center gap-2 text-caption text-fg-muted">
                    <x-ui.icon name="paw-print" :size="$size" class="text-fg" />
                    {{ $size }}
                </span>
            @endforeach
        </x-styleguide.example>

        <x-styleguide.example :title="$t('icon.set')" background="surface">
            @foreach ($icons as $icon)
                <span class="inline-flex size-12 items-center justify-center rounded-icon bg-canvas" title="{{ $icon }}">
                    <x-ui.icon :name="$icon" />
                </span>
            @endforeach
        </x-styleguide.example>

        <x-styleguide.example :title="$t('icon.labelled')">
            <x-slot:code>@verbatim<x-ui.icon name="circle-check" :label="__('…')" />@endverbatim</x-slot:code>
            <x-ui.icon name="circle-check" size="lg" class="text-success" :label="$t('icon.verified')" />
            <span>{{ $t('icon.labelled_hint') }}</span>
        </x-styleguide.example>
    </x-styleguide.component>

    <x-styleguide.component id="button" :title="$t('button.title')" :description="$t('button.description')" :a11y="[$t('button.a11y_target'), $t('button.a11y_icon_only'), $t('button.a11y_loading'), $t('button.a11y_link')]">
        <x-styleguide.example :title="$t('button.variants')">
            <x-slot:code>@verbatim<x-ui.button variant="secondary" icon="heart">…</x-ui.button>@endverbatim</x-slot:code>
            @foreach ($variants as $variant)
                <x-ui.button :variant="$variant">{{ $t('button.variant_'.$variant) }}</x-ui.button>
            @endforeach
        </x-styleguide.example>

        <x-styleguide.example :title="$t('button.sizes')">
            @foreach (['sm', 'md', 'lg'] as $size)
                <x-ui.button :size="$size" icon="paw-print">{{ $t('button.size_'.$size) }}</x-ui.button>
            @endforeach
        </x-styleguide.example>

        <x-styleguide.example :title="$t('button.icons')">
            <x-slot:code>@verbatim<x-ui.button variant="ghost" icon="x" :label="__('…')" />@endverbatim</x-slot:code>
            <x-ui.button icon="search">{{ $t('button.search') }}</x-ui.button>
            <x-ui.button variant="secondary" icon-trailing="arrow-right">{{ $t('button.next') }}</x-ui.button>
            <x-ui.button variant="ghost" icon="x" :label="$t('button.close')" />
            <x-ui.button variant="secondary" size="sm" icon="heart" :label="$t('button.favorite')" />
        </x-styleguide.example>

        <x-styleguide.example :title="$t('button.states')">
            <x-slot:code>@verbatim<x-ui.button loading>…</x-ui.button>@endverbatim</x-slot:code>
            <x-ui.button disabled>{{ $t('button.disabled') }}</x-ui.button>
            <x-ui.button loading data-demo="loading">{{ $t('button.save') }}</x-ui.button>
            <x-ui.button :href="'#button'">{{ $t('button.as_link') }}</x-ui.button>
            <x-ui.button :href="'#button'" variant="secondary" disabled>{{ $t('button.link_disabled') }}</x-ui.button>
        </x-styleguide.example>

        <x-styleguide.example :title="$t('button.livewire')">
            <x-slot:code>@verbatim<x-ui.button wire:click="save">…</x-ui.button>@endverbatim</x-slot:code>
            <p class="w-full text-small text-fg-muted">{{ $t('button.livewire_hint') }}</p>
            <div x-data="{ busy: false }">
                <x-ui.button
                    data-demo="data-loading"
                    x-bind:data-loading="busy"
                    x-on:click="busy = true; setTimeout(() => busy = false, 1500)"
                >{{ $t('button.simulate') }}</x-ui.button>
            </div>
        </x-styleguide.example>

        <x-styleguide.example :title="$t('button.on_blocks')" background="inverse">
            <x-ui.button variant="highlight">{{ $t('button.variant_highlight') }}</x-ui.button>
        </x-styleguide.example>

        <x-styleguide.example :title="$t('button.on_leaf')" background="block-leaf">
            <x-ui.button variant="highlight" size="lg">{{ $t('button.donate') }}</x-ui.button>
        </x-styleguide.example>
    </x-styleguide.component>

    <x-styleguide.component id="link" :title="$t('link.title')" :description="$t('link.description')" :a11y="[$t('link.a11y_underline'), $t('link.a11y_external')]">
        <x-styleguide.example :title="$t('link.inline')">
            <x-slot:code>@verbatim<x-ui.link :href="route('catalog')">…</x-ui.link>@endverbatim</x-slot:code>
            <p class="max-w-xl">{{ $t('link.sentence_before') }} <x-ui.link href="#link">{{ $t('link.catalog') }}</x-ui.link> {{ $t('link.sentence_after') }}</p>
        </x-styleguide.example>

        <x-styleguide.example :title="$t('link.external_title')">
            <x-slot:code>@verbatim<x-ui.link href="https://…" external>…</x-ui.link>@endverbatim</x-slot:code>
            <x-ui.link href="https://lucide.dev" external>Lucide</x-ui.link>
        </x-styleguide.example>

        <x-styleguide.example :title="$t('link.inherit')" background="block-leaf">
            <x-slot:code>@verbatim<x-ui.link href="…" variant="inherit">…</x-ui.link>@endverbatim</x-slot:code>
            <x-ui.link href="#link" variant="inherit">{{ $t('link.catalog') }}</x-ui.link>
        </x-styleguide.example>
    </x-styleguide.component>

    <x-styleguide.component id="badge" :title="$t('badge.title')" :description="$t('badge.description')" :a11y="[$t('badge.a11y')]">
        <x-styleguide.example :title="$t('badge.variants')">
            <x-slot:code>@verbatim<x-ui.badge variant="success" icon="circle-check">…</x-ui.badge>@endverbatim</x-slot:code>
            @foreach ($badges as $badge)
                <x-ui.badge :variant="$badge" :icon="$badgeIcons[$badge] ?? null">{{ $t('badge.variant_'.$badge) }}</x-ui.badge>
            @endforeach
        </x-styleguide.example>

        <x-styleguide.example :title="$t('badge.on_surface')" background="surface">
            @foreach ($badges as $badge)
                <x-ui.badge :variant="$badge">{{ $t('badge.variant_'.$badge) }}</x-ui.badge>
            @endforeach
        </x-styleguide.example>
    </x-styleguide.component>

    <x-styleguide.component id="tag" :title="$t('tag.title')" :description="$t('tag.description')" :a11y="[$t('tag.a11y_pressed'), $t('tag.a11y_remove')]">
        <x-styleguide.example :title="$t('tag.filters')">
            <x-slot:code>@verbatim<x-ui.tag :pressed="$species === 'dog'" wire:click="…">…</x-ui.tag>@endverbatim</x-slot:code>
            <div role="group" aria-label="{{ $t('tag.filters_label') }}" class="flex flex-wrap gap-2" x-data="{ active: 'all' }" data-demo="filters">
                @foreach (['all', 'dogs', 'cats', 'others'] as $chip)
                    <x-ui.tag
                        :pressed="$chip === 'all'"
                        x-on:click="active = '{{ $chip }}'"
                        x-bind:aria-pressed="(active === '{{ $chip }}').toString()"
                        data-chip="{{ $chip }}"
                    >{{ $t('tag.'.$chip) }}</x-ui.tag>
                @endforeach
            </div>
        </x-styleguide.example>

        <x-styleguide.example :title="$t('tag.removable')">
            <x-slot:code>@verbatim<x-ui.tag removable label="Cani" wire:click="…">Cani</x-ui.tag>@endverbatim</x-slot:code>
            <div class="flex flex-wrap gap-2" x-data="{ tags: ['dogs', 'cats'] }" data-demo="removable">
                @foreach (['dogs', 'cats'] as $chip)
                    <span x-show="tags.includes('{{ $chip }}')" class="contents">
                        <x-ui.tag removable :label="$t('tag.'.$chip)" x-on:click="tags = tags.filter((tag) => tag !== '{{ $chip }}')" data-remove="{{ $chip }}">{{ $t('tag.'.$chip) }}</x-ui.tag>
                    </span>
                @endforeach
            </div>
        </x-styleguide.example>

        <x-styleguide.example :title="$t('tag.static')" background="canvas">
            <x-ui.tag icon="ruler">{{ $t('tag.size') }}</x-ui.tag>
            <x-ui.tag icon="cake">{{ $t('tag.age') }}</x-ui.tag>
        </x-styleguide.example>
    </x-styleguide.component>
</x-styleguide.layout>
