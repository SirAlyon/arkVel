{{--
 | Styleguide · gruppo "pickers": combobox e date-picker (wrapper di Cally).
 | Testi in lang/{it,en}/styleguide_pickers.php. Ogni demo ha attributi data-* stabili
 | per i test browser (tests/Browser/Components/PickersComponentsTest.php).
 |
 | Le date delle demo partono da oggi, così la pagina resta sensata nel tempo.
--}}
@php
    $t = static fn (string $key, array $replace = []): string => __('styleguide_pickers.'.$key, $replace);

    $breeds = collect(['labrador', 'golden', 'german_shepherd', 'border_collie', 'beagle', 'dachshund', 'jack_russell', 'poodle', 'irish_setter', 'epagneul', 'maremma', 'mixed'])
        ->mapWithKeys(static fn (string $breed): array => [$breed => $t('breeds.'.$breed)])
        ->all();
    $shelters = collect(['faro', 'querce', 'gattopardo', 'coda'])
        ->mapWithKeys(static fn (string $shelter): array => [$shelter => ['label' => $t('shelters.'.$shelter), 'description' => $t('shelters.'.$shelter.'_city')]])
        ->all();
    $species = collect(['dog', 'cat', 'rabbit', 'ferret', 'guinea_pig'])
        ->mapWithKeys(static fn (string $item): array => [$item => $t('species.'.$item)])
        ->all();
    $towns = collect(['milano', 'monza', 'modena', 'macerata', 'forli', 'cefalu'])
        ->mapWithKeys(static fn (string $town): array => [$town => $t('towns.'.$town)])
        ->all();

    $today = today();
    $holidays = [$today->copy()->addDays(3)->format('Y-m-d'), $today->copy()->addDays(10)->format('Y-m-d')];
@endphp
<x-styleguide.layout :title="__('styleguide.page_title', ['group' => __('styleguide.groups.pickers.title'), 'styleguide' => __('styleguide.title')])" current="pickers">
    <div class="flex flex-col gap-3 pt-4 md:pt-8">
        <h1 class="text-h1">{{ __('styleguide.groups.pickers.title') }}</h1>
        <p class="max-w-2xl text-lead text-fg-muted">{{ __('styleguide.groups.pickers.intro') }}</p>
    </div>

    {{-- combobox --}}
    <x-styleguide.component id="combobox" :title="$t('combobox.title')" :description="$t('combobox.description')" :a11y="[$t('combobox.a11y_pattern'), $t('combobox.a11y_keyboard'), $t('combobox.a11y_state'), $t('combobox.a11y_live')]">
        <x-styleguide.example :title="$t('combobox.basic')">
            <x-slot:code>@verbatim<x-ui.field name="breed" :label="__('…')" :help="__('…')">
    <x-ui.combobox wire:model="breed" :options="$breeds" :placeholder="__('…')" />
</x-ui.field>@endverbatim</x-slot:code>
            <div class="w-full max-w-md" data-demo="combobox-basic">
                <x-ui.field name="breed" :label="$t('combobox.breed')" :help="$t('combobox.breed_help')">
                    <x-ui.combobox :options="$breeds" :placeholder="$t('combobox.breed_placeholder')" />
                </x-ui.field>
            </div>
        </x-styleguide.example>

        <x-styleguide.example :title="$t('combobox.described')">
            <x-slot:code>@verbatim<x-ui.combobox name="shelter" value="querce" :options="[
    'querce' => ['label' => __('…'), 'description' => __('…')],
    …
]" />@endverbatim</x-slot:code>
            <div class="w-full max-w-md" data-demo="combobox-described">
                <x-ui.field name="shelter" :label="$t('combobox.shelter')">
                    <x-ui.combobox :options="$shelters" value="querce" />
                </x-ui.field>
            </div>
        </x-styleguide.example>

        <x-styleguide.example :title="$t('combobox.model')">
            <x-slot:code>@verbatim<div x-data="{ species: 'cat' }">
    <x-ui.combobox x-model="species" :options="$species" :aria-label="__('…')" />
    <output x-text="species"></output>
</div>@endverbatim</x-slot:code>
            <div class="flex w-full max-w-md flex-col gap-3" x-data="{ species: 'cat' }" data-demo="combobox-model">
                <x-ui.field name="species" :label="$t('combobox.species')">
                    <x-ui.combobox x-model="species" :options="$species" />
                </x-ui.field>
                <p class="text-small text-fg-muted">{{ $t('combobox.current') }} <output class="font-semibold text-fg" x-text="species || @js($t('combobox.empty_value'))" data-demo-value>cat</output></p>
            </div>
        </x-styleguide.example>

        <x-styleguide.example :title="$t('combobox.error_title')">
            <x-slot:code>@verbatim<x-ui.field name="breed" :label="__('…')" required>
    <x-ui.combobox :options="$breeds" />
