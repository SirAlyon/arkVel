{{--
 | Styleguide · gruppo "overlays": dialog, drawer, dropdown, tooltip, toast.
 | Testi in lang/{it,en}/styleguide_overlays.php. Gli attributi data-demo / data-* sono
 | i punti d'aggancio dei test browser (tests/Browser/Components/OverlaysComponentsTest.php).
--}}
@php
    use Illuminate\Support\Js;

    $t = static fn (string $key, array $replace = []): string => __('styleguide_overlays.'.$key, $replace);

    $toastTypes = ['success', 'warning', 'danger', 'info'];
    $toastButtons = ['success' => 'primary', 'warning' => 'secondary', 'danger' => 'danger', 'info' => 'ghost'];
@endphp
<x-styleguide.layout :title="__('styleguide.page_title', ['group' => __('styleguide.groups.overlays.title'), 'styleguide' => __('styleguide.title')])" current="overlays">
    <div class="flex flex-col gap-3 pt-4 md:pt-8">
        <h1 class="text-h1">{{ __('styleguide.groups.overlays.title') }}</h1>
        <p class="max-w-2xl text-lead text-fg-muted">{{ __('styleguide.groups.overlays.intro') }}</p>
    </div>

    {{-- Dialog --}}
    <x-styleguide.component id="dialog" :title="$t('dialog.title')" :description="$t('dialog.description')" :a11y="[$t('dialog.a11y_native'), $t('dialog.a11y_label'), $t('dialog.a11y_focus'), $t('dialog.a11y_alert')]">
        <x-styleguide.example :title="$t('dialog.basic')">
            <x-slot:code>@verbatim<x-ui.dialog name="adopt" :title="__('…')" :description="__('…')">
    <x-slot:trigger><x-ui.button>…</x-ui.button></x-slot:trigger>
    <p>…</p>
    <x-slot:footer>
        <x-ui.button variant="secondary" x-on:click="close()">…</x-ui.button>
        <x-ui.button wire:click="send">…</x-ui.button>
    </x-slot:footer>
</x-ui.dialog>@endverbatim</x-slot:code>
            <x-ui.dialog name="adoption-info" :title="$t('dialog.basic_title')" :description="$t('dialog.basic_description')">
                <x-slot:trigger>
                    <x-ui.button icon="message-circle" data-demo="dialog-open">{{ $t('dialog.open_basic') }}</x-ui.button>
                </x-slot:trigger>
                <p>{{ $t('dialog.basic_body') }}</p>
                <x-slot:footer>
                    <x-ui.button variant="secondary" x-on:click="close()" data-demo="dialog-cancel">{{ $t('dialog.cancel') }}</x-ui.button>
                    <x-ui.button icon="send" x-on:click="close()">{{ $t('dialog.send') }}</x-ui.button>
                </x-slot:footer>
            </x-ui.dialog>
        </x-styleguide.example>

        <x-styleguide.example :title="$t('dialog.confirm')">
            <x-slot:code>@verbatim<x-ui.button variant="danger" x-on:click="$dispatch('open-dialog', 'delete-photo')">…</x-ui.button>

<x-ui.dialog name="delete-photo" alert icon="trash-2" :title="__('…')" :description="__('…')">
    <x-slot:footer>
        <x-ui.button variant="secondary" autofocus x-on:click="close()">…</x-ui.button>
        <x-ui.button variant="danger" wire:click="deletePhoto" x-on:click="close()">…</x-ui.button>
    </x-slot:footer>
