{{-- Primo messaggio all'ente (App\Livewire\AnimalDetail\ContactOrganizationForm), nel drawer
     "Scrivi all'ente" della scheda: titolo e presentazione sono quelli del drawer.

     Focus dopo l'invio (WCAG 2.4.3 / 3.3.1): con errori il riepilogo (ricreato a ogni
     tentativo grazie al contatore nel wire:key) prende il focus; con l'esito lo prende il
     messaggio, che scorre in vista. --}}
<div data-animal-cta="contact">
    @guest
        <x-site.animal.guest-note :message="__('page_animal.contact.guest_note')" class="mb-6" />
    @endguest

    <form wire:submit="contact" novalidate class="flex flex-col gap-6">
        <x-ui.error-summary
            id="contact-error-summary"
            :messages="$errors->has('contactBody') ? ['contact-body' => $errors->first('contactBody')] : []"
            heading-level="3"
            wire:key="contact-errors-{{ $contactAttempts }}"
        />

        <x-ui.field name="contactBody" for="contact-body" :label="__('messaging.reply.label')" required>
            <x-ui.textarea wire:model="contactBody" rows="5" autoresize :placeholder="__('messaging.contact.placeholder')" />
        </x-ui.field>

        <div class="flex flex-wrap gap-3">
            <x-ui.button type="submit" icon="send" wire:loading.attr="data-loading" wire:target="contact" data-animal-contact-submit>
                {{ __('messaging.contact.submit') }}
            </x-ui.button>
            @auth
                <x-ui.button :href="route('filament.adopter.pages.my-messages')" variant="secondary">
                    {{ __('messaging.contact.my_messages') }}
                </x-ui.button>
            @endauth
        </div>
    </form>

    {{-- Con errori di validazione il focus va al riepilogo: un esito precedente non si mostra. --}}
    <x-site.animal.feedback
        :message="$errors->any() ? null : $contactMessage"
        :error="$contactMessageIsError"
        focus
        wire:key="contact-message-{{ $contactAttempts }}"
    />
</div>
