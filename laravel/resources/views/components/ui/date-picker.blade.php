{{--
 | Selettore di date: wrapper di Cally (web component MIT, < 9 KB). Cally si carica con
 | import dinamico solo quando un date-picker si inizializza (resources/js/ui/pickers.js).
 | Pattern ispirato a WAI-ARIA APG "Date Picker Dialog" (non modale).
 |
 | Due varianti:
 | - popover (default): campo a pill che mostra la data per esteso nella lingua
 |   (Intl.DateTimeFormat) + bottone "Apri il calendario" (aria-haspopup="dialog",
 |   aria-expanded). Il calendario si apre in un pannello (x-anchor, top layer) e riceve il
 |   focus sul giorno scelto (o su oggi). Esc, clic fuori o Tab fuori dal pannello lo
 |   chiudono; dopo Esc, il clic fuori e la scelta il focus torna al bottone e una live
 |   region annuncia "Data scelta: …". Anche clic, Invio, Spazio o ↓ sul campo lo aprono;
 | - `inline`: calendario sempre visibile (es. prenotazione di una visita), con la data
 |   scelta scritta sotto (aria-live polite).
 |
 | Props:
 | - `name`: nome dell'<input type="hidden"> con il valore ISO Y-m-d (form classici);
 | - `value`, `min`, `max`: date ISO Y-m-d (o DateTimeInterface);
 | - `disallowed`: date non disponibili, lista di date ISO e/o giorni della settimana
 |   (0 = domenica … 6 = sabato, come Carbon::dayOfWeek). Restano raggiungibili da
 |   tastiera, barrate e con aria-disabled, ma non si possono scegliere;
 | - `locale`: lingua del calendario e della data scritta (default app()->getLocale());
 | - `first-day-of-week`: 0 = domenica … 6 = sabato (default 1, lunedì: lo standard
 |   europeo e italiano);
 | - `placeholder`: testo del campo vuoto (default tradotto "Scegli una data");
 | - `clearable`: bottone "Cancella la data" quando c'è un valore;
 | - `invalid`: forza lo stato di errore (di norma lo decide x-ui.field).
 |
 | Valore: x-modelable sul contenitore, quindi wire:model / x-model (con i modificatori)
 | vanno sul componente e ricevono la data ISO ('' se vuota). `class` va sul contenitore;
 | gli altri attributi (disabled, aria-*) sul campo. Disabled ha il bordo `line` (sotto il
 | 3:1): voluto, WCAG 1.4.11 esclude i componenti inattivi.
 | Dentro x-ui.field eredita id, name, aria-describedby, aria-invalid e l'obbligo
 | (aria-required: il campo è di sola lettura, quindi la validazione è sul server), con le
 | regole di App\Support\Ui\ResolvedControl: id, name e required propri non sono ammessi.
 | Nella variante inline il controllo è un div role="group", che non è etichettabile con
 | `for`: il gruppo punta alla label del field con aria-labelledby e la label non ha `for`
 | (vedi FieldControl::labelFor()).
 | Da solo serve un aria-label; l'errore si legge da $errors con il name o con la chiave
 | di wire:model. L'id da solo: `id`, altrimenti `date-picker-<name>`, altrimenti un hash
 | stabile di wire:model / aria-label (mai del valore): due date-picker senza id né name
 | nella stessa pagina vogliono un aria-label diverso.
 |
 | <x-ui.field name="visit_date" :label="__('…')" :help="__('…')" required>
 |     <x-ui.date-picker wire:model="visitDate" :min="today()" :disallowed="[0, '2026-12-25']" />
 | </x-ui.field>
 | <x-ui.date-picker inline name="slot_date" :aria-label="__('…')" :min="today()" :max="today()->addMonths(2)" />
--}}
@use('App\Support\Ui\ResolvedControl')

@aware(['uiField' => null])

@props([
    'value' => null,
    'min' => null,
    'max' => null,
    'disallowed' => [],
    'locale' => null,
    'firstDayOfWeek' => 1,
    'placeholder' => null,
    'inline' => false,
    'clearable' => false,
    'invalid' => null,
])