</x-ui.dialog>@endverbatim</x-slot:code>
            <x-ui.button variant="danger" icon="trash-2" x-data x-on:click="$dispatch('open-dialog', 'delete-photo')" data-demo="confirm-open">{{ $t('dialog.open_confirm') }}</x-ui.button>
            <x-ui.dialog name="delete-photo" alert icon="trash-2" size="sm" :title="$t('dialog.confirm_title')" :description="$t('dialog.confirm_description')">
                <x-slot:footer>
                    <x-ui.button variant="secondary" autofocus x-on:click="close()" data-demo="confirm-cancel">{{ $t('dialog.cancel') }}</x-ui.button>
                    <x-ui.button variant="danger" icon="trash-2" x-on:click="close()" data-demo="confirm-delete">{{ $t('dialog.delete') }}</x-ui.button>
                </x-slot:footer>
            </x-ui.dialog>
        </x-styleguide.example>

        <x-styleguide.example :title="$t('dialog.model')">
            <x-slot:code>@verbatim<x-ui.dialog wire:model="showReminder" :title="__('…')">…</x-ui.dialog>

<div x-data="{ open: false }">
    <x-ui.button x-on:click="open = true">…</x-ui.button>
    <x-ui.dialog x-model="open" :title="__('…')">…</x-ui.dialog>
</div>@endverbatim</x-slot:code>
            <div x-data="{ open: false }" class="flex flex-wrap items-center gap-4">
                <p class="w-full text-small text-fg-muted">{{ $t('dialog.model_hint') }}</p>
                <x-ui.button variant="secondary" icon="calendar" x-on:click="open = true" data-demo="model-open">{{ $t('dialog.open_model') }}</x-ui.button>
                <p class="text-small">
                    {{ $t('dialog.model_state') }}
                    <strong data-demo="model-state" x-text="open ? @js($t('dialog.model_open')) : @js($t('dialog.model_closed'))">{{ $t('dialog.model_closed') }}</strong>
                </p>
                <x-ui.dialog x-model="open" icon="calendar" :title="$t('dialog.model_title')" data-demo="model-dialog">
                    <p>{{ $t('dialog.model_body') }}</p>
                    <x-slot:footer>
                        <x-ui.button x-on:click="close()">{{ $t('dialog.ok') }}</x-ui.button>
                    </x-slot:footer>
                </x-ui.dialog>
            </div>
        </x-styleguide.example>
    </x-styleguide.component>

    {{-- Drawer --}}
    <x-styleguide.component id="drawer" :title="$t('drawer.title')" :description="$t('drawer.description')" :a11y="[$t('drawer.a11y_dialog'), $t('drawer.a11y_sheet'), $t('drawer.a11y_sticky')]">
        <x-styleguide.example :title="$t('drawer.filters')">
            <x-slot:code>@verbatim<x-ui.drawer name="filters" sheet :title="__('…')" :description="__('…')">
    <x-slot:trigger><x-ui.button variant="secondary" icon="sliders-horizontal">…</x-ui.button></x-slot:trigger>
    …
    <x-slot:footer><x-ui.button x-on:click="close()">…</x-ui.button></x-slot:footer>
