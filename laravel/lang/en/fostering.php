<?php

declare(strict_types=1);

return [
    // Notification to organization members when a new foster request arrives.
    'new_received' => [
        'subject' => 'New foster request for :animal',
        'greeting' => 'Hi,',
        'line' => ':carer submitted a foster request for :animal.',
        'action' => 'Manage fostering',
        'salutation' => 'See you soon, the ArkVel team',
        // Short message for the in-app (database channel) notification.
        'database' => ':carer requested to foster :animal.',
    ],

    // Notification to the carer when the foster request status changes.
    'status_changed' => [
        'subject' => 'Update on your foster request for :animal',
        'greeting' => 'Hi :name,',
        'line' => 'The status of your foster request for :animal changed to ":status".',
        'action' => 'View request',
        'salutation' => 'See you soon, the ArkVel team',
        // Short message for the in-app (database channel) notification.
        'database' => 'Your foster request for :animal is now ":status".',
    ],

    // Notification to organization members when a carer withdraws the request.
    'withdrawn' => [
        'subject' => 'Foster request withdrawn for :animal',
        'greeting' => 'Hi,',
        'line' => ':carer withdrew the foster request for :animal.',
        'action' => 'Manage fostering',
        'salutation' => 'See you soon, the ArkVel team',
        // Short message for the in-app (database channel) notification.
        'database' => ':carer withdrew the foster request for :animal.',
    ],

    // Notification to the carer when the foster placement starts.
    'placement_started' => [
        'subject' => 'Foster started: :animal is with you',
        'greeting' => 'Hi :name,',
        'line' => 'The foster placement for :animal has started: thank you for welcoming them!',
        'action' => 'Go to my account',
        'salutation' => 'See you soon, the ArkVel team',
        // Short message for the in-app (database channel) notification.
        'database' => 'The foster placement for :animal has started.',
    ],

    // Notification to the carer when the foster placement ends.
    'placement_ended' => [
        'subject' => 'Foster ended: :animal',
        'greeting' => 'Hi :name,',
        'line' => 'The foster placement for :animal has ended. Thank you for your help!',
        'action' => 'Go to my account',
        'salutation' => 'See you soon, the ArkVel team',
        // Short message for the in-app (database channel) notification.
        'database' => 'The foster placement for :animal has ended.',
    ],
];
