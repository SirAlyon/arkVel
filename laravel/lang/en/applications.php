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

    // Notification to organization members when a new application arrives.
    'new_received' => [
        'subject' => 'New application for :animal',
        'greeting' => 'Hi,',
        'line' => ':adopter submitted an adoption application for :animal.',
        'action' => 'Manage applications',
        'salutation' => 'See you soon, the ArkVel team',
        // Short message for the in-app (database channel) notification.
        'database' => ':adopter applied for :animal.',
    ],

    // Notification to organization members when an adopter withdraws the application.
    'withdrawn' => [
        'subject' => 'Application withdrawn for :animal',
        'greeting' => 'Hi,',
        'line' => ':adopter withdrew the adoption application for :animal.',
        'action' => 'Manage applications',
        'salutation' => 'See you soon, the ArkVel team',
        // Short message for the in-app (database channel) notification.
        'database' => ':adopter withdrew the application for :animal.',
    ],

    // Application flow from the public animal detail page.
    'apply' => [
        'submitting' => 'Sending…',
        'applied' => 'Application sent',
        'my_applications' => 'My applications',
        'flash_success' => 'Application sent: the organization will get in touch.',
        'flash_duplicate' => 'You have already applied for this animal.',
        'complete_profile' => 'Complete your adopter profile before applying.',
    ],

    // Organization-side application management (shelter panel).
    'shelter' => [
        'nav_group' => 'Adoptions',
        'label' => 'Application',
        'plural_label' => 'Applications',
        'nav_label' => 'Applications',

        // Columns / fields.
        'animal' => 'Animal',
        'adopter' => 'Adopter',
        'status' => 'Status',
        'assigned_to' => 'Assigned to',
        'unassigned' => 'Unassigned',
        'submitted_at' => 'Submitted on',
        'decided_at' => 'Decided on',
        'decision_reason' => 'Decision reason',
        'staff_notes' => 'Internal notes',

        // Board tabs (by status).
        'tabs' => [
            'all' => 'All',
            'to_review' => 'To review',
            'in_progress' => 'In progress',
            'decided' => 'Decided',
        ],

        // Row actions.
        'advance' => 'Change status',
        'advance_heading' => 'Update the application status',
        'advance_submit' => 'Apply',
        'new_status' => 'New status',
        'reason' => 'Reason (optional)',
        'reason_help' => 'Shared with the adopter on decisions (approved / rejected / waitlisted).',
        'advanced' => 'Status updated',
        'advanced_body' => 'The application is now ":status".',
        'advance_failed' => 'Transition not allowed',
        'assign' => 'Assign',
        'assign_heading' => 'Assign the application to a member',
        'assign_submit' => 'Assign',
        'assign_member' => 'Assigned member',
        'assign_done' => 'Application assigned.',
        'notes' => 'Internal notes',
        'notes_heading' => 'Edit internal notes',
        'notes_submit' => 'Save',
        'notes_done' => 'Notes updated.',

        // View / infolist sections.
        'section_application' => 'Application',
        'section_adopter' => 'Adopter profile',
        'section_adopter_help' => 'Data minimized to what is needed for the decision and to arrange the interview (GDPR).',
        'section_contact' => 'Contact',
        'section_notes' => 'Internal management',

        // Adopter profile fields.
        'profile' => [
            'housing_type' => 'Housing type',
            'has_garden' => 'Garden',
            'fenced' => 'Fenced',
            'rent_or_own' => 'Own / rent',
            'landlord_allows_pets' => 'Landlord allows pets',
            'adults_in_home' => 'Adults in home',
            'kids_in_home' => 'Kids in home',
            'other_pets' => 'Other pets',
            'experience' => 'Experience',
            'hours_alone_weekday' => 'Hours alone (weekday)',
            'hours_alone_weekend' => 'Hours alone (weekend)',
            'lifestyle' => 'Lifestyle',
            'ideal_animal' => 'Ideal animal',
            'contingency_plan' => 'Contingency plan',
            'previous_surrender' => 'Previous surrender',
            'phone' => 'Phone',
            'city' => 'City',
            'region' => 'Region',
            'country' => 'Country',
            'consent' => 'Data processing consent',
            'consented_at' => 'Consented on',
            'no_profile' => 'The adopter has not filled in their profile yet.',
        ],

        'yes' => 'Yes',
        'no' => 'No',
        'unknown' => 'Not provided',

        'empty_title' => 'No applications',
        'empty_body' => 'When an adopter applies for one of your animals, the application will show up here.',
    ],

    // "My applications" page in the adopter panel.
    'my' => [
        'title' => 'My applications',
        'animal' => 'Animal',
        'organization' => 'Organization',
        'status' => 'Status',
        'submitted_at' => 'Submitted on',
        'decision_reason' => 'Reason',
        'withdraw' => 'Withdraw',
        'withdraw_confirm_heading' => 'Withdraw the application?',
        'withdraw_confirm_body' => 'The application will be withdrawn and cannot be reactivated. Do you want to proceed?',
        'withdraw_confirm_submit' => 'Withdraw application',
        'withdraw_done' => 'Application withdrawn.',
        'empty_title' => 'No applications',
        'empty_body' => 'You have not applied yet. Browse the catalog and apply for the animal you are interested in.',
    ],
];
