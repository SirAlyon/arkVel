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
 * `/termini` (route `legal.terms`): termini di servizio della piattaforma. Pagina di
 * contenuto statico resa come le altre pagine pubbliche (layout `app`, token semantici,
 * tema, breadcrumb, SEO). Nessuna logica: la versione e la data arrivano da {@see LegalLinks}.
 *
 * I contenuti sono una BOZZA da validare (vedi `legal_terms.draft`).
 */
class TermsOfService extends Component
{
    use InteractsWithSeo;

    public function render(PublicPageSeo $seo, LegalLinks $legalLinks): View
    {
        $document = LegalDocument::fromVersion($legalLinks->termsVersion());

        $view = ViewFacade::make('livewire.legal.terms', [
            'version' => $document->version,
            'updatedOn' => $document->updatedOn(),
        ]);
        $view->layoutData(['themeable' => true]); // @phpstan-ignore method.notFound

        return $this->withSeo($view, $seo->legal(
            'legal_terms.meta.title',
            'legal_terms.meta.description',
            'legal_terms.meta.breadcrumb',
            route(LegalLinks::TERMS_ROUTE),
        ));
    }
}
