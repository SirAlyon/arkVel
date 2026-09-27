<?php

declare(strict_types=1);

return [
    // Notification to organization members when a new appointment is requested.
    'new_requested' => [
        'subject' => 'New appointment requested for :animal',
        'greeting' => 'Hi,',
        'line' => ':requester requested an appointment (:type) for :animal on :when.',
        'action' => 'Manage appointments',
        'salutation' => 'See you soon, the ArkVel team',
        // Short message for the in-app (database channel) notification.
        'database' => ':requester requested an appointment (:type) for :animal.',
    ],

    // Notification to the requester when the organization confirms the appointment.
    'confirmed' => [
        'subject' => 'Appointment confirmed for :animal',
        'greeting' => 'Hi :name,',
        'line' => 'Your appointment for :animal has been confirmed for :when.',
        'action' => 'Go to my account',
        'salutation' => 'See you soon, the ArkVel team',
        // Short message for the in-app (database channel) notification.
        'database' => 'Your appointment for :animal is confirmed for :when.',
    ],

    // Notification to the counterparty when an appointment is cancelled.
    'cancelled' => [
        'subject' => 'Appointment cancelled for :animal',
        'greeting' => 'Hi,',
        'line' => 'The appointment for :animal on :when has been cancelled.',
        'reason' => 'Reason: :reason',
        'action' => 'Go to my account',
        'salutation' => 'See you soon, the ArkVel team',
        // Short message for the in-app (database channel) notification.
        'database' => 'The appointment for :animal on :when has been cancelled.',
    ],

    // Notification to the requester when the appointment is completed.
    'completed' => [
        'subject' => 'Appointment completed for :animal',
        'greeting' => 'Hi :name,',
        'line' => 'Your appointment for :animal has been completed. Thank you!',
        'action' => 'Go to my account',
        'salutation' => 'See you soon, the ArkVel team',
        // Short message for the in-app (database channel) notification.
        'database' => 'Your appointment for :animal has been completed.',
    ],

    // Notification to the requester when a no-show is recorded.
    'no_show' => [
        'subject' => 'No-show recorded for the appointment on :animal',
        'greeting' => 'Hi :name,',
        'line' => 'You were marked as absent for the appointment for :animal on :when.',
        'action' => 'Go to my account',
        'salutation' => 'See you soon, the ArkVel team',
        // Short message for the in-app (database channel) notification.
        'database' => 'You were marked as absent for the appointment for :animal.',
    ],
];
