<?php

declare(strict_types=1);

namespace App\Livewire\AnimalDetail;

use App\Actions\Fostering\SubmitFosterApplication;
use App\Exceptions\DuplicateFosterApplication;
use App\Exceptions\IncompleteAdopterProfile;
use App\Livewire\AnimalDetail\Concerns\ActsOnAnimal;
use App\Livewire\AnimalDetail\Concerns\RedirectsToAdopterProfile;
use App\Models\Animal;
use App\Models\FosterApplication;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View as ViewFacade;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * CTA "richiedi affido" della scheda animale: specchio di
 * {@see AdoptionApplicationForm}, delega all'Action {@see SubmitFosterApplication}.
 * Stessi rami e stessa guardia di screening (profilo/consenso completo, riusata anche
 * per l'affido). Nessuna business logic qui.
 */
class FosterRequestForm extends Component
{
    use ActsOnAnimal;
    use RedirectsToAdopterProfile;

    /**
     * L'utente corrente ha già una richiesta di affido per questo animale: calcolato
     * al mount e aggiornato in-place dopo un invio riuscito (o su
     * DuplicateFosterApplication).
     */
    #[Locked]
    public bool $hasRequestedFoster = false;

    /**
     * Feedback in-place del ramo affido. Le redirezioni (ospite / profilo incompleto)
     * usano il flash di sessione, condiviso col ramo adozione.
     */
    #[Locked]
    public ?string $fosterMessage = null;

    #[Locked]
    public bool $fosterMessageIsError = false;

    public function mount(Animal $animal): void
    {
        $this->animalId = $animal->getKey();
        $this->hasRequestedFoster = $this->currentUserHasRequestedFoster();
    }

    /**
     * Invia la richiesta di affido dell'utente corrente per questo animale: stessa
     * macchina di rami della candidatura (ospite / profilo incompleto / richiedibile /
     * già richiesto).
     */
    public function requestFoster(SubmitFosterApplication $submitFosterApplication): mixed
    {
        $user = Auth::user();
        $animal = $this->animal();

        if (! $user instanceof User) {
            return $this->redirectGuestToLogin($animal);
        }

        // Chiunque può richiedere l'affido (SubmitFosterApplication impone le guardie
        // di dominio): manteniamo il gancio alla policy.
        $this->authorize('create', FosterApplication::class);

        if (! $user->hasCompleteAdopterProfile()) {
            return $this->redirectToProfile();
        }

        try {
            $submitFosterApplication->handle($user, $animal);

            $this->hasRequestedFoster = true;
            $this->fosterMessageIsError = false;
            $this->fosterMessage = __('fostering.request.flash_success');
        } catch (DuplicateFosterApplication) {
            $this->hasRequestedFoster = true;
            $this->fosterMessageIsError = false;
            $this->fosterMessage = __('fostering.request.flash_duplicate');
        } catch (IncompleteAdopterProfile) {
            // Difensivo: il profilo è diventato incompleto tra i due controlli.
            return $this->redirectToProfile();
        }

        return null;
    }

    public function render(): View
    {
        return ViewFacade::make('livewire.animal-detail.foster-request-form');
    }

    /**
     * Predicato di sola lettura sulla relazione self-owned (user_id): nessuna
     * business logic.
     */
    private function currentUserHasRequestedFoster(): bool
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return false;
        }

        return FosterApplication::query()
            ->where('user_id', $user->getKey())
            ->where('animal_id', $this->animalId)
            ->exists();
    }
}
