<?php

declare(strict_types=1);

namespace App\Support\Site;

use Illuminate\Support\Facades\Route;

/**
 * URL delle pagine legali, in un solo punto.
 *
 * Le route esistono dalla fase UI-legal: privacyPolicy()/termsOfService() restituiscono
 * l'URL quando la rispettiva route è registrata, null altrimenti (fallback «In arrivo» nel
 * footer e testo senza link nella casella di presa visione in /registrati). Tenere il
 * controllo `Route::has` rende i consumatori robusti anche se una route viene disattivata.
 */
final readonly class LegalLinks
{
    public const PRIVACY_POLICY_ROUTE = 'legal.privacy';

    public const TERMS_ROUTE = 'legal.terms';

    /** Informativa sulla privacy, o null se la pagina non c'è. */
    public function privacyPolicy(): ?string
    {
        return Route::has(self::PRIVACY_POLICY_ROUTE) ? route(self::PRIVACY_POLICY_ROUTE) : null;
    }

    /** Termini di servizio, o null se la pagina non c'è. */
    public function termsOfService(): ?string
    {
        return Route::has(self::TERMS_ROUTE) ? route(self::TERMS_ROUTE) : null;
    }

    /** Versione dell'informativa in vigore, registrata con la presa visione. */
    public function privacyPolicyVersion(): string
    {
        return $this->version('arkvel.privacy_policy_version');
    }

    /** Versione dei termini di servizio in vigore. */
    public function termsVersion(): string
    {
        return $this->version('arkvel.terms_version');
    }

    private function version(string $key): string
    {
        $version = config($key);

        return is_string($version) && $version !== '' ? $version : '2026-11';
    }
}
