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
 * Landing di annullamento dopo il ritorno da Stripe Checkout (cancel_url) per una
 * donazione una tantum: `/enti/{slug}/annulla`.
 *
 * Pagina SOTTILE e di sola lettura: il donatore ha abbandonato il Checkout, nessun
 * pagamento è avvenuto. Mostra un avviso localizzato e un link per tornare alla pagina
 * dell'ente e riprovare. La riga Donation resta Pending finché Stripe non invia l'evento
 * `checkout.session.expired` (→ Failed, via webhook); non la tocchiamo qui.
 */
class DonationCancelled extends Component
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
        $view = ViewFacade::make('livewire.donation-cancelled');
        $view->layoutData(['themeable' => true]); // @phpstan-ignore method.notFound

        return $this->withSeo($view, app(PublicPageSeo::class)->donationCancelled($this->organization));
    }
}