@php
    // Id, name, obbligo ed errore: dal field se c'è, altrimenti propri (ResolvedControl).
    // Seme dell'id stabile (wire:model, aria-label): mai il valore, che cambia con lo stato.
    $control = ResolvedControl::resolve($uiField, $attributes, $errors ?? null, $invalid, prefix: 'date-picker');
    $id = $control->id;
    $name = $control->name;
    $invalid = $control->invalid;
    $describedBy = $control->describedBy();

    // Date ISO Y-m-d: accetta stringhe e DateTimeInterface, scarta i valori non validi.
    $iso = static function (mixed $date): string {
        if ($date instanceof DateTimeInterface) {
            return $date->format('Y-m-d');
        }

        return is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 && checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4)) ? $date : '';
    };

    $locale ??= app()->getLocale();
    $value = $iso($value);
    $min = $iso($min);
    $max = $iso($max);
    $display = $value !== '' ? \Illuminate\Support\Carbon::createFromFormat('!Y-m-d', $value)->locale($locale)->isoFormat('LL') : '';

    $disallowedDates = [];
    $disallowedWeekdays = [];
    foreach ($disallowed as $day) {
        if (is_int($day) && $day >= 0 && $day <= 6) {
            $disallowedWeekdays[] = $day;
        } elseif ($iso($day) !== '') {
            $disallowedDates[] = $iso($day);
        }
    }

    $placeholder ??= __('ui_pickers.date_picker.placeholder');
    $panelId = $id.'-calendar';
    $disabled = (bool) $attributes->get('disabled', false);

    $config = [
        'value' => $value,
        'min' => $min,
        'max' => $max,
        'locale' => $locale,
        'inline' => (bool) $inline,
        'disallowed' => ['dates' => $disallowedDates, 'weekdays' => $disallowedWeekdays],
        'messages' => [
            'selected' => __('ui_pickers.date_picker.selected'),
            'none' => __('ui_pickers.date_picker.none'),
        ],
    ];

    // wire:model* e x-model* vanno sul contenitore (x-modelable), insieme a class.
    $modelKeys = array_values(array_filter(array_keys($attributes->getAttributes()), static fn (string $key): bool => str_starts_with($key, 'wire:model') || str_starts_with($key, 'x-model')));

    $boxClasses = [
        'flex min-h-12 w-full min-w-0 items-center rounded-full border-2 bg-canvas text-fg',
        'transition-colors duration-(--duration-fast) ease-out',
        'has-[input:focus-visible]:outline-3 has-[input:focus-visible]:outline-offset-2',
        'has-[input:disabled]:cursor-not-allowed has-[input:disabled]:border-line has-[input:disabled]:bg-surface',
        $invalid ? 'border-danger' : 'border-line-strong',
        'hover:border-fg' => ! $invalid && ! $disabled,
    ];

    $buttonClasses = 'inline-flex size-11 shrink-0 items-center justify-center rounded-full text-fg transition-colors duration-(--duration-fast) ease-out hover:bg-surface disabled:cursor-not-allowed disabled:text-fg-muted disabled:hover:bg-transparent';
@endphp

@if ($inline)
    <div
        x-data="uiDatePicker(@js($config))"
        x-modelable="value"
        id="{{ $id }}"
        role="group"
        @if ($control->labelId !== null) aria-labelledby="{{ $control->labelId }}" @endif
        @if ($describedBy !== null) aria-describedby="{{ $describedBy }}" @endif
        data-ui-date-picker="inline"
        {{ $attributes->except(['id', 'name', 'required', 'aria-describedby', ...($control->labelId !== null ? ['aria-labelledby'] : [])])->class([
            'flex w-full max-w-sm min-w-0 flex-col gap-3 rounded-card border-2 bg-canvas p-3 text-fg',
            $invalid ? 'border-danger' : 'border-line',
        ]) }}
    >
        @if ($name !== null)
            <input type="hidden" name="{{ $name }}" value="{{ $value }}" x-bind:value="value" data-date-picker-value />
        @endif

        <x-ui.date-picker.calendar :value="$value" :min="$min" :max="$max" :locale="$locale" :first-day-of-week="$firstDayOfWeek" />

        <p aria-live="polite" class="flex min-h-11 items-center gap-2 px-2 text-small text-fg-muted" data-date-picker-status>
            <x-ui.icon name="calendar-check" size="sm" />
            <span x-text="status">{{ $display !== '' ? __('ui_pickers.date_picker.selected', ['date' => $display]) : __('ui_pickers.date_picker.none') }}</span>
        </p>
    </div>
