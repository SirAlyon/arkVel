{{-- Prenotazione di una visita (App\Livewire\AnimalDetail\AppointmentBooking), nel drawer
     "Prenota una visita" della scheda: titolo e presentazione sono quelli del drawer.

     Data e ora: il componente riceve sempre una sola stringa `appointmentScheduledAt`
     (Y-m-d\TH:i), validata come prima (required + date). Qui la si compone da due controlli:
     il giorno con x-ui.date-picker (Cally) e l'orario LIBERO con un input type=time nativo,
     come il datetime-local di prima: nessuna fascia oraria imposta dalla vista. Alpine tiene
     giorno e orario e scrive la stringa su $wire senza richieste (solo quando ci sono
     entrambi, altrimenti stringa vuota: la validazione lo segnala); quando il server azzera il
     valore dopo un invio riuscito, azzera anche i due controlli. Le guardie "slot nel futuro"
     e overlap restano solo nell'Action (il min del calendario aiuta a non scegliere giorni
     passati, ma il controllo vero e il messaggio sono del server).

     Focus dopo l'invio (WCAG 2.4.3 / 3.3.1): con errori di validazione il riepilogo
     (x-ui.error-summary, ricreato a ogni tentativo grazie al contatore nel wire:key) prende il
     focus; con un esito (successo o slot non valido) lo prende il messaggio. --}}
@use('App\Support\Ui\ControlAttributes')

@php
    $typeOptions = collect($appointmentTypes)->mapWithKeys(fn (\App\Enums\AppointmentType $type): array => [$type->value => $type->getLabel()])->all();

    $whenInvalid = $errors->has('appointmentScheduledAt');

    // Riepilogo degli errori: ogni messaggio porta al controllo da correggere (la prima
    // opzione del tipo, il campo del giorno, le note), nell'ordine del modulo.
    $firstType = array_key_first($typeOptions);
    $errorTargets = [
        'appointmentType' => 'field-'.ControlAttributes::slug('appointmentType').'-'.ControlAttributes::slug((string) $firstType),
        'appointmentScheduledAt' => 'appointment-date',
        'appointmentNotes' => 'appointment-notes',
    ];
    $errorSummary = [];
    foreach ($errorTargets as $key => $target) {
        if ($errors->has($key)) {
            $errorSummary[$target] = $errors->first($key);
        }
    }
@endphp
<div
    data-animal-cta="booking"
    x-data="{
        date: '',
        time: '',
        init() {
            [this.date, this.time] = this.split(this.$wire.appointmentScheduledAt);
            this.$watch('date', () => this.sync());
            this.$watch('time', () => this.sync());
            this.$watch('$wire.appointmentScheduledAt', (value) => {
                if ((value ?? '') === '' && this.composed() !== '') {
                    this.date = '';
                    this.time = '';
                }
            });
        },
        split(value) {
            const [date = '', time = ''] = String(value ?? '').split('T');

            return [date, time.slice(0, 5)];
        },
        composed() {
            return this.date !== '' && this.time !== '' ? `${this.date}T${this.time}` : '';
        },
        sync() {
            this.$wire.appointmentScheduledAt = this.composed();
        },
    }"
>
    @guest
        <x-site.animal.guest-note :message="__('page_animal.booking.guest_note')" class="mb-6" />
    @endguest

    <form wire:submit="book" novalidate class="flex flex-col gap-6">
        <x-ui.error-summary
            id="booking-error-summary"
            :messages="$errorSummary"
            heading-level="3"
            wire:key="booking-errors-{{ $bookingAttempts }}"
        />

        <x-ui.radio-group
            name="appointmentType"
            :legend="__('appointments.booking.type')"
            :options="$typeOptions"
            wire:model="appointmentType"
            required
        />

        <x-ui.field group name="appointmentScheduledAt" :label="__('page_animal.booking.when')" :help="__('page_animal.booking.when_help')" required>
            <div class="flex flex-col gap-3 sm:flex-row">
                <x-ui.date-picker
                    id="appointment-date"
                    x-model="date"
                    :min="today()"
                    :aria-label="__('page_animal.booking.date')"
                    :invalid="$whenInvalid"
                    class="sm:flex-1"
                />
                <x-ui.input
                    type="time"
                    id="appointment-time"
                    x-model="time"
                    :aria-label="__('page_animal.booking.time')"
                    :invalid="$whenInvalid"
                    class="sm:w-44 sm:flex-none"
                    data-appointment-time
                />
            </div>
        </x-ui.field>

        <x-ui.field name="appointmentNotes" for="appointment-notes" :label="__('appointments.booking.notes')">
            <x-ui.textarea wire:model="appointmentNotes" rows="3" :placeholder="__('appointments.booking.notes_placeholder')" />
        </x-ui.field>

        <div class="flex flex-wrap gap-3">
            <x-ui.button type="submit" icon="calendar-check" wire:loading.attr="data-loading" wire:target="book" data-animal-book-submit>
                {{ __('appointments.booking.submit') }}
            </x-ui.button>
            @auth
                <x-ui.button :href="route('filament.adopter.pages.my-appointments')" variant="secondary">
                    {{ __('appointments.booking.my_appointments') }}
                </x-ui.button>
            @endauth
        </div>
    </form>

    {{-- Con errori di validazione il focus va al riepilogo: un esito precedente non si mostra. --}}
    <x-site.animal.feedback
        :message="$errors->any() ? null : $bookingMessage"
        :error="$bookingMessageIsError"
        focus
        wire:key="booking-message-{{ $bookingAttempts }}"
    />
</div>
