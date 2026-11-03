<?php

declare(strict_types=1);

return [
    // Super-admin page (Admin panel): platform-level Stripe credentials. A single Stripe
    // account for the whole instance; donations are recorded per organization.
    'nav_group' => 'Settings',
    'nav' => 'Payments integration',
    'title' => 'Stripe integration',

    // Form section.
    'section' => 'Stripe credentials',
    'section_help' => 'Configure your Stripe account keys here for the whole platform. Values set here take precedence over environment variables (.env). Until a publishable key and a secret key are configured, donations stay disabled on the public site.',

    // Fields.
    'publishable_key' => 'Publishable key',
    'publishable_key_help' => 'Your Stripe account publishable key (pk_...). Not a secret.',
    'secret_key' => 'Secret key',
    'secret_placeholder' => 'Leave blank to keep the current value',
    'secret_stored' => 'A secret key is currently configured. Fill the field only to replace it; leaving it blank keeps it unchanged.',
    'secret_missing' => 'No secret key configured. Enter it (sk_...) to enable donations.',
    'webhook_secret' => 'Webhook signing secret',
    'webhook_stored' => 'A webhook secret is currently configured. Fill the field only to replace it; leaving it blank keeps it unchanged.',
    'webhook_missing' => 'No webhook secret configured. Create a webhook endpoint in Stripe pointing to :url and paste its signing secret (whsec_...) here. Without it, payment confirmations are not verified.',

    // Configuration status.
    'status_db' => 'Configured from the interface (values saved here take precedence over .env).',
    'status_env' => 'Configured through environment variables (.env). You can override them here.',
    'status_none' => 'Stripe is not configured yet: enter a publishable key and a secret key to enable donations.',

    // Actions and outcomes.
    'save' => 'Save',
    'saved' => 'Stripe credentials saved.',
];