</x-ui.field>@endverbatim</x-slot:code>
            <div class="grid w-full gap-6 md:grid-cols-2">
                <x-ui.field name="breed_required" :label="$t('combobox.breed')" :error="$t('combobox.error')" required>
                    <x-ui.combobox :options="$breeds" :placeholder="$t('combobox.breed_placeholder')" />
                </x-ui.field>
                <x-ui.field name="breed_disabled" :label="$t('combobox.disabled')">
                    <x-ui.combobox :options="$breeds" value="beagle" disabled />
                </x-ui.field>
            </div>
        </x-styleguide.example>

        <x-styleguide.example :title="$t('combobox.standalone')" background="surface">
            <x-slot:code>@verbatim<x-ui.combobox name="town" icon="search" :options="$towns" :aria-label="__('…')" :placeholder="__('…')" />@endverbatim</x-slot:code>
            <div class="w-full max-w-md" data-demo="combobox-standalone">
                <x-ui.combobox name="town" icon="search" :options="$towns" :aria-label="$t('combobox.standalone_label')" :placeholder="$t('combobox.standalone_label')" />
            </div>
        </x-styleguide.example>

        <x-styleguide.example :title="$t('combobox.server')">
            <x-slot:code>@verbatim{{-- componente Livewire: public string $shelterQuery = ''; public ?int $shelterId = null;
     #[Computed] public function shelters(): array { return Organization::search($this->shelterQuery)->take(20)->get()->pluck('name', 'id')->all(); } --}}
<x-ui.combobox wire:model="shelterId" search="shelterQuery" :options="$this->shelters" icon="search" :aria-label="__('…')" />@endverbatim</x-slot:code>
            <p class="max-w-2xl text-small text-fg-muted">{{ $t('combobox.server_text') }}</p>
        </x-styleguide.example>
    </x-styleguide.component>

    {{-- date-picker --}}
    <x-styleguide.component id="date-picker" :title="$t('date_picker.title')" :description="$t('date_picker.description')" :a11y="[$t('date_picker.a11y_dialog'), $t('date_picker.a11y_keyboard'), $t('date_picker.a11y_days'), $t('date_picker.a11y_live')]">
        <x-styleguide.example :title="$t('date_picker.popover')">
            <x-slot:code>@verbatim<x-ui.field name="visit_date" :label="__('…')" :help="__('…')">
    <x-ui.date-picker wire:model="visitDate" :min="today()" />
</x-ui.field>@endverbatim</x-slot:code>
            <div class="w-full max-w-md" data-demo="date-popover">
                <x-ui.field name="visit_date" :label="$t('date_picker.visit')" :help="$t('date_picker.visit_help')">
                    <x-ui.date-picker :min="$today" :value="$today->copy()->addDays(7)" />
                </x-ui.field>
            </div>
        </x-styleguide.example>

        <x-styleguide.example :title="$t('date_picker.disallowed')">
            <x-slot:code>@verbatim{{-- 0 = domenica; più alcune date ISO --}}
<x-ui.date-picker name="pickup_date" :min="today()" :disallowed="[0, '2026-12-25', '2026-12-26']" clearable />@endverbatim</x-slot:code>
            <div class="w-full max-w-md" data-demo="date-disallowed">
                <x-ui.field name="pickup_date" :label="$t('date_picker.pickup')" :help="$t('date_picker.pickup_help')">
                    <x-ui.date-picker :min="$today" :disallowed="[0, ...$holidays]" clearable />
                </x-ui.field>
            </div>
        </x-styleguide.example>

        <x-styleguide.example :title="$t('date_picker.model')">
            <x-slot:code>@verbatim<div x-data="{ birthDate: '' }">
    <x-ui.date-picker x-model="birthDate" :max="today()" :aria-label="__('…')" />
    <output x-text="birthDate"></output>
</div>@endverbatim</x-slot:code>
            <div class="flex w-full max-w-md flex-col gap-3" x-data="{ birthDate: '' }" data-demo="date-model">
                <x-ui.field name="birth_date" :label="$t('date_picker.birth')">
                    <x-ui.date-picker x-model="birthDate" :max="$today" clearable />
                </x-ui.field>
                <p class="text-small text-fg-muted">{{ $t('date_picker.current') }} <output class="font-semibold text-fg" x-text="birthDate || @js($t('date_picker.empty_value'))" data-demo-value>{{ $t('date_picker.empty_value') }}</output></p>
            </div>
        </x-styleguide.example>

        <x-styleguide.example :title="$t('date_picker.error_title')">
            <x-slot:code>@verbatim<x-ui.field name="visit_date" :label="__('…')" required>
    <x-ui.date-picker :min="today()" />
</x-ui.field>@endverbatim</x-slot:code>
            <div class="grid w-full gap-6 md:grid-cols-2">
                <x-ui.field name="visit_required" :label="$t('date_picker.visit')" :error="$t('date_picker.error')" required>
                    <x-ui.date-picker :min="$today" />
                </x-ui.field>
                <x-ui.field name="visit_disabled" :label="$t('date_picker.disabled')">
                    <x-ui.date-picker :value="$today" disabled />
                </x-ui.field>
            </div>
        </x-styleguide.example>

        <x-styleguide.example :title="$t('date_picker.inline')" background="surface">
            <x-slot:code>@verbatim<x-ui.date-picker inline name="slot_date" wire:model.live="slotDate" :aria-label="__('…')"
    :min="today()" :max="today()->addMonths(2)" :disallowed="[0]" />@endverbatim</x-slot:code>
            <div class="w-full" data-demo="date-inline">
                <x-ui.date-picker inline name="slot_date" :aria-label="$t('date_picker.inline_label')" :min="$today" :max="$today->copy()->addMonths(2)" :disallowed="[0]" />
            </div>
        </x-styleguide.example>
    </x-styleguide.component>
</x-styleguide.layout>
