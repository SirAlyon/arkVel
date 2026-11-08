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

];
