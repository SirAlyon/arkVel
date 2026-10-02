<?php

declare(strict_types=1);

return [
    // Notification to the counterpart when a new message arrives in a thread
    // (same class for both the adopter and the organization staff).
    'new_message' => [
        'subject' => 'New message from :sender',
        'greeting' => 'Hi,',
        'line' => ':sender sent you a new message:',
        'action' => 'Read the message',
        'salutation' => 'See you soon, the ArkVel team',
        // Short message for the in-app notification (database channel).
        'database' => ':sender sent you a new message.',
    ],
];
