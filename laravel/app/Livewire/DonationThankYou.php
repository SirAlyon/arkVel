<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\OrganizationStatus;
use App\Models\Organization;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\View as ViewFacade;
use Livewire\Component;

/**
 * Landing di ringraziamento dopo il ritorno da Stripe Checkout (success_url) per una
 * donazione una tantum: `/enti/{slug}/grazie`.
 *
 * Pagina SOTTILE e di sola lettura: NON conferma il pagamento (lo fa il webhook). Spiega
 * all'utente che l'incasso può richiedere un istante per confermarsi (in particolare col
 * SEPA, asincrono). Nessuna mutazione di stato qui: la verità sullo stato della donazione
 * arriva solo dall'evento Stripe lato server.
 */
class DonationThankYou extends Component
{
    public Organization $organization;

    public function mount(Organization $organization): void
    {
        abort_unless($organization->status === OrganizationStatus::Verified, 404);

        $this->organization = $organization;
    }

    public function render(): View
    {
        $view = ViewFacade::make('livewire.donation-thank-you');

        $view->title(__('donations.public.thanks_title').' — ArkVel'); // @phpstan-ignore method.notFound

        return $view;
    }
}
