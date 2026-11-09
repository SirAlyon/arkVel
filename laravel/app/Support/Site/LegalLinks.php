<?php

declare(strict_types=1);

namespace App\Support\Site;

use Illuminate\Support\Facades\Route;

/**
 * URL delle pagine legali, in un solo punto.
 *
 * Oggi le pagine legali non esistono ancora (TODO prima della produzione): finché manca la
 * route `legal.privacy`, privacyPolicy() restituisce null e le viste mostrano il testo
 * senza link (es. la casella dell'informativa in /registrati). Quando la pagina arriva, il
 * link compare da solo.
 */
final readonly class LegalLinks
{
    public const PRIVACY_POLICY_ROUTE = 'legal.privacy';

    /** Informativa sulla privacy, o null se la pagina non c'è ancora. */
    public function privacyPolicy(): ?string
    {
        return Route::has(self::PRIVACY_POLICY_ROUTE) ? route(self::PRIVACY_POLICY_ROUTE) : null;
    }

    /** Versione dell'informativa in vigore, registrata con la presa visione. */
    public function privacyPolicyVersion(): string
    {
        $version = config('arkvel.privacy_policy_version');

        return is_string($version) && $version !== '' ? $version : '2026-11';
    }
}
