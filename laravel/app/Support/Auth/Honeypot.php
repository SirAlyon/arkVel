<?php

declare(strict_types=1);

namespace App\Support\Auth;

/**
 * Trappola anti-bot del modulo di registrazione, senza dipendenze esterne:
 *
 * - campo esca (`FIELD`): invisibile e fuori dall'albero di accessibilità (inert,
 *   aria-hidden), quindi un utente reale non lo vede né lo raggiunge da tastiera e
 *   lo lascia vuoto; molti bot compilano ogni input che trovano;
 * - tempo minimo (`MIN_SECONDS`): tra il caricamento del modulo e l'invio deve passare
 *   qualche secondo. Il momento del caricamento è una proprietà #[Locked] del
 *   componente Livewire, che il client non può modificare.
 *
 * Non sostituisce il rate limit (vedi RegisterAdopter): lo affianca.
 */
final class Honeypot
{
    /** Nome del campo esca: plausibile per un bot, inutile per chi si registra. */
    public const FIELD = 'website';

    /** Secondi minimi tra la comparsa del modulo e l'invio. */
    public const MIN_SECONDS = 2;

    /**
     * @param  string  $trap  valore del campo esca
     * @param  int  $renderedAt  timestamp Unix del caricamento del modulo
     * @param  int  $submittedAt  timestamp Unix dell'invio
     */
    public function isBot(string $trap, int $renderedAt, int $submittedAt): bool
    {
        return $trap !== '' || ($submittedAt - $renderedAt) < self::MIN_SECONDS;
    }
}
