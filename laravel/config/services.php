<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    | Google Calendar (Cal-5). Config-gated: senza client_id/client_secret la
    | funzione di connessione resta INERTE (UI disabilitata, provider che solleva
    | CalendarProviderNotConfigured). Nessun segreto nel repo: solo env.
    | `redirect` è la callback OAuth registrata in Google Cloud Console; il default
    | punta alla rotta nominata `calendar.google.callback` risolta a runtime quando
    | l'app URL è disponibile (config statica → il fallback resta null qui, la rotta
    | viene risolta nel controller).
    */
    'google_calendar' => [
        'client_id' => env('GOOGLE_CALENDAR_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CALENDAR_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_CALENDAR_REDIRECT_URI'),
    ],

    /*
    | Mappe del sito pubblico (x-ui.map, UI-7): stili vettoriali MapLibre di OpenFreeMap
    | (gratuiti, senza chiave né cookie; tile © OpenMapTiles, dati © OpenStreetMap).
    | Il browser li scarica da tiles.openfreemap.org solo quando una mappa entra in vista.
    | Uno stile per tema: positron (chiaro e sobrio, i segnaposto restano in primo piano) e
    | fiord (blu scuro, vicino al navy del tema scuro). Per un'istanza self-hosted delle
    | tile basta cambiare questi URL.
    */
    'maps' => [
        'style_light' => env('MAP_STYLE_LIGHT', 'https://tiles.openfreemap.org/styles/positron'),
        'style_dark' => env('MAP_STYLE_DARK', 'https://tiles.openfreemap.org/styles/fiord'),
        // Privacy: lato in gradi della griglia con cui si approssimano le sedi degli enti
        // (App\Support\Map\ApproximateLocation). 0,02° ≈ 2 km; accettato tra 0,01° e 1°.
        'grid_degrees' => env('MAP_GRID_DEGREES', 0.02),
    ],

];
