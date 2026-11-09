{{-- CTA "richiedi affido" (App\Livewire\AnimalDetail\FosterRequestForm), sotto la candidatura
     nel pannello azioni: specchio della candidatura, con il bottone secondario. Il ramo lo
     decide il server in requestFoster(); già richiesto: stato "inviata" + rimando agli affidi.
     Durante la richiesta il bottone è disattivato (niente doppio invio) e mostra lo spinner
     (data-loading di Livewire). --}}
<div data-animal-cta="foster">
    <div class="flex flex-col gap-3">
        <p class="text-small text-fg-muted">{{ __('fostering.request.cta_body') }}</p>

        @if ($hasRequestedFoster)
            <p class="flex items-center gap-2 font-bold text-fg">
                <x-ui.icon name="circle-check" class="shrink-0" />
                <span>{{ __('fostering.request.requested') }}</span>
            </p>
            <x-ui.button :href="route('filament.adopter.pages.my-foster-applications')" variant="secondary" icon-trailing="arrow-right" class="w-full">
                {{ __('fostering.request.my_foster_applications') }}
            </x-ui.button>
        @else
            <x-ui.button
                type="button"
                variant="secondary"
                class="w-full"
                wire:click="requestFoster"
                wire:loading.attr="disabled"
                wire:target="requestFoster"
                data-animal-foster
            >{{ __('fostering.request.cta_button') }}</x-ui.button>
        @endif
    </div>

    <x-site.animal.feedback :message="$fosterMessage" :error="$fosterMessageIsError" focus wire:key="foster-message" />
</div>