@else
    <div
        x-data="uiDatePicker(@js($config))"
        x-modelable="value"
        x-on:keydown.escape="escape($event)"
        x-on:focusout="focusLeft($event)"
        x-on:click.outside="clickedOutside()"
        data-ui-date-picker="popover"
        {{ $attributes->only(['class', ...$modelKeys])->class('relative w-full min-w-0') }}
    >
        @if ($name !== null)
            <input type="hidden" name="{{ $name }}" value="{{ $value }}" x-bind:value="value" data-date-picker-value />
        @endif

        <div x-ref="box" @class($boxClasses)>
            <input
                x-ref="input"
                type="text"
                readonly
                id="{{ $id }}"
                value="{{ $display }}"
                x-bind:value="display"
                placeholder="{{ $placeholder }}"
                x-on:click="toggle()"
                x-on:keydown.enter.prevent="show()"
                x-on:keydown.space.prevent="show()"
                x-on:keydown.down.prevent="show()"
                @if ($describedBy !== null) aria-describedby="{{ $describedBy }}" @endif
                @if ($invalid) aria-invalid="true" @endif
                @if ($control->required) aria-required="true" @endif
                data-date-picker-input
                {{ $attributes->except(['class', 'id', 'name', 'aria-describedby', 'required', ...$modelKeys])->class([
                    'h-11 w-full min-w-0 flex-1 cursor-pointer rounded-full bg-transparent ps-5 pe-2 text-body text-fg placeholder:text-fg-muted',
                    'focus-visible:outline-hidden disabled:cursor-not-allowed disabled:text-fg-muted',
                ]) }}
            />

            @if ($clearable)
                <button
                    type="button"
                    x-show="value !== ''"
                    x-on:click="clear()"
                    aria-label="{{ __('ui_pickers.date_picker.clear') }}"
                    @if ($value === '') style="display: none" @endif
                    @disabled($disabled)
                    data-date-picker-clear
                    class="{{ $buttonClasses }}"
                >
                    <x-ui.icon name="x" />
                </button>
            @endif
            <button
                x-ref="trigger"
                type="button"
                x-on:click="toggle()"
                x-bind:aria-expanded="open.toString()"
                aria-expanded="false"
                aria-haspopup="dialog"
                aria-controls="{{ $panelId }}"
                aria-label="{{ __('ui_pickers.date_picker.open') }}"
                @disabled($disabled)
                data-date-picker-trigger
                class="{{ $buttonClasses }} me-0.5"
            >
                <x-ui.icon name="calendar" />
            </button>
        </div>

        <div
            x-ref="panel"
            x-show="open"
            x-anchor.bottom-start.offset.8.fixed="$refs.box"
            id="{{ $panelId }}"
            role="dialog"
            aria-label="{{ __('ui_pickers.date_picker.dialog') }}"
            popover="manual"
            tabindex="-1"
            style="display: none"
            data-date-picker-panel
            class="ui-picker-layer ui-date-picker-panel inset-auto z-50 m-0 w-max rounded-card bg-surface-raised p-3 text-fg shadow-overlay transition duration-(--duration-fast) ease-out starting:open:scale-95 starting:open:opacity-0"
        >
            <x-ui.date-picker.calendar :value="$value" :min="$min" :max="$max" :locale="$locale" :first-day-of-week="$firstDayOfWeek" />
        </div>

        <div role="status" class="sr-only" x-text="announcement" data-date-picker-status></div>
    </div>
@endif
