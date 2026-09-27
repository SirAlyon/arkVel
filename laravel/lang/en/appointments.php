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

    // Reminder to the requester about an upcoming confirmed appointment.
    'reminder' => [
        'subject' => 'Reminder: appointment for :animal',
        'greeting' => 'Hi :name,',
        'line' => 'A reminder about your appointment (:type) for :animal on :when.',
        'action' => 'Go to my account',
        'salutation' => 'See you soon, the ArkVel team',
        // Short message for the in-app (database channel) notification.
        'database' => 'Reminder: appointment (:type) for :animal on :when.',
        // Summary printed by the arkvel:send-appointment-reminders command.
        'command_summary' => 'Appointment reminders sent: :count.',
    ],

    // "Upcoming appointments" widget on the shelter dashboard.
    'widget' => [
        'heading' => 'Upcoming appointments',
        'description' => 'Active appointments still to be held, in chronological order.',
        'scheduled_at' => 'Date and time',
        'animal' => 'Animal',
        'requester' => 'Requester',
        'type' => 'Type',
        'status' => 'Status',
        'empty_title' => 'No upcoming appointments',
        'empty_body' => 'There are no active appointments scheduled.',
    ],

    // Booking CTA on the public animal page.
    'booking' => [
        'cta_title' => 'Book a visit',
        'cta_body' => 'Want to meet :name in person? Pick a type and time and book a meeting with the organization.',
        'type' => 'Meeting type',
        'scheduled_at' => 'Date and time',
        'notes' => 'Notes (optional)',
        'notes_placeholder' => 'Add anything useful for the organization…',
        'submit' => 'Book',
        'submitting' => 'Booking…',
        'my_appointments' => 'My appointments',
        'flash_success' => 'Appointment requested: the organization will confirm the time.',
        // Validation / domain-error messages shown in place.
        'validation' => [
            'type_required' => 'Choose the meeting type.',
            'type_invalid' => 'The selected meeting type is not valid.',
            'scheduled_at_required' => 'Enter the appointment date and time.',
            'scheduled_at_invalid' => 'The date and time entered are not valid.',
        ],
        'slot_in_past' => 'Choose a date and time in the future.',
        'slot_unavailable' => 'That slot is unavailable: try another time.',
    ],

    // "My appointments" page of the adopter panel.
    'my' => [
        'title' => 'My appointments',
        'animal' => 'Animal',
        'organization' => 'Organization',
        'type' => 'Type',
        'status' => 'Status',
        'scheduled_at' => 'Date and time',
        'cancel' => 'Cancel',
        'cancel_confirm_heading' => 'Cancel the appointment?',
        'cancel_confirm_body' => 'The appointment will be cancelled and the organization will be notified. Do you want to proceed?',
        'cancel_confirm_submit' => 'Cancel the appointment',
        'cancel_done' => 'Appointment cancelled.',
        'cancel_failed' => 'Could not cancel the appointment',
        'empty_title' => 'No appointments',
        'empty_body' => 'You have not booked any appointments yet. Browse the catalog and book a visit for the animal you are interested in.',
    ],

    // Appointment management on the organization side (shelter panel).
    'shelter' => [
        'nav_group' => 'Appointments',
        'label' => 'Appointment',
        'plural_label' => 'Appointments',
        'nav_label' => 'Appointments',

        // Columns / fields.
        'scheduled_at' => 'Date and time',
        'animal' => 'Animal',
        'requester' => 'Requester',
        'email' => 'Email',
        'type' => 'Type',
        'status' => 'Status',
        'organization' => 'Organization',
        'notes' => 'Notes',

        // Table filters.
        'filter_status' => 'Status',
        'filter_type' => 'Type',
        'filter_from' => 'From',
        'filter_to' => 'To',
        'filter_upcoming' => 'Upcoming only',

        // Board tabs (by lifecycle stage).
        'tabs' => [
            'all' => 'All',
            'to_confirm' => 'To confirm',
            'confirmed' => 'Confirmed',
            'closed' => 'Closed',
        ],

        // View / infolist sections.
        'section_appointment' => 'Appointment',
        'section_requester' => 'Requester',

        // Staff management actions.
        'confirm' => 'Confirm',
        'confirm_heading' => 'Confirm the appointment?',
        'confirm_body' => 'The requester will receive the time confirmation.',
        'confirm_submit' => 'Confirm',
        'confirm_done' => 'Appointment confirmed.',
        'confirm_failed' => 'Could not confirm the appointment',

        'complete' => 'Complete',
        'complete_heading' => 'Mark the appointment as completed?',
        'complete_body' => 'The meeting will be recorded as held.',
        'complete_submit' => 'Complete',
        'complete_done' => 'Appointment completed.',
        'complete_failed' => 'Could not complete the appointment',

        'no_show' => 'No-show',
        'no_show_heading' => 'Record the requester as absent?',
        'no_show_body' => 'The appointment will be recorded as not honored.',
        'no_show_submit' => 'Record no-show',
        'no_show_done' => 'No-show recorded.',
        'no_show_failed' => 'Could not record the no-show',

        'cancel' => 'Cancel',
        'cancel_heading' => 'Cancel the appointment?',
        'cancel_submit' => 'Cancel the appointment',
        'cancel_reason' => 'Reason (optional)',
        'cancel_reason_help' => 'Shared with the requester in the cancellation notice.',
        'cancel_done' => 'Appointment cancelled.',
        'cancel_failed' => 'Could not cancel the appointment',

        'empty_title' => 'No appointments',
        'empty_body' => 'When someone books a visit for one of your animals, the appointment will appear here.',
    ],

    // Appointments calendar on the organization panel (primary agenda view).
    'calendar' => [
        'nav' => 'Calendar',
        'title' => 'Appointments calendar',
        // Calendar event title: "{type}: {animal}".
        'event_title' => ':type: :animal',
        // Modal opened when clicking an event.
        'manage_heading' => 'Appointment details',
        'close' => 'Close',
        // Calendar toolbar labels (rendered client-side).
        'toolbar' => [
            'today' => 'Today',
            'month' => 'Month',
            'week' => 'Week',
            'day' => 'Day',
        ],
        // Colour legend by status.
        'legend' => [
            'requested' => 'Requested',
            'confirmed' => 'Confirmed',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            'no_show' => 'No-show',
        ],
    ],
];