</x-ui.drawer>@endverbatim</x-slot:code>
            <x-ui.drawer name="filters" sheet :title="$t('drawer.filters_title')" :description="$t('drawer.filters_description')">
                <x-slot:trigger>
                    <x-ui.button variant="secondary" icon="sliders-horizontal" data-demo="drawer-open">{{ $t('drawer.open_filters') }}</x-ui.button>
                </x-slot:trigger>

                @php
                    // Gruppi di filtri della demo: chiave → [opzioni, opzione scelta]. Abbastanza
                    // da far scorrere il pannello (test di header e footer fissi, WCAG 2.4.11).
                    $filters = [
                        'species' => [['dogs', 'cats', 'others'], 'dogs'],
                        'size' => [['small', 'medium', 'large'], 'medium'],
                        'age' => [['puppy', 'young', 'adult', 'senior'], 'young'],
                        'good_with' => [['kids', 'with_dogs', 'with_cats'], 'kids'],
                    ];
                    $chosenFilters = array_map(static fn (array $filter): string => $filter[1], $filters);
                @endphp
                {{-- Nei tag dei componenti @js non si compila: Js::from è lo stesso, dentro {{ }}. --}}
                <div x-data="@js($chosenFilters)" class="flex flex-col gap-6">
                    @foreach ($filters as $filter => $spec)
                        @php([$options, $chosen] = $spec)
                        <fieldset class="flex flex-col gap-3">
                            <legend class="mb-3 text-h5">{{ $t('drawer.'.$filter) }}</legend>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($options as $option)
                                    <x-ui.tag
                                        :pressed="$option === $chosen"
                                        x-on:click="{{ $filter }} = {{ Js::from($option) }}"
                                        x-bind:aria-pressed="({{ $filter }} === {{ Js::from($option) }}).toString()"
                                        :data-demo="$loop->parent->last && $loop->last ? 'drawer-last' : null"
                                    >{{ $t('drawer.'.$option) }}</x-ui.tag>
                                @endforeach
                            </div>
                        </fieldset>
                    @endforeach
                </div>

                <x-slot:footer>
                    <x-ui.button variant="ghost" icon="rotate-ccw" x-on:click="$dispatch('toast', {{ Js::from(['type' => 'info', 'title' => $t('drawer.reset_toast')]) }})" data-demo="drawer-reset">{{ $t('drawer.reset') }}</x-ui.button>
                    <x-ui.button x-on:click="close()" data-demo="drawer-apply">{{ $t('drawer.show_results') }}</x-ui.button>
                </x-slot:footer>
            </x-ui.drawer>
        </x-styleguide.example>

        <x-styleguide.example :title="$t('drawer.menu')">
            <x-slot:code>@verbatim<x-ui.drawer side="left" :title="__('…')">…</x-ui.drawer>@endverbatim</x-slot:code>
            <x-ui.drawer name="menu" side="left" icon="paw-print" :title="$t('drawer.menu_title')">
                <x-slot:trigger>
                    <x-ui.button variant="secondary" icon="menu" :label="$t('drawer.open_menu')" data-demo="menu-open" />
                </x-slot:trigger>
                <nav aria-label="{{ $t('drawer.menu_label') }}">
                    <ul class="flex flex-col gap-1">
                        @foreach (['animals' => 'paw-print', 'organizations' => 'house', 'how' => 'circle-help', 'donate' => 'heart'] as $item => $icon)
                            <li>
                                <a href="#drawer" class="flex min-h-12 items-center gap-3 rounded-full px-4 font-semibold text-fg no-underline transition-colors duration-(--duration-fast) ease-out hover:bg-surface">
                                    <x-ui.icon :name="$icon" />
                                    {{ $t('drawer.nav_'.$item) }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </nav>
            </x-ui.drawer>
        </x-styleguide.example>

        <x-styleguide.example :title="$t('drawer.bottom')">
            <x-slot:code>@verbatim<x-ui.drawer side="bottom" :title="__('…')">…</x-ui.drawer>@endverbatim</x-slot:code>
            <x-ui.drawer name="share" side="bottom" :title="$t('drawer.bottom_title')">
                <x-slot:trigger>
                    <x-ui.button variant="secondary" icon="share-2" data-demo="bottom-open">{{ $t('drawer.open_bottom') }}</x-ui.button>
                </x-slot:trigger>
                <div class="flex flex-wrap gap-3">
                    <x-ui.button variant="secondary" icon="link" x-on:click="close()">{{ $t('drawer.copy_link') }}</x-ui.button>
                    <x-ui.button variant="secondary" icon="mail" x-on:click="close()">{{ $t('drawer.email') }}</x-ui.button>
                </div>
            </x-ui.drawer>
        </x-styleguide.example>
    </x-styleguide.component>

    {{-- Dropdown --}}
    <x-styleguide.component id="dropdown" :title="$t('dropdown.title')" :description="$t('dropdown.description')" :a11y="[$t('dropdown.a11y_button'), $t('dropdown.a11y_keys'), $t('dropdown.a11y_danger')]">
        <x-styleguide.example :title="$t('dropdown.actions')">
            <x-slot:code>@verbatim<x-ui.dropdown :label="__('…')">
    <x-ui.dropdown.item icon="pencil" wire:click="edit">…</x-ui.dropdown.item>
    <x-ui.dropdown.item icon="share-2" :href="route('…')">…</x-ui.dropdown.item>
    <x-ui.dropdown.separator />
    <x-ui.dropdown.item icon="archive" disabled>…</x-ui.dropdown.item>
    <x-ui.dropdown.item icon="trash-2" variant="danger" wire:click="delete">…</x-ui.dropdown.item>
</x-ui.dropdown>@endverbatim</x-slot:code>
            <div x-data="{ choice: @js($t('dropdown.none')) }" class="flex flex-wrap items-center gap-4">
                <x-ui.dropdown :label="$t('dropdown.label')" data-demo="dropdown-actions">
                    @foreach (['edit' => 'pencil', 'duplicate' => 'copy'] as $action => $icon)
                        <x-ui.dropdown.item :icon="$icon" x-on:click="choice = {{ Js::from($t('dropdown.'.$action)) }}" data-item="{{ $action }}">{{ $t('dropdown.'.$action) }}</x-ui.dropdown.item>
                    @endforeach
                    <x-ui.dropdown.item icon="share-2" href="#dropdown" data-item="share">{{ $t('dropdown.share') }}</x-ui.dropdown.item>
                    <x-ui.dropdown.separator />
                    <x-ui.dropdown.item icon="archive" disabled x-on:click="choice = 'archive'" data-item="archive">{{ $t('dropdown.archive') }}</x-ui.dropdown.item>
                    <x-ui.dropdown.item icon="trash-2" variant="danger" x-on:click="choice = {{ Js::from($t('dropdown.delete')) }}" data-item="delete">{{ $t('dropdown.delete') }}</x-ui.dropdown.item>
                </x-ui.dropdown>
                <p class="text-small">{{ $t('dropdown.chosen') }} <strong x-text="choice" data-demo="dropdown-choice">{{ $t('dropdown.none') }}</strong></p>
            </div>
        </x-styleguide.example>

        <x-styleguide.example :title="$t('dropdown.icon_only')" class="justify-end">
            <x-slot:code>@verbatim<x-ui.dropdown :label="__('…')" icon="ellipsis-vertical" icon-only variant="ghost" align="end">…</x-ui.dropdown>@endverbatim</x-slot:code>
            <x-ui.dropdown :label="$t('dropdown.more')" icon="ellipsis-vertical" icon-only variant="ghost" align="end" data-demo="dropdown-more">
                <x-ui.dropdown.item icon="share-2">{{ $t('dropdown.share') }}</x-ui.dropdown.item>
                <x-ui.dropdown.item icon="flag" variant="danger">{{ $t('dropdown.report') }}</x-ui.dropdown.item>
            </x-ui.dropdown>
        </x-styleguide.example>

        <x-styleguide.example :title="$t('dropdown.on_inverse')" background="inverse">
            <x-slot:code>@verbatim<x-ui.dropdown :label="__('…')" icon="circle-user" variant="highlight">…</x-ui.dropdown>@endverbatim</x-slot:code>
            <x-ui.dropdown :label="$t('dropdown.account')" icon="circle-user" variant="highlight" data-demo="dropdown-account">
                <x-ui.dropdown.item icon="user" href="#dropdown">{{ $t('dropdown.profile') }}</x-ui.dropdown.item>
                <x-ui.dropdown.item icon="heart" href="#dropdown">{{ $t('dropdown.favorites') }}</x-ui.dropdown.item>
                <x-ui.dropdown.separator />
                <x-ui.dropdown.item icon="log-out">{{ $t('dropdown.logout') }}</x-ui.dropdown.item>
            </x-ui.dropdown>
        </x-styleguide.example>
    </x-styleguide.component>

    {{-- Tooltip --}}
    <x-styleguide.component id="tooltip" :title="$t('tooltip.title')" :description="$t('tooltip.description')" :a11y="[$t('tooltip.a11y_describedby'), $t('tooltip.a11y_hover')]">
        <x-styleguide.example :title="$t('tooltip.icons')">
            <x-slot:code>@verbatim<x-ui.tooltip :text="__('…')">
    <x-ui.button variant="ghost" icon="share-2" :label="__('…')" />
</x-ui.tooltip>@endverbatim</x-slot:code>
            <x-ui.tooltip :text="$t('tooltip.share_tip')" data-demo="tooltip-share">
                <x-ui.button variant="ghost" icon="share-2" :label="$t('tooltip.share')" />
            </x-ui.tooltip>
            <x-ui.tooltip :text="$t('tooltip.favorite_tip')">
                <x-ui.button variant="secondary" icon="heart" :label="$t('tooltip.favorite')" />
            </x-ui.tooltip>
            <x-ui.tooltip :text="$t('tooltip.print_tip')" position="bottom">
                <x-ui.button variant="ghost" icon="printer" :label="$t('tooltip.print')" />
            </x-ui.tooltip>
        </x-styleguide.example>

        <x-styleguide.example :title="$t('tooltip.positions')" background="surface">
            <x-slot:code>@verbatim<x-ui.tooltip :text="__('…')" position="right">…</x-ui.tooltip>@endverbatim</x-slot:code>
            @foreach (['top', 'bottom', 'left', 'right'] as $position)
                <x-ui.tooltip :text="$t('tooltip.'.$position.'_tip')" :position="$position">
                    <x-ui.button variant="secondary" size="sm">{{ $t('tooltip.'.$position) }}</x-ui.button>
                </x-ui.tooltip>
            @endforeach
        </x-styleguide.example>
    </x-styleguide.component>

    {{-- Toast --}}
    <x-styleguide.component id="toast" :title="$t('toast.title')" :description="$t('toast.description')" :a11y="[$t('toast.a11y_live'), $t('toast.a11y_type'), $t('toast.a11y_timing'), $t('toast.a11y_focus'), $t('toast.a11y_modal'), $t('toast.a11y_static')]">
        <x-styleguide.example :title="$t('toast.launch')">
            <x-slot:code>@verbatim<x-ui.toaster />  {{-- una volta per layout --}}

<x-ui.button x-on:click="$dispatch('toast', { type: 'success', title: '…', description: '…' })">…</x-ui.button>
window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'danger', title: '…' } }));@endverbatim</x-slot:code>
            @foreach ($toastTypes as $type)
                <x-ui.button
                    :variant="$toastButtons[$type]"
                    x-data
                    x-on:click="$dispatch('toast', {{ Js::from(['type' => $type, 'title' => $t('toast.'.$type.'_title'), 'description' => $t('toast.'.$type.'_description')]) }})"
                    data-toast-demo="{{ $type }}"
                >{{ $t('toast.'.$type) }}</x-ui.button>
            @endforeach
        </x-styleguide.example>

        <x-styleguide.example :title="$t('toast.variants')" background="surface" class="flex-col items-stretch">
            <x-slot:code>@verbatim<x-ui.toast type="warning" :title="__('…')" :description="__('…')" />@endverbatim</x-slot:code>
            @foreach ($toastTypes as $type)
                <x-ui.toast :type="$type" :title="$t('toast.'.$type.'_title')" :description="$t('toast.'.$type.'_description')" class="max-w-sm" />
            @endforeach
        </x-styleguide.example>

        <x-styleguide.example :title="$t('toast.livewire')">
            <x-slot:code>@verbatim// Componente Livewire
$this->dispatch('toast', type: 'success', title: __('…'), description: __('…'));

// Controller, dopo un redirect
return redirect()->route('…')->with('toast', ['type' => 'success', 'title' => __('…')]);@endverbatim</x-slot:code>
            <p class="text-small text-fg-muted">{{ $t('toast.livewire_hint') }}</p>
        </x-styleguide.example>
    </x-styleguide.component>

    <x-ui.toaster />
</x-styleguide.layout>
