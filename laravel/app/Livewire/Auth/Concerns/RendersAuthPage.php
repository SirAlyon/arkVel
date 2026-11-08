<?php

declare(strict_types=1);

namespace App\Livewire\Auth\Concerns;

use App\Exceptions\AlreadyAuthenticated;
use App\Support\Seo\InteractsWithSeo;
use App\Support\Seo\PublicPageSeo;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\View as ViewFacade;
use Throwable;

/**
 * Comune alle pagine di autenticazione del sito pubblico (componenti Livewire in
 * App\Livewire\Auth): layout pubblico con il tema (themeable: pagine nuove, tutte a token),
 * SEO da PublicPageSeo::auth() (noindex) con titolo e descrizione da
 * `auth_public.<pagina>.title|meta`, e il contatore degli invii che fa da wire:key del
 * riepilogo errori (x-ui.error-summary torna a prendere il focus a ogni tentativo fallito).
 *
 * Se un'Action per ospiti trova la sessione già autenticata ({@see AlreadyAuthenticated},
 * es. accesso fatto in un'altra scheda), la pagina porta l'utente alla sua destinazione
 * invece di mostrare un errore.
 */
trait RendersAuthPage
{
    use InteractsWithSeo;

    /** Invii del modulo: chiave del riepilogo errori, non è stato di dominio. */
    public int $submissions = 0;

    protected function authView(PublicPageSeo $seo, string $view, string $page, string $canonical): View
    {
        $view = ViewFacade::make($view);
        $view->layoutData(['themeable' => true]); // @phpstan-ignore method.notFound

        return $this->withSeo($view, $seo->auth(
            __('auth_public.'.$page.'.title'),
            __('auth_public.'.$page.'.meta'),
            $canonical,
        ));
    }

    /**
     * Hook di Livewire per le eccezioni (exception + nome del trait); i nomi dei parametri
     * sono quelli con cui Livewire li passa.
     */
    public function exceptionRendersAuthPage(Throwable $e, callable $stopPropagation): void
    {
        if ($e instanceof AlreadyAuthenticated) {
            $stopPropagation();
            $this->redirect($e->destination);
        }
    }
}
