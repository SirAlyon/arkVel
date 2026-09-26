<?php

declare(strict_types=1);

return [
    // Notification to the adopter when the application status changes.
    'status_changed' => [
        'subject' => 'Update on your application for :animal',
        'greeting' => 'Hi :name,',
        'line' => 'The status of your application for :animal changed to ":status".',
        'action' => 'View application',
        'salutation' => 'See you soon, the ArkVel team',
        // Short message for the in-app (database channel) notification.
        'database' => 'Your application for :animal is now ":status".',
    ],
];
