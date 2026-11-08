<div class="mt-8 rounded-2xl border border-brand-100 bg-white p-6 shadow-sm">
    <h2 class="text-lg font-semibold text-stone-900">{{ __('appointments.booking.cta_title') }}</h2>
    <p class="mt-1 text-sm text-stone-500">{{ __('appointments.booking.cta_body', ['name' => $animal->name]) }}</p>

    {{-- Feedback in-place (successo / errore di dominio o validazione). --}}
    @if ($bookingMessage)
        <p class="mt-4 rounded-xl px-4 py-2.5 text-sm font-medium {{ $bookingMessageIsError ? 'bg-red-50 text-red-700' : 'bg-brand-50 text-brand-800' }}"
           role="status" aria-live="polite" wire:key="booking-message">
            {{ $bookingMessage }}
        </p>
    @endif

    <form wire:submit="book" class="mt-4 space-y-4">
        <div>
            <label for="appointment-type" class="block text-sm font-medium text-stone-700">
                {{ __('appointments.booking.type') }}
            </label>
            <select
                id="appointment-type"
                wire:model="appointmentType"
                class="mt-1 block w-full rounded-xl border-brand-200 text-sm shadow-sm focus:border-brand-400 focus:ring-brand-400"
            >
                <option value="">—</option>
                @foreach ($appointmentTypes as $type)
                    <option value="{{ $type->value }}">{{ $type->getLabel() }}</option>
                @endforeach
            </select>
            @error('appointmentType')
                <p class="mt-1 text-sm text-red-600" wire:key="err-type">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="appointment-scheduled-at" class="block text-sm font-medium text-stone-700">
                {{ __('appointments.booking.scheduled_at') }}
            </label>
            <input
                id="appointment-scheduled-at"
                type="datetime-local"
                wire:model="appointmentScheduledAt"
                class="mt-1 block w-full rounded-xl border-brand-200 text-sm shadow-sm focus:border-brand-400 focus:ring-brand-400"
            >
            @error('appointmentScheduledAt')
                <p class="mt-1 text-sm text-red-600" wire:key="err-scheduled-at">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="appointment-notes" class="block text-sm font-medium text-stone-700">
                {{ __('appointments.booking.notes') }}
            </label>
            <textarea
                id="appointment-notes"
                wire:model="appointmentNotes"
                rows="2"
                placeholder="{{ __('appointments.booking.notes_placeholder') }}"
                class="mt-1 block w-full rounded-xl border-brand-200 text-sm shadow-sm focus:border-brand-400 focus:ring-brand-400"
            ></textarea>
            @error('appointmentNotes')
                <p class="mt-1 text-sm text-red-600" wire:key="err-notes">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <x-button
                type="submit"
                variant="primary"
                size="lg"
                wire:loading.attr="disabled"
                wire:target="book"
            >
                <span wire:loading.remove wire:target="book">{{ __('appointments.booking.submit') }}</span>
                <span wire:loading wire:target="book">{{ __('appointments.booking.submitting') }}</span>
            </x-button>
            @auth
                <x-button :href="route('filament.adopter.pages.my-appointments')" variant="outline" size="lg">
                    {{ __('appointments.booking.my_appointments') }}
                </x-button>
            @endauth
        </div>
    </form>
</div>
