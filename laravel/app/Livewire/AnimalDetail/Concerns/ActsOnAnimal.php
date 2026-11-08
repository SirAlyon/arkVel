<?php

declare(strict_types=1);

namespace App\Livewire\AnimalDetail\Concerns;

use App\Livewire\AnimalDetail;
use App\Models\Animal;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Comune ai componenti figli della scheda animale ({@see AnimalDetail}): l'animale è
 * identificato da un id `#[Locked]` (identità del componente, non manomettibile lato
 * client), risolto dal DB a ogni richiesta. La visibilità pubblica è già imposta dalla
 * pagina al mount; i figli non sono montabili da soli.
 *
 * Gli ospiti che usano una CTA vengono instradati alla pagina di accesso pubblica, con
 * la scheda come intended-URL per tornarci dopo l'autenticazione.
 *
 * @phpstan-require-extends Component
 */
trait ActsOnAnimal
{
    #[Locked]
    public int $animalId;

    protected function animal(): Animal
    {
        return Animal::query()->findOrFail($this->animalId);
    }

    protected function redirectGuestToLogin(Animal $animal): mixed
    {
        session()->put('url.intended', route('animals.show', $animal));

        return $this->redirectRoute('login', navigate: false);
    }
}
