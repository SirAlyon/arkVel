<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\OrganizationStatus;
use App\Models\Organization;
use App\Support\Seo\InteractsWithSeo;
use App\Support\Seo\PublicPageSeo;
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
    use InteractsWithSeo;

    public Organization $organization;

    public function mount(Organization $organization): void
    {
        abort_unless($organization->status === OrganizationStatus::Verified, 404);

        $this->organization = $organization;
    }

    public function render(): View
    {
        $view = ViewFacade::make('livewire.donation-thank-you');
        $view->layoutData(['themeable' => true]); // @phpstan-ignore method.notFound

        return $this->withSeo($view, app(PublicPageSeo::class)->donationThanks($this->organization));
    }
}
