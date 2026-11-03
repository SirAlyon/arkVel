<?php

declare(strict_types=1);

return [
    // Reminder to the organization staff about an upcoming/overdue medical due date.
    'reminder' => [
        'subject' => 'Upcoming medical due date for :animal',
        'greeting' => 'Hi,',
        'line' => 'The :type entry for :animal is due on :due. Remember to schedule it.',
        'action' => 'Go to the dashboard',
        'salutation' => 'See you soon, the ArkVel team',
        // Short message for the in-app (database channel) notification.
        'database' => ':type for :animal due on :due.',
        // Summary printed by the arkvel:send-medical-reminders command.
        'command_summary' => 'Medical reminders sent: :count.',
    ],

    // "Upcoming medical due dates" widget on the shelter dashboard.
    'widget' => [
        'heading' => 'Upcoming medical due dates',
        'description' => 'Boosters and checkups due (or already overdue) within the next 30 days.',
        'animal' => 'Animal',
        'type' => 'Type',
        'next_due_at' => 'Due date',
        'vet_name' => 'Vet',
        'empty_title' => 'No upcoming due dates',
        'empty_body' => 'There are no medical due dates in the next 30 days.',
    ],
];
