<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Actions\Applications\SubmitApplication;
use App\Actions\Fostering\SubmitFosterApplication;
use App\Exceptions\DuplicateApplication;
use App\Exceptions\DuplicateFosterApplication;
use App\Exceptions\IncompleteAdopterProfile;
use App\Models\AdoptionApplication;
use App\Models\Animal;
use App\Models\FosterApplication;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View as ViewFacade;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Scheda pubblica di un singolo animale.
 *
 * L'animale è legato per id (`/animali/{animal}`): lo slug è univoco solo
 * per-ente, quindi un binding globale su slug sarebbe ambiguo. La visibilità è
 * imposta qui — 404 se l'animale non è pubblicamente visibile
 * ({@see Animal::publiclyVisible()}): mai 200 per bozze o enti non verificati.
 *
 * CTA "candidati" (slice 8): il submit vero e proprio è delegato all'Action
 * {@see SubmitApplication}. Il componente resta sottile — decide solo il ramo
 * (ospite / profilo incompleto / candidabile / già candidato) e traduce le
 * eccezioni di dominio in feedback UI. Nessuna business logic qui.
 *
 * CTA "richiedi affido" (Fase 3, slice 4): specchio della CTA di adozione, delega
 * all'Action {@see SubmitFosterApplication}. Stessi rami e stessa guardia di
 * screening (profilo/consenso completo, riusata anche per l'affido).
 */
class AnimalDetail extends Component
{
    public Animal $animal;

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

    /**
     * L'utente corrente ha già una richiesta di affido per questo animale.
     * Specchio di {@see self::$hasApplied} per il ramo affido: calcolato al mount
     * e aggiornato in-place dopo un invio riuscito (o su DuplicateFosterApplication).
     */
    #[Locked]
    public bool $hasRequestedFoster = false;

    /**
     * Messaggio di feedback in-place per il ramo affido, mostrato accanto alla CTA
     * di affido. Le redirezioni (ospite / profilo incompleto) usano il flash di
     * sessione, condiviso col ramo adozione.
     */
    #[Locked]
    public ?string $fosterMessage = null;

    #[Locked]
    public bool $fosterMessageIsError = false;

    public function mount(Animal $animal): void
    {
        abort_unless(
            Animal::query()->publiclyVisible()->whereKey($animal->getKey())->exists(),
            404,
        );

        $animal->loadMissing(['media', 'organization']);

        $this->animal = $animal;

        $this->hasApplied = $this->currentUserHasApplied();
        $this->hasRequestedFoster = $this->currentUserHasRequestedFoster();
    }

    /**
     * Invia la candidatura dell'utente corrente per questo animale.
     *
     * Macchina di stati del ramo:
     *  - OSPITE            → redirect alla login adottante (intended = questa scheda).
     *  - PROFILO INCOMPLETO → redirect alla pagina profilo con flash informativo.
     *  - CANDIDABILE       → delega a SubmitApplication; successo riflesso in-place.
     *  - GIÀ CANDIDATO     → DuplicateApplication tradotta in messaggio amichevole.
     *
     * La logica vive nell'Action: qui si autorizza (policy `create`), si sceglie il
     * ramo e si traducono le eccezioni di dominio in feedback UI.
     */
    public function apply(SubmitApplication $submitApplication): mixed
    {
        $user = Auth::user();

        // OSPITE: instrada alla login del pannello adottante, memorizzando questa
        // scheda come intended-URL per tornarci dopo l'autenticazione.
        if (! $user instanceof User) {
            session()->put('url.intended', route('animals.show', $this->animal));

            return $this->redirectRoute('filament.adopter.auth.login', navigate: false);
        }

        // Autorizzazione: chiunque può candidarsi (SubmitApplication impone le
        // guardie di dominio). Manteniamo comunque il gancio alla policy.
        $this->authorize('create', AdoptionApplication::class);

        // PROFILO INCOMPLETO: nessuna riga viene creata; instradiamo l'utente al
        // completamento del profilo con un messaggio esplicito.
        if (! $user->hasCompleteAdopterProfile()) {
            return $this->redirectToProfile();
        }

        try {
            $submitApplication->handle($user, $this->animal);

            $this->hasApplied = true;
            $this->applyMessageIsError = false;
            $this->applyMessage = __('applications.apply.flash_success');
        } catch (DuplicateApplication) {
            // GIÀ CANDIDATO: la CTA riflette lo stato "inviata"; messaggio amichevole.
            $this->hasApplied = true;
            $this->applyMessageIsError = false;
            $this->applyMessage = __('applications.apply.flash_duplicate');
        } catch (IncompleteAdopterProfile) {
            // Difensivo: il predicato sopra dovrebbe già aver deviato, ma se il
            // profilo è diventato incompleto tra i due controlli, instradiamo.
            return $this->redirectToProfile();
        }

        return null;
    }

    /**
     * Invia la richiesta di affido dell'utente corrente per questo animale.
     *
     * Specchio esatto di {@see self::apply()} per il ramo affido: stessa macchina
     * di rami (ospite / profilo incompleto / richiedibile / già richiesto), stessa
     * guardia di screening (profilo/consenso completo, riusata anche per l'affido).
     * La logica vive nell'Action {@see SubmitFosterApplication}: qui si autorizza
     * (policy `create`), si sceglie il ramo e si traducono le eccezioni di dominio
     * in feedback UI. Nessuna business logic qui.
     */
    public function requestFoster(SubmitFosterApplication $submitFosterApplication): mixed
    {
        $user = Auth::user();

        // OSPITE: instrada alla login del pannello adottante, memorizzando questa
        // scheda come intended-URL per tornarci dopo l'autenticazione.
        if (! $user instanceof User) {
            session()->put('url.intended', route('animals.show', $this->animal));

            return $this->redirectRoute('filament.adopter.auth.login', navigate: false);
        }

        // Autorizzazione: chiunque può richiedere l'affido (SubmitFosterApplication
        // impone le guardie di dominio). Manteniamo il gancio alla policy.
        $this->authorize('create', FosterApplication::class);

        // PROFILO INCOMPLETO: nessuna riga viene creata; instradiamo l'utente al
        // completamento del profilo con un messaggio esplicito.
        if (! $user->hasCompleteAdopterProfile()) {
            return $this->redirectToProfile();
        }

        try {
            $submitFosterApplication->handle($user, $this->animal);

            $this->hasRequestedFoster = true;
            $this->fosterMessageIsError = false;
            $this->fosterMessage = __('fostering.request.flash_success');
        } catch (DuplicateFosterApplication) {
            // GIÀ RICHIESTO: la CTA riflette lo stato "inviata"; messaggio amichevole.
            $this->hasRequestedFoster = true;
            $this->fosterMessageIsError = false;
            $this->fosterMessage = __('fostering.request.flash_duplicate');
        } catch (IncompleteAdopterProfile) {
            // Difensivo: il predicato sopra dovrebbe già aver deviato, ma se il
            // profilo è diventato incompleto tra i due controlli, instradiamo.
            return $this->redirectToProfile();
        }

        return null;
    }

    public function render(): View
    {
        $view = ViewFacade::make('livewire.animal-detail', [
            'photos' => $this->animal->getMedia('photos'),
        ]);

        // `title()` è una macro di Livewire sulla View (full-page component):
        // imposta il <title> passandolo al layout.
        $view->title($this->animal->name.' — ArkVel'); // @phpstan-ignore method.notFound

        return $view;
    }

    /**
     * Redirect alla pagina profilo del pannello adottante con flash informativo:
     * completa il profilo prima di candidarti.
     */
    private function redirectToProfile(): mixed
    {
        session()->flash('adopter.profile_notice', __('applications.apply.complete_profile'));

        return $this->redirectRoute('filament.adopter.pages.manage-adopter-profile', navigate: false);
    }

    /**
     * L'utente autenticato ha già una candidatura per questo animale. Predicato di
     * sola lettura sulla relazione self-owned: nessuna business logic.
     */
    private function currentUserHasApplied(): bool
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return false;
        }

        return $user->applications()
            ->where('animal_id', $this->animal->getKey())
            ->exists();
    }

    /**
     * L'utente autenticato ha già una richiesta di affido per questo animale.
     * Specchio di {@see self::currentUserHasApplied()}: predicato di sola lettura
     * sulla relazione self-owned (user_id), nessuna business logic.
     */
    private function currentUserHasRequestedFoster(): bool
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return false;
        }

        return FosterApplication::query()
            ->where('user_id', $user->getKey())
            ->where('animal_id', $this->animal->getKey())
            ->exists();
    }
}
