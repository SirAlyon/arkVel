{{--
 | Calendario Cally (<calendar-date>, web component MIT) con i nostri bottoni "mese
 | precedente / successivo" (icone Lucide + nome tradotto), il titolo "mese anno" nella
 | lingua (slot heading, aria-hidden: Cally lo annuncia già in una sua live region) e lo stile di
 | resources/css/ui/pickers.css (::part). Uso interno di x-ui.date-picker: lo stato
 | (`value`, `select()`) è quello di Alpine.data('uiDatePicker').
 |
 | Cally gestisce la tastiera della griglia: frecce (giorno / settimana), Home / End
 | (inizio / fine settimana), PagSu / PagGiù (mese), Maiusc+PagSu / PagGiù (anno),
 | Invio / Spazio per scegliere. I giorni hanno aria-label con la data per esteso,
 | aria-pressed (scelto), aria-current="date" (oggi) e aria-disabled (non disponibili).
 |
 | <x-ui.date-picker.calendar value="2026-11-12" min="2026-11-01" locale="it" :first-day-of-week="1" />
--}}
@props([
    'value' => '',
    'min' => null,
    'max' => null,
    'locale' => 'it',
    'firstDayOfWeek' => 1,
])

<calendar-date
    x-ref="calendar"
    value="{{ $value }}"
    x-bind:value="value"
    x-on:change="select($event)"
    x-on:focusday="focused($event)"
    @if (filled($min)) min="{{ $min }}" @endif
    @if (filled($max)) max="{{ $max }}" @endif
    locale="{{ $locale }}"
    first-day-of-week="{{ $firstDayOfWeek }}"
    data-date-picker-calendar
    {{ $attributes->class('ui-calendar') }}
>
    <span slot="previous" class="inline-flex">
        <x-ui.icon name="chevron-left" class="rtl:rotate-180" />
        <span class="sr-only">{{ __('ui_pickers.date_picker.previous') }}</span>
    </span>
    <span slot="heading" aria-hidden="true" x-text="heading"></span>
    <span slot="next" class="inline-flex">
        <x-ui.icon name="chevron-right" class="rtl:rotate-180" />
        <span class="sr-only">{{ __('ui_pickers.date_picker.next') }}</span>
    </span>
    <calendar-month></calendar-month>
</calendar-date>
