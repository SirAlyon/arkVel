<div class="mt-5 border-t border-white/20 pt-5">
    <p class="text-sm text-brand-100">{{ __('fostering.request.cta_body') }}</p>

    {{-- Feedback in-place del ramo affido (successo / messaggio amichevole). --}}
    @if ($fosterMessage)
        <p class="mt-3 rounded-xl px-4 py-2.5 text-sm font-medium {{ $fosterMessageIsError ? 'bg-white/20 text-white' : 'bg-white/90 text-brand-800' }}"
           role="status" aria-live="polite" wire:key="foster-message">
            {{ $fosterMessage }}
        </p>
    @endif

    @if ($hasRequestedFoster)
        {{-- GIÀ RICHIESTO: stato "inviata" + rimando ai miei affidi. --}}
        <div class="mt-3 flex flex-wrap items-center gap-3">
            <span class="inline-flex items-center gap-2 rounded-xl bg-white/90 px-4 py-2.5 text-sm font-semibold text-brand-800">
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0L3.3 9.7a1 1 0 0 1 1.4-1.4l3.1 3.1 6.8-6.8a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/>
                </svg>
                {{ __('fostering.request.requested') }}
            </span>
            <x-button :href="route('filament.adopter.pages.my-foster-applications')" variant="outline" size="lg">
                {{ __('fostering.request.my_foster_applications') }}
            </x-button>
        </div>
    @else
        {{-- OSPITE / PROFILO INCOMPLETO / RICHIEDIBILE: un solo pulsante che
             delega all'Action; il ramo è deciso lato server in requestFoster(). --}}
        <x-button
            type="button"
            variant="outline"
            size="lg"
            class="mt-3"
            wire:click="requestFoster"
            wire:loading.attr="disabled"
            wire:target="requestFoster"
        >
            <span wire:loading.remove wire:target="requestFoster">{{ __('fostering.request.cta_button') }}</span>
            <span wire:loading wire:target="requestFoster">{{ __('fostering.request.submitting') }}</span>
        </x-button>
    @endif
</div>
