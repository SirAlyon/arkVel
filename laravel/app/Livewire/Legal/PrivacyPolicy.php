<?php

declare(strict_types=1);

namespace App\Livewire\Legal;

use App\Support\Seo\InteractsWithSeo;
use App\Support\Seo\PublicPageSeo;
use App\Support\Site\LegalDocument;
use App\Support\Site\LegalLinks;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\View as ViewFacade;
use Livewire\Component;

/**
 * `/privacy` (route `legal.privacy`): informativa sul trattamento dei dati personali
 * (GDPR art. 13). Pagina di contenuto statico resa come le altre pagine pubbliche
 * (layout `app`, token semantici, tema, breadcrumb, SEO). Nessuna logica: la versione
 * in vigore e la data arrivano da {@see LegalLinks} e sono mostrate dalla pagina.
 *
 * I contenuti sono una BOZZA da validare dal titolare del trattamento: il testo lo dice in
 * cima (vedi `legal_privacy.draft`).
 */
class PrivacyPolicy extends Component
{
    use InteractsWithSeo;

    public function render(PublicPageSeo $seo, LegalLinks $legalLinks): View
    {
        $document = LegalDocument::fromVersion($legalLinks->privacyPolicyVersion());

        $view = ViewFacade::make('livewire.legal.privacy', [
            'version' => $document->version,
            'updatedOn' => $document->updatedOn(),
        ]);
        $view->layoutData(['themeable' => true]); // @phpstan-ignore method.notFound

        return $this->withSeo($view, $seo->legal(
            'legal_privacy.meta.title',
            'legal_privacy.meta.description',
            'legal_privacy.meta.breadcrumb',
            route(LegalLinks::PRIVACY_POLICY_ROUTE),
        ));
    }
}
