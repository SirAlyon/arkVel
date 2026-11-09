<?php

declare(strict_types=1);

namespace App\Livewire\AnimalDetail;

use App\Actions\Messaging\ContactOrganizationAboutAnimal;
use App\Livewire\AnimalDetail\Concerns\ActsOnAnimal;
use App\Models\Animal;
use App\Models\MessageThread;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View as ViewFacade;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * CTA "Contatta l'ente" della scheda animale: un form con un solo campo (il messaggio)
 * che delega a {@see ContactOrganizationAboutAnimal} (avvia la conversazione o, al
 * ricontatto, la riusa). Non c'è guardia di profilo: basta l'autenticazione, l'ospite
 * va alla login adottante. Componente sottile: autorizza, valida e delega.
 */
class ContactOrganizationForm extends Component
{
    use ActsOnAnimal;

    /**
     * Corpo del messaggio, validato (presenza + lunghezza) prima di delegare; la
     * guardia "corpo non vuoto" vive comunque anche nelle Action.
     */
    public string $contactBody = '';

    /**
     * Feedback in-place (successo o errore amichevole), mostrato accanto al form.
     * Le redirezioni (ospite) usano il flash di sessione.
     */
    #[Locked]
    public ?string $contactMessage = null;

    #[Locked]
    public bool $contactMessageIsError = false;

    /**
     * Tentativi di invio: entra nel wire:key del riepilogo errori e del messaggio, così a
     * ogni invio vengono ricreati e prendono il focus (WCAG 2.4.3 / 3.3.1). Solo stato di
     * presentazione, come il contatore del widget donazione.
     */
    #[Locked]
    public int $contactAttempts = 0;

    public function mount(Animal $animal): void
    {
        $this->animalId = $animal->getKey();
    }

    public function contact(ContactOrganizationAboutAnimal $contactOrganization): mixed
    {
        $user = Auth::user();
        $animal = $this->animal();

        if (! $user instanceof User) {
            return $this->redirectGuestToLogin($animal);
        }

        // Chiunque autenticato può avviare una conversazione (le guardie di dominio
        // vivono nelle Action): manteniamo il gancio alla policy.
        $this->authorize('create', MessageThread::class);

        $this->contactAttempts++;

        $validated = $this->validate();

        $result = $contactOrganization->handle($user, $animal, (string) $validated['contactBody']);

        $this->contactMessageIsError = false;
        $this->contactMessage = $result->reusedExistingThread
            ? __('messaging.contact.flash_appended')
            : __('messaging.contact.flash_success');

        $this->reset('contactBody');

        return null;
    }

    public function render(): View
    {
        return ViewFacade::make('livewire.animal-detail.contact-organization-form', [
            'animal' => $this->animal(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'contactBody' => ['required', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'contactBody.required' => __('messaging.contact.empty'),
        ];
    }
}
