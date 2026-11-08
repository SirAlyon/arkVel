<div class="mt-8 rounded-2xl border border-brand-100 bg-white p-6 shadow-sm">
    <h2 class="text-lg font-semibold text-stone-900">{{ __('messaging.contact.cta_title') }}</h2>
    <p class="mt-1 text-sm text-stone-500">{{ __('messaging.contact.cta_body', ['name' => $animal->name]) }}</p>

    {{-- Feedback in-place (successo / errore di dominio o validazione). --}}
    @if ($contactMessage)
        <p class="mt-4 rounded-xl px-4 py-2.5 text-sm font-medium {{ $contactMessageIsError ? 'bg-red-50 text-red-700' : 'bg-brand-50 text-brand-800' }}"
           role="status" aria-live="polite" wire:key="contact-message">
            {{ $contactMessage }}
        </p>
    @endif

    <form wire:submit="contact" class="mt-4 space-y-4">
        <div>
            <label for="contact-body" class="block text-sm font-medium text-stone-700">
                {{ __('messaging.reply.label') }}
            </label>
            <textarea
                id="contact-body"
                wire:model="contactBody"
                rows="3"
                placeholder="{{ __('messaging.contact.placeholder') }}"
                class="mt-1 block w-full rounded-xl border-brand-200 text-sm shadow-sm focus:border-brand-400 focus:ring-brand-400"
            ></textarea>
            @error('contactBody')
                <p class="mt-1 text-sm text-red-600" wire:key="err-contact-body">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <x-button
                type="submit"
                variant="primary"
                size="lg"
                wire:loading.attr="disabled"
                wire:target="contact"
            >
                <span wire:loading.remove wire:target="contact">{{ __('messaging.contact.submit') }}</span>
                <span wire:loading wire:target="contact">{{ __('messaging.contact.submitting') }}</span>
            </x-button>
            @auth
                <x-button :href="route('filament.adopter.pages.my-messages')" variant="outline" size="lg">
                    {{ __('messaging.contact.my_messages') }}
                </x-button>
            @endauth
        </div>
    </form>
</div>
