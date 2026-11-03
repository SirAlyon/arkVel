<?php

declare(strict_types=1);

return [
    // Super-admin page (Admin panel): platform-level Google Calendar OAuth app
    // credentials. Each organization then connects its own Google account.
    'nav_group' => 'Settings',
    'nav' => 'Calendar integration',
    'title' => 'Google Calendar integration',

    // Form section.
    'section_google' => 'Google Calendar OAuth app',
    'section_google_help' => 'Configure the Google Calendar OAuth app credentials for the whole platform here. Values set here take precedence over environment variables (.env). Each organization will then connect its own Google account from its panel.',

    // Fields.
    'client_id' => 'Client ID',
    'client_id_help' => 'The OAuth 2.0 Client ID created in the Google Cloud Console.',
    'client_secret' => 'Client secret',
    'client_secret_placeholder' => 'Leave empty to keep the current secret',
    'client_secret_stored' => 'A client secret is currently configured. Fill this field only to replace it; leaving it empty keeps it unchanged.',
    'client_secret_missing' => 'No client secret configured. Enter one to enable the integration.',
    'redirect_uri' => 'Redirect URI',
    'redirect_uri_help' => 'Register this exact redirect URI in the Google Cloud Console (Credentials → Authorized redirect URIs): :url. If you leave this field empty, this address is used automatically.',

    // Configuration status.
    'status_db' => 'Configured from the UI (values saved here take precedence over .env).',
    'status_env' => 'Configured via environment variables (.env). You can override them here.',
    'status_none' => 'Google Calendar is not configured yet: enter a Client ID and Client secret.',

    // Actions and outcomes.
    'save' => 'Save',
    'saved' => 'Integration settings saved.',
];
