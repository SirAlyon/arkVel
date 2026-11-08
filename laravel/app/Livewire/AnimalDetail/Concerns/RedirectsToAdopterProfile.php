<?php

declare(strict_types=1);

namespace App\Livewire\AnimalDetail\Concerns;

use Livewire\Component;

/**
 * Per le CTA della scheda animale che richiedono lo screening d'adozione (candidatura e
 * affido): con il profilo adottante incompleto l'utente va alla pagina profilo del
 * pannello adottante, con un flash che gli chiede di completarlo.
 *
 * @phpstan-require-extends Component
 */
trait RedirectsToAdopterProfile
{
    protected function redirectToProfile(): mixed
    {
        session()->flash('adopter.profile_notice', __('applications.apply.complete_profile'));

        return $this->redirectRoute('filament.adopter.pages.manage-adopter-profile', navigate: false);
    }
}
