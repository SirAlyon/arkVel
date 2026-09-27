<?php

declare(strict_types=1);

return [
    // External calendar connections management page (shelter panel).
    'connections' => [
        'nav_group' => 'Appointments',
        'nav' => 'Calendar sync',
        'title' => 'Calendar sync',
        'subheading' => 'Connect the organization\'s external calendars: appointments will appear there and stay up to date automatically.',

        // Table columns.
        'provider' => 'Provider',
        'name' => 'Name',
        'status' => 'Status',
        'direction' => 'Direction',
        'last_synced_at' => 'Last synced',
        'last_error' => 'Last error',
        'never_synced' => 'Never',

        // Empty state.
        'empty_title' => 'No connected calendars',
        'empty_body' => 'Connect an external calendar to mirror the organization\'s appointments there. For now the demo connection is available; Google and CalDAV are coming soon.',

        // Action: connect (demo, log provider).
        'connect_demo' => 'Connect (demo)',
        'connect_demo_heading' => 'Connect a demo calendar?',
        'connect_demo_body' => 'Creates a demo connection (no credentials): appointments will be "synced" to a provider that only records the operations. Use it to try the pipeline before the Google and CalDAV integrations.',
        'connect_demo_submit' => 'Connect',
        'connect_demo_name' => 'Demo calendar',
        'connect_demo_done' => 'Demo connection created: appointments will be synced.',

        // Actions: real connections.
        'connect_google' => 'Connect Google Calendar',
        'connect_caldav' => 'Connect CalDAV',
        'coming_soon' => 'Coming soon',
        'coming_soon_notice' => 'CalDAV connections will be available in a future update.',

        // Google Calendar flow (Cal-5).
        'google' => [
            'name' => 'Google Calendar',
            'not_configured' => 'To enable Google Calendar, set the GOOGLE_CALENDAR_* variables in the server configuration.',
            'connected' => 'Google Calendar connected: appointments will be synced.',
            'error' => 'Could not complete the Google Calendar connection. Please try again.',
        ],

        // Action: disconnect.
        'disconnect' => 'Disconnect',
        'disconnect_heading' => 'Disconnect the calendar?',
        'disconnect_body' => 'The connection will be removed and appointments will no longer be synced to this calendar. Events already present in the external calendar will not be deleted.',
        'disconnect_submit' => 'Disconnect',
        'disconnect_done' => 'Calendar disconnected.',
    ],
];
