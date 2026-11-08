{{--
 | Styleguide · gruppo "forms": field, input, textarea, select, checkbox, checkbox-group,
 | radio, radio-group, switch, e un modulo d'esempio completo (candidatura) con gli errori.
 | Testi in lang/{it,en}/styleguide_forms.php. Ogni demo ha attributi data-* stabili
 | per i test browser (tests/Browser/Components/FormsComponentsTest.php).
 |
 | Il modulo d'esempio si invia in GET su questa pagina con ?demo=errors e mostra gli
 | errori di validazione come li mostrerebbe il server (prop `error` dei componenti),
 | con un riepilogo in cima che riceve il focus (è il frammento dell'action).
--}}
@php
    $t = static fn (string $key, array $replace = []): string => __('styleguide_forms.'.$key, $replace);

    $types = [
        'text' => ['icon' => null, 'attributes' => ['autocomplete' => 'name']],
        'email' => ['icon' => 'mail', 'attributes' => ['autocomplete' => 'email']],
        'tel' => ['icon' => 'phone', 'attributes' => ['autocomplete' => 'tel']],
        'number' => ['icon' => null, 'attributes' => ['min' => 0, 'max' => 30, 'inputmode' => 'numeric']],
        'url' => ['icon' => 'link', 'attributes' => ['autocomplete' => 'url']],
        'search' => ['icon' => 'search', 'attributes' => []],
        'date' => ['icon' => null, 'attributes' => []],
    ];
    $species = ['dogs' => $t('options.dogs'), 'cats' => $t('options.cats'), 'others' => $t('options.others')];
    $homes = [
        'house' => ['label' => $t('options.house'), 'description' => $t('options.house_description')],
        'flat' => ['label' => $t('options.flat'), 'description' => $t('options.flat_description')],
        'other' => ['label' => $t('options.other')],
    ];
    $provinces = [
        $t('options.north') => ['MI' => 'Milano', 'TO' => 'Torino', 'BO' => 'Bologna'],
        $t('options.centre') => ['FI' => 'Firenze', 'RM' => 'Roma'],
        $t('options.south') => ['NA' => 'Napoli', 'BA' => 'Bari', 'PA' => 'Palermo'],
    ];

    // Modulo d'esempio: dopo l'invio (?demo=errors) mostra gli errori dei campi obbligatori.
    $demoErrors = request()->query('demo') === 'errors' ? [
        'apply-name' => $t('example.error_name'),
        'apply-email' => $t('example.error_email'),
        'field-home-house' => $t('example.error_home'),
        'apply-privacy' => $t('example.error_privacy'),
    ] : [];
    $demoError = static fn (string $id): ?string => $demoErrors[$id] ?? null;
@endphp
<x-styleguide.layout :title="__('styleguide.page_title', ['group' => __('styleguide.groups.forms.title'), 'styleguide' => __('styleguide.title')])" current="forms">
    <div class="flex flex-col gap-3 pt-4 md:pt-8">
        <h1 class="text-h1">{{ __('styleguide.groups.forms.title') }}</h1>
        <p class="max-w-2xl text-lead text-fg-muted">{{ __('styleguide.groups.forms.intro') }}</p>
    </div>

    {{-- field --}}
    <x-styleguide.component id="field" :title="$t('field.title')" :description="$t('field.description')" :a11y="[$t('field.a11y_label'), $t('field.a11y_described'), $t('field.a11y_error'), $t('field.a11y_required')]">
        <x-styleguide.example :title="$t('field.basic')">
            <x-slot:code>@verbatim<x-ui.field name="name" :label="__('…')" :help="__('…')">
    <x-ui.input wire:model.blur="name" autocomplete="name" />
</x-ui.field>@endverbatim</x-slot:code>
            <div class="grid w-full gap-6 md:grid-cols-2">
                <x-ui.field name="field_name" :label="$t('field.name')" :help="$t('field.name_help')">
                    <x-ui.input autocomplete="name" />
                </x-ui.field>
            </div>
        </x-styleguide.example>

        <x-styleguide.example :title="$t('field.indicators')">
            <x-slot:code>@verbatim<x-ui.field name="email" :label="__('…')" required>…</x-ui.field>
<x-ui.field name="phone" :label="__('…')" optional>…</x-ui.field>@endverbatim</x-slot:code>
            <div class="grid w-full gap-6 md:grid-cols-2">
                <x-ui.field name="field_email" :label="$t('field.email')" required>
                    <x-ui.input type="email" autocomplete="email" />
                </x-ui.field>
                <x-ui.field name="field_phone" :label="$t('field.phone')" optional>
                    <x-ui.input type="tel" autocomplete="tel" />
                </x-ui.field>
            </div>
        </x-styleguide.example>

        <x-styleguide.example :title="$t('field.error_title')">
            <x-slot:code>@verbatim{{-- l'errore arriva da $errors->first('email'), oppure: --}}
<x-ui.field name="email" :label="__('…')" :error="__('…')">…</x-ui.field>@endverbatim</x-slot:code>
            <div class="grid w-full gap-6 md:grid-cols-2" data-demo="field-error">
                <x-ui.field name="field_email_error" :label="$t('field.email')" :help="$t('field.email_help')" :error="$t('field.email_error')" required>
                    <x-ui.input type="email" autocomplete="email" value="anna@" />
                </x-ui.field>
            </div>
        </x-styleguide.example>

        <x-styleguide.example :title="$t('field.hidden_label')">
            <x-slot:code>@verbatim<x-ui.field name="q" :label="__('…')" hide-label>
    <x-ui.input type="search" icon="search" :placeholder="__('…')" />
</x-ui.field>@endverbatim</x-slot:code>
            <div class="w-full max-w-md">
                <x-ui.field name="field_search" :label="$t('field.search')" hide-label>
                    <x-ui.input type="search" icon="search" :placeholder="$t('field.search_placeholder')" />
                </x-ui.field>
            </div>
        </x-styleguide.example>

        <x-styleguide.example :title="$t('field.group_title')">
            <x-slot:code>@verbatim<x-ui.field group :label="__('…')" :help="__('…')">
    <x-ui.input type="time" name="from" :aria-label="__('…')" />
    <x-ui.input type="time" name="to" :aria-label="__('…')" />
</x-ui.field>@endverbatim</x-slot:code>
            <x-ui.field group name="field_slot" :label="$t('field.slot')" :help="$t('field.slot_help')" class="w-full max-w-md">
                <div class="flex flex-wrap items-center gap-3">
                    <x-ui.input type="time" name="field_slot_from" :aria-label="$t('field.slot_from')" value="10:00" class="w-auto flex-1" />
                    <span aria-hidden="true" class="text-fg-muted">–</span>
                    <x-ui.input type="time" name="field_slot_to" :aria-label="$t('field.slot_to')" value="12:30" class="w-auto flex-1" />
                </div>
            </x-ui.field>
        </x-styleguide.example>
    </x-styleguide.component>

    {{-- input --}}
    <x-styleguide.component id="input" :title="$t('input.title')" :description="$t('input.description')" :a11y="[$t('input.a11y_height'), $t('input.a11y_focus'), $t('input.a11y_password'), $t('input.a11y_standalone')]">
        <x-styleguide.example :title="$t('input.types')">
            <x-slot:code>@verbatim<x-ui.input type="email" icon="mail" wire:model.blur="email" autocomplete="email" />@endverbatim</x-slot:code>
            <div class="grid w-full gap-6 md:grid-cols-2">
                @foreach ($types as $type => $config)
                    <x-ui.field :name="'type_'.$type" :label="$t('input.type_'.$type)">
                        <x-ui.input :type="$type" :icon="$config['icon']" :attributes="new \Illuminate\View\ComponentAttributeBag($config['attributes'])" />
                    </x-ui.field>
                @endforeach
            </div>
        </x-styleguide.example>

        <x-styleguide.example :title="$t('input.adornments')">
            <x-slot:code>@verbatim<x-ui.input type="number" prefix="€" min="1" />
<x-ui.input type="number" suffix="kg" />
<x-ui.input icon-trailing="map-pin" />@endverbatim</x-slot:code>
            <div class="grid w-full gap-6 md:grid-cols-3">
                <x-ui.field name="input_amount" :label="$t('input.amount')">
                    <x-ui.input type="number" prefix="€" min="1" inputmode="decimal" value="25" />
                </x-ui.field>
                <x-ui.field name="input_weight" :label="$t('input.weight')">
                    <x-ui.input type="number" suffix="kg" min="0" inputmode="decimal" />
                </x-ui.field>
                <x-ui.field name="input_city" :label="$t('input.city')">
                    <x-ui.input icon-trailing="map-pin" autocomplete="address-level2" />
                </x-ui.field>
            </div>
        </x-styleguide.example>

        <x-styleguide.example :title="$t('input.password')">
            <x-slot:code>@verbatim<x-ui.input type="password" autocomplete="current-password" />@endverbatim</x-slot:code>
            <div class="w-full max-w-md" data-demo="password">
                <x-ui.field name="demo_password" for="demo-password" :label="$t('input.password_label')" :help="$t('input.password_help')">
                    <x-ui.input type="password" autocomplete="new-password" value="zampe-felici-2026" />
                </x-ui.field>
            </div>
        </x-styleguide.example>

        <x-styleguide.example :title="$t('input.states')">
            <x-slot:code>@verbatim<x-ui.input disabled />
<x-ui.input readonly value="…" />@endverbatim</x-slot:code>
            <div class="grid w-full gap-6 md:grid-cols-3">
                <x-ui.field name="input_invalid" :label="$t('input.invalid')" :error="$t('input.invalid_error')">
                    <x-ui.input type="tel" value="12" />
                </x-ui.field>
                <x-ui.field name="input_disabled" :label="$t('input.disabled')">
                    <x-ui.input :value="$t('input.disabled_value')" disabled />
                </x-ui.field>
                <x-ui.field name="input_readonly" :label="$t('input.readonly')">
                    <x-ui.input value="ARK-2026-0042" readonly />
                </x-ui.field>
            </div>
        </x-styleguide.example>

        <x-styleguide.example :title="$t('input.standalone')" background="surface">
            <x-slot:code>@verbatim<x-ui.input type="search" icon="search" :aria-label="__('…')" wire:model.live.debounce="q" />@endverbatim</x-slot:code>
            <x-ui.input type="search" icon="search" :aria-label="$t('input.standalone_label')" :placeholder="$t('input.standalone_placeholder')" class="max-w-md" />
        </x-styleguide.example>
    </x-styleguide.component>

    {{-- textarea --}}
    <x-styleguide.component id="textarea" :title="$t('textarea.title')" :description="$t('textarea.description')" :a11y="[$t('textarea.a11y_counter'), $t('textarea.a11y_resize')]">
        <x-styleguide.example :title="$t('textarea.basic')">
            <x-slot:code>@verbatim<x-ui.field name="notes" :label="__('…')">
    <x-ui.textarea wire:model="notes" rows="3" />
</x-ui.field>@endverbatim</x-slot:code>
            <x-ui.field name="textarea_notes" :label="$t('textarea.notes')" class="w-full max-w-2xl">
                <x-ui.textarea rows="3" :placeholder="$t('textarea.notes_placeholder')" />
            </x-ui.field>
        </x-styleguide.example>

        <x-styleguide.example :title="$t('textarea.counter_title')">
            <x-slot:code>@verbatim<x-ui.textarea maxlength="120" counter autoresize />@endverbatim</x-slot:code>
            <div class="w-full max-w-2xl" data-demo="counter">
                <x-ui.field name="textarea_story" for="demo-counter" :label="$t('textarea.story')" :help="$t('textarea.story_help')">
                    <x-ui.textarea maxlength="120" counter autoresize rows="2">{{ $t('textarea.story_value') }}</x-ui.textarea>
                </x-ui.field>
            </div>
        </x-styleguide.example>

        <x-styleguide.example :title="$t('textarea.states')">
            <div class="grid w-full gap-6 md:grid-cols-2">
                <x-ui.field name="textarea_invalid" :label="$t('textarea.invalid')" :error="$t('textarea.invalid_error')">
                    <x-ui.textarea rows="3" />
                </x-ui.field>
                <x-ui.field name="textarea_disabled" :label="$t('textarea.disabled')">
                    <x-ui.textarea rows="3" disabled>{{ $t('textarea.disabled_value') }}</x-ui.textarea>
                </x-ui.field>
            </div>
        </x-styleguide.example>
    </x-styleguide.component>

    {{-- select --}}
    <x-styleguide.component id="select" :title="$t('select.title')" :description="$t('select.description')" :a11y="[$t('select.a11y_native'), $t('select.a11y_placeholder')]">
        <x-styleguide.example :title="$t('select.basic')">
            <x-slot:code>@verbatim<x-ui.select wire:model.live="species" :options="$options" placeholder />@endverbatim</x-slot:code>
            <div class="grid w-full gap-6 md:grid-cols-2">
                <x-ui.field name="select_species" :label="$t('select.species')">
                    <x-ui.select :options="$species" placeholder />
                </x-ui.field>
                <x-ui.field name="select_province" :label="$t('select.province')" :help="$t('select.province_help')">
                    <x-ui.select :options="$provinces" :placeholder="$t('select.province_placeholder')" value="BO" />
                </x-ui.field>
            </div>
        </x-styleguide.example>

        <x-styleguide.example :title="$t('select.slot')">
            <x-slot:code>@verbatim<x-ui.select>
    <option value="small">…</option>
</x-ui.select>@endverbatim</x-slot:code>
            <div class="grid w-full gap-6 md:grid-cols-2">
                <x-ui.field name="select_size" :label="$t('select.size')">
                    <x-ui.select>
                        <option value="small">{{ $t('options.small') }}</option>
                        <option value="medium" selected>{{ $t('options.medium') }}</option>
                        <option value="large">{{ $t('options.large') }}</option>
                    </x-ui.select>
                </x-ui.field>
            </div>
        </x-styleguide.example>

        <x-styleguide.example :title="$t('select.states')">
            <div class="grid w-full gap-6 md:grid-cols-2">
                <x-ui.field name="select_invalid" :label="$t('select.species')" :error="$t('select.invalid_error')" required>
                    <x-ui.select :options="$species" placeholder />
                </x-ui.field>
                <x-ui.field name="select_disabled" :label="$t('select.species')">
                    <x-ui.select :options="$species" value="cats" disabled />
                </x-ui.field>
            </div>
        </x-styleguide.example>
    </x-styleguide.component>

    {{-- checkbox --}}
    <x-styleguide.component id="checkbox" :title="$t('checkbox.title')" :description="$t('checkbox.description')" :a11y="[$t('checkbox.a11y_target'), $t('checkbox.a11y_state'), $t('checkbox.a11y_mixed')]">
        <x-styleguide.example :title="$t('checkbox.basic')">
            <x-slot:code>@verbatim<x-ui.checkbox name="updates" wire:model="updates" :description="__('…')">…</x-ui.checkbox>@endverbatim</x-slot:code>
            <div class="flex w-full flex-col">
                <x-ui.checkbox name="checkbox_updates" :description="$t('checkbox.updates_description')">{{ $t('checkbox.updates') }}</x-ui.checkbox>
                <x-ui.checkbox name="checkbox_reminder" checked>{{ $t('checkbox.reminder') }}</x-ui.checkbox>
            </div>
        </x-styleguide.example>

        <x-styleguide.example :title="$t('checkbox.states')">
            <x-slot:code>@verbatim<x-ui.checkbox name="privacy" required :error="__('…')">…</x-ui.checkbox>
<x-ui.checkbox indeterminate>…</x-ui.checkbox>@endverbatim</x-slot:code>
            <div class="grid w-full gap-x-6 md:grid-cols-2">
                <x-ui.checkbox name="checkbox_terms" required :error="$t('checkbox.terms_error')">{{ $t('checkbox.terms') }}</x-ui.checkbox>
                <x-ui.checkbox name="checkbox_mixed" indeterminate data-demo="indeterminate">{{ $t('checkbox.mixed') }}</x-ui.checkbox>
                <x-ui.checkbox name="checkbox_disabled" disabled>{{ $t('checkbox.disabled') }}</x-ui.checkbox>
                <x-ui.checkbox name="checkbox_disabled_checked" disabled checked>{{ $t('checkbox.disabled_checked') }}</x-ui.checkbox>
            </div>
        </x-styleguide.example>

        <x-styleguide.example :title="$t('checkbox.select_all')">
            <x-slot:code>@verbatim<x-ui.checkbox x-effect="$el.indeterminate = some && ! all" …>…</x-ui.checkbox>@endverbatim</x-slot:code>
            <div
                role="group"
                aria-labelledby="demo-select-all-label"
                class="flex flex-col"
                data-demo="select-all"
                x-data="{ all: @js(array_keys($species)), chosen: ['dogs'] }"
            >
                <p id="demo-select-all-label" class="pb-1 font-semibold">{{ $t('checkbox.select_all_label') }}</p>
                <x-ui.checkbox
                    id="demo-select-all"
                    x-bind:checked="chosen.length === all.length"
                    x-effect="$el.indeterminate = chosen.length > 0 && chosen.length < all.length"
                    x-on:change="chosen = $event.target.checked ? [...all] : []"
                >{{ $t('checkbox.select_all_toggle') }}</x-ui.checkbox>
                <div class="flex flex-col ps-9">
                    @foreach ($species as $value => $speciesLabel)
                        <x-ui.checkbox :id="'demo-select-all-'.$value" :value="$value" x-model="chosen">{{ $speciesLabel }}</x-ui.checkbox>
                    @endforeach
                </div>
            </div>
        </x-styleguide.example>

        <x-styleguide.example :title="$t('checkbox.on_surface')" background="surface">
            <x-ui.checkbox name="checkbox_surface" checked>{{ $t('checkbox.reminder') }}</x-ui.checkbox>
            <x-ui.checkbox name="checkbox_surface_off">{{ $t('checkbox.updates') }}</x-ui.checkbox>
        </x-styleguide.example>
    </x-styleguide.component>

    {{-- checkbox-group --}}
    <x-styleguide.component id="checkbox-group" :title="$t('checkbox_group.title')" :description="$t('checkbox_group.description')" :a11y="[$t('checkbox_group.a11y_fieldset'), $t('checkbox_group.a11y_error')]">
        <x-styleguide.example :title="$t('checkbox_group.vertical')">
            <x-slot:code>@verbatim<x-ui.checkbox-group name="species" :legend="__('…')" :options="$options" wire:model="species" />@endverbatim</x-slot:code>
            <x-ui.checkbox-group name="group_species" :legend="$t('checkbox_group.species')" :help="$t('checkbox_group.species_help')" :options="$species" :value="['cats']" />
        </x-styleguide.example>

        <x-styleguide.example :title="$t('checkbox_group.horizontal')">
            <x-slot:code>@verbatim<x-ui.checkbox-group … orientation="horizontal" :error="__('…')" required />@endverbatim</x-slot:code>
            <x-ui.checkbox-group name="group_days" :legend="$t('checkbox_group.days')" orientation="horizontal" :error="$t('checkbox_group.days_error')" required :options="['sat' => $t('options.saturday'), 'sun' => $t('options.sunday'), 'week' => $t('options.weekdays')]" />
        </x-styleguide.example>
    </x-styleguide.component>

    {{-- radio --}}
    <x-styleguide.component id="radio" :title="$t('radio.title')" :description="$t('radio.description')" :a11y="[$t('radio.a11y_keyboard'), $t('radio.a11y_target')]">
        <x-styleguide.example :title="$t('radio.slot')">
            <x-slot:code>@verbatim<x-ui.radio-group name="size" :legend="__('…')">
    <x-ui.radio value="small" wire:model="size" :description="__('…')">…</x-ui.radio>
</x-ui.radio-group>@endverbatim</x-slot:code>
            <x-ui.radio-group name="radio_size" :legend="$t('radio.size')" data-demo="radio">
                <x-ui.radio value="small" :description="$t('radio.small_description')">{{ $t('options.small') }}</x-ui.radio>
                <x-ui.radio value="medium" checked>{{ $t('options.medium') }}</x-ui.radio>
                <x-ui.radio value="large">{{ $t('options.large') }}</x-ui.radio>
                <x-ui.radio value="giant" disabled>{{ $t('radio.giant') }}</x-ui.radio>
            </x-ui.radio-group>
        </x-styleguide.example>
    </x-styleguide.component>

    {{-- radio-group --}}
    <x-styleguide.component id="radio-group" :title="$t('radio_group.title')" :description="$t('radio_group.description')" :a11y="[$t('radio_group.a11y_role'), $t('radio_group.a11y_error')]">
        <x-styleguide.example :title="$t('radio_group.options')">
            <x-slot:code>@verbatim<x-ui.radio-group name="home" :legend="__('…')" :options="$homes" wire:model="home" required />@endverbatim</x-slot:code>
            <x-ui.radio-group name="group_home" :legend="$t('radio_group.home')" :options="$homes" value="flat" required />
        </x-styleguide.example>

        <x-styleguide.example :title="$t('radio_group.horizontal')">
            <x-slot:code>@verbatim<x-ui.radio-group … orientation="horizontal" :error="__('…')" />@endverbatim</x-slot:code>
            <x-ui.radio-group name="group_contact" :legend="$t('radio_group.contact')" orientation="horizontal" :error="$t('radio_group.contact_error')" :options="['email' => $t('options.email'), 'phone' => $t('options.phone'), 'whatsapp' => 'WhatsApp']" required />
        </x-styleguide.example>

        <x-styleguide.example :title="$t('radio_group.on_surface')" background="surface">
            <x-ui.radio-group name="group_surface" :legend="$t('radio_group.contact')" orientation="horizontal" :options="['email' => $t('options.email'), 'phone' => $t('options.phone')]" value="email" />
        </x-styleguide.example>
    </x-styleguide.component>

    {{-- switch --}}
    <x-styleguide.component id="switch" :title="$t('switch.title')" :description="$t('switch.description')" :a11y="[$t('switch.a11y_role'), $t('switch.a11y_state')]">
        <x-styleguide.example :title="$t('switch.basic')">
            <x-slot:code>@verbatim<x-ui.switch name="alerts" wire:model.live="alerts" :description="__('…')">…</x-ui.switch>@endverbatim</x-slot:code>
            <div class="flex w-full flex-col">
                <x-ui.switch name="switch_alerts" id="demo-switch" :description="$t('switch.alerts_description')">{{ $t('switch.alerts') }}</x-ui.switch>
                <x-ui.switch name="switch_digest" checked>{{ $t('switch.digest') }}</x-ui.switch>
            </div>
        </x-styleguide.example>

        <x-styleguide.example :title="$t('switch.states')">
            <div class="flex w-full flex-col">
                <x-ui.switch name="switch_disabled" disabled>{{ $t('switch.disabled') }}</x-ui.switch>
                <x-ui.switch name="switch_disabled_on" disabled checked>{{ $t('switch.disabled_on') }}</x-ui.switch>
                <x-ui.switch name="switch_invalid" :error="$t('switch.invalid_error')">{{ $t('switch.invalid') }}</x-ui.switch>
            </div>
        </x-styleguide.example>

        <x-styleguide.example :title="$t('switch.on_surface')" background="surface">
            <x-ui.switch name="switch_surface" checked>{{ $t('switch.digest') }}</x-ui.switch>
            <x-ui.switch name="switch_surface_off">{{ $t('switch.alerts') }}</x-ui.switch>
        </x-styleguide.example>
    </x-styleguide.component>

    {{-- modulo d'esempio --}}
    <section id="example-form" aria-labelledby="example-form-title" data-styleguide-component="example-form" class="flex scroll-mt-6 flex-col gap-6">
        <div class="flex flex-col gap-3">
            <h2 id="example-form-title" class="text-h2">{{ $t('example.title') }}</h2>
            <p class="max-w-3xl text-fg-muted">{{ $t('example.description') }}</p>
        </div>

        <div class="rounded-panel bg-surface p-panel">
            <form method="get" action="{{ route('styleguide.group', 'forms') }}#example-form-errors" novalidate data-demo="form" class="flex max-w-2xl flex-col gap-6">
                <input type="hidden" name="demo" value="errors">

                {{-- Destinazione del frammento dell'action: essendo focalizzabile (tabindex -1),
                     il browser gli dà il focus al caricamento, anche senza JavaScript. --}}
                <x-ui.error-summary
                    id="example-form-errors"
                    data-demo="error-summary"
                    :heading-level="3"
                    :title="$t('example.summary', ['count' => count($demoErrors)])"
                    :messages="$demoErrors"
                />

                <div class="grid gap-6 md:grid-cols-2">
                    <x-ui.field name="full_name" for="apply-name" :label="$t('example.name')" :error="$demoError('apply-name')" required class="md:col-span-2">
                        <x-ui.input autocomplete="name" />
                    </x-ui.field>
                    <x-ui.field name="email" for="apply-email" :label="$t('example.email')" :help="$t('example.email_help')" :error="$demoError('apply-email')" required>
                        <x-ui.input type="email" icon="mail" autocomplete="email" />
                    </x-ui.field>
                    <x-ui.field name="phone" for="apply-phone" :label="$t('example.phone')" :help="$t('example.phone_help')" optional>
                        <x-ui.input type="tel" icon="phone" autocomplete="tel" />
                    </x-ui.field>
                </div>

                <x-ui.field name="message" for="apply-message" :label="$t('example.message')" :help="$t('example.message_help')" optional>
                    <x-ui.textarea maxlength="500" counter autoresize rows="3" />
                </x-ui.field>

                <x-ui.radio-group name="home" :legend="$t('example.home')" :options="$homes" :error="$demoError('field-home-house')" required />

                <div class="flex flex-col">
                    <x-ui.checkbox name="privacy" id="apply-privacy" value="1" required :error="$demoError('apply-privacy')">
                        {{ $t('example.privacy') }} <x-ui.link href="#example-form">{{ $t('example.privacy_link') }}</x-ui.link>
                    </x-ui.checkbox>
                    <x-ui.switch name="newsletter" value="1" :description="$t('example.newsletter_description')">{{ $t('example.newsletter') }}</x-ui.switch>
                </div>

                <div class="flex flex-wrap items-center gap-4">
                    <x-ui.button type="submit" icon-trailing="send">{{ $t('example.submit') }}</x-ui.button>
                    @if ($demoErrors !== [])
                        <x-ui.button variant="ghost" :href="route('styleguide.group', 'forms').'#example-form'" icon="rotate-ccw">{{ $t('example.reset') }}</x-ui.button>
                    @endif
                </div>
            </form>
        </div>
    </section>
</x-styleguide.layout>
