<div>
    {{-- Feedback in-place (successo / messaggio amichevole). Le redirezioni
         (ospite / profilo incompleto) usano invece il flash di sessione. --}}
    @if ($applyMessage)
        <p class="mt-4 rounded-xl px-4 py-2.5 text-sm font-medium {{ $applyMessageIsError ? 'bg-white/20 text-white' : 'bg-white/90 text-brand-800' }}"
           role="status" aria-live="polite" wire:key="apply-message">
            {{ $applyMessage }}
        </p>
    @endif

    @if ($hasApplied)
        {{-- GIÀ CANDIDATO: stato "inviata" + rimando alle candidature. --}}
        <div class="mt-4 flex flex-wrap items-center gap-3">
            <span class="inline-flex items-center gap-2 rounded-xl bg-white/90 px-4 py-2.5 text-sm font-semibold text-brand-800">
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0L3.3 9.7a1 1 0 0 1 1.4-1.4l3.1 3.1 6.8-6.8a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/>
                </svg>
                {{ __('applications.apply.applied') }}
            </span>
            <x-button :href="route('filament.adopter.pages.my-applications')" variant="outline" size="lg">
                {{ __('applications.apply.my_applications') }}
            </x-button>
        </div>
    @else
        {{-- OSPITE / PROFILO INCOMPLETO / CANDIDABILE: un solo pulsante che
             delega all'Action; il ramo è deciso lato server in apply(). --}}
        <x-button
            type="button"
            variant="accent"
            size="lg"
            class="mt-4"
            wire:click="apply"
            wire:loading.attr="disabled"
            wire:target="apply"
        >
            <span wire:loading.remove wire:target="apply">{{ __('public.detail.cta_button') }}</span>
            <span wire:loading wire:target="apply">{{ __('applications.apply.submitting') }}</span>
        </x-button>
    @endif
</div>
