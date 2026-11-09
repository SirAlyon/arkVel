{{-- CTA "candidati" (App\Livewire\AnimalDetail\AdoptionApplicationForm), nel pannello azioni
     della scheda. Un solo bottone: il ramo (ospite / profilo incompleto / candidabile) lo
     decide il server in apply(). Già candidato: stato "inviata" + rimando alle candidature.
     L'esito in-place prende il focus, perché il bottone sparisce dopo l'invio. Durante la
     richiesta il bottone è disattivato (niente doppio invio) e mostra lo spinner: Livewire
     gli mette data-loading, che x-ui.button usa per lo stato di attesa. --}}
<div data-animal-cta="apply">
    @if ($hasApplied)
        <div class="flex flex-col gap-3">
            <p class="flex items-center gap-2 font-bold text-fg">
                <x-ui.icon name="circle-check" class="shrink-0" />
                <span>{{ __('applications.apply.applied') }}</span>
            </p>
            <x-ui.button :href="route('filament.adopter.pages.my-applications')" variant="secondary" icon-trailing="arrow-right" class="w-full">
                {{ __('applications.apply.my_applications') }}
            </x-ui.button>
        </div>
    @else
        <x-ui.button
            type="button"
            class="w-full"
            wire:click="apply"
            wire:loading.attr="disabled"
            wire:target="apply"
            data-animal-apply
        >{{ __('public.detail.cta_button') }}</x-ui.button>
    @endif

    <x-site.animal.feedback :message="$applyMessage" :error="$applyMessageIsError" focus wire:key="apply-message" />
</div>
