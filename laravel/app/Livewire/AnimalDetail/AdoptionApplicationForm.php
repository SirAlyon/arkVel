<?php

declare(strict_types=1);

namespace App\Livewire\AnimalDetail;

use App\Actions\Applications\SubmitApplication;
use App\Exceptions\DuplicateApplication;
use App\Exceptions\IncompleteAdopterProfile;
use App\Livewire\AnimalDetail\Concerns\ActsOnAnimal;
use App\Livewire\AnimalDetail\Concerns\RedirectsToAdopterProfile;
use App\Models\AdoptionApplication;
use App\Models\Animal;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View as ViewFacade;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * CTA "candidati" della scheda animale. Il submit è delegato all'Action
 * {@see SubmitApplication}: il componente decide solo il ramo (ospite / profilo
 * incompleto / candidabile / già candidato) e traduce le eccezioni di dominio in
 * feedback UI. Nessuna business logic qui.
 */
class AdoptionApplicationForm extends Component
{
    use ActsOnAnimal;
    use RedirectsToAdopterProfile;

    /**
     * L'utente corrente ha già una candidatura per questo animale. Calcolato al
     * mount e aggiornato in-place dopo un invio riuscito (o su DuplicateApplication),
     * così la CTA riflette lo stato senza ricaricare la pagina.
     */
    #[Locked]
    public bool $hasApplied = false;

    /**
     * Messaggio di feedback in-place (successo o errore "amichevole"), mostrato
     * accanto alla CTA. Le redirezioni (ospite / profilo incompleto) usano invece
     * il flash di sessione, letto dalla pagina di destinazione.
     */
    #[Locked]
    public ?string $applyMessage = null;

    #[Locked]
    public bool $applyMessageIsError = false;

    public function mount(Animal $animal): void
    {
        $this->animalId = $animal->getKey();
        $this->hasApplied = $this->currentUserHasApplied();
    }

    /**
     * Invia la candidatura dell'utente corrente per questo animale.
     *
     * Macchina di stati del ramo:
     *  - OSPITE            → redirect alla login adottante (intended = la scheda).
     *  - PROFILO INCOMPLETO → redirect alla pagina profilo con flash informativo.
     *  - CANDIDABILE       → delega a SubmitApplication; successo riflesso in-place.
     *  - GIÀ CANDIDATO     → DuplicateApplication tradotta in messaggio amichevole.
     */
    public function apply(SubmitApplication $submitApplication): mixed
    {
        $user = Auth::user();
        $animal = $this->animal();

        if (! $user instanceof User) {
            return $this->redirectGuestToLogin($animal);
        }

        // Chiunque può candidarsi (SubmitApplication impone le guardie di dominio):
        // manteniamo comunque il gancio alla policy.
        $this->authorize('create', AdoptionApplication::class);

        if (! $user->hasCompleteAdopterProfile()) {
            return $this->redirectToProfile();
        }

        try {
            $submitApplication->handle($user, $animal);

            $this->hasApplied = true;
            $this->applyMessageIsError = false;
            $this->applyMessage = __('applications.apply.flash_success');
        } catch (DuplicateApplication) {
            $this->hasApplied = true;
            $this->applyMessageIsError = false;
            $this->applyMessage = __('applications.apply.flash_duplicate');
        } catch (IncompleteAdopterProfile) {
            // Difensivo: il profilo è diventato incompleto tra i due controlli.
            return $this->redirectToProfile();
        }

        return null;
    }

    public function render(): View
    {
        return ViewFacade::make('livewire.animal-detail.adoption-application-form');
    }

    /**
     * Predicato di sola lettura sulla relazione self-owned: nessuna business logic.
     */
    private function currentUserHasApplied(): bool
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return false;
        }

        return $user->applications()
            ->where('animal_id', $this->animalId)
            ->exists();
    }
}
