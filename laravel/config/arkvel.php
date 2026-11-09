<?php

declare(strict_types=1);

/*
 | Impostazioni proprie di ArkVel (non di un pacchetto).
 */

return [

    /*
     | Versione dell'informativa sulla privacy in vigore: è registrata nell'activitylog con
     | la presa visione alla registrazione (App\Actions\Auth\RegisterAdopter), così si sa
     | quale testo l'utente aveva davanti. Va aggiornata a ogni modifica sostanziale
     | dell'informativa. Vedi App\Support\Site\LegalLinks.
     */
    'privacy_policy_version' => env('PRIVACY_POLICY_VERSION', '2026-11'),

    /*
     | Reveal allo scroll del sito pubblico (x-reveal, resources/js/ui/motion.js). Spento
     | (UI_REVEAL=false) i layout scrivono <html data-reveal="off"> e nessun blocco aspetta lo
     | scroll per comparire. È anche l'interruttore esplicito dei test browser che non
     | riguardano il motion (tests/Pest.php): screenshot a pagina intera e audit axe vedono
     | tutto, senza che il codice di produzione riconosca i browser automatizzati.
     | prefers-reduced-motion spegne comunque il reveal, a prescindere da qui.
     */
    'reveal' => env('UI_REVEAL', true),

];
