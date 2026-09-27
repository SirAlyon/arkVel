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

    // "Request foster" CTA on the public animal page.
    'request' => [
        'cta_body' => 'Can\'t adopt but want to help? Offer to become a temporary foster carer.',
        'cta_button' => 'Request to foster',
        'submitting' => 'Sending…',
        'requested' => 'Request submitted',
        'my_foster_applications' => 'My foster requests',
        'flash_success' => 'Foster request submitted: the organization will get back to you.',
        'flash_duplicate' => 'You have already submitted a foster request for this animal.',
    ],

    // "My foster requests" page of the adopter panel.
    'my' => [
        'title' => 'My foster requests',
        'animal' => 'Animal',
        'organization' => 'Organization',
        'status' => 'Status',
        'submitted_at' => 'Submitted on',
        'decision_reason' => 'Reason',
        'placement_type' => 'Foster type',
        'placement_start' => 'Foster start',
        'placement_end' => 'Foster end',
        'withdraw' => 'Withdraw',
        'withdraw_confirm_heading' => 'Withdraw the foster request?',
        'withdraw_confirm_body' => 'The request will be withdrawn. If the foster is active, the animal will return to the organization. Proceed?',
        'withdraw_confirm_submit' => 'Withdraw the request',
        'withdraw_done' => 'Foster request withdrawn.',
        'empty_title' => 'No foster requests',
        'empty_body' => 'You haven\'t submitted any foster requests yet. Browse the catalog and offer to foster the animal you care about.',
    ],

    // Foster request management on the organization side (shelter panel).
    'shelter' => [
        'nav_group' => 'Fostering',
        'label' => 'Foster request',
        'plural_label' => 'Foster requests',
        'nav_label' => 'Fostering',

        // Columns / fields.
        'animal' => 'Animal',
        'species' => 'Species',
        'carer' => 'Carer',
        'email' => 'Email',
        'status' => 'Status',
        'assigned_to' => 'Assigned to',
        'unassigned' => 'Unassigned',
        'submitted_at' => 'Submitted on',
        'decided_at' => 'Decided on',
        'decision_reason' => 'Decision reason',
        'staff_notes' => 'Internal notes',
        'placement_type' => 'Foster type',
        'placement_status' => 'Foster status',
        'placement_start' => 'Foster start',
        'placement_end' => 'Foster end',
        'placement_end_help' => 'Leave empty if the foster has no set end date.',
        'placement_notes' => 'Foster notes',

        // Board tabs (by state).
        'tabs' => [
            'all' => 'All',
            'to_review' => 'To review',
            'in_progress' => 'In progress',
            'decided' => 'Decided',
        ],

        // Row actions.
        'advance' => 'Change status',
        'advance_heading' => 'Update the request status',
        'advance_submit' => 'Apply',
        'new_status' => 'New status',
        'reason' => 'Reason (optional)',
        'reason_help' => 'Shared with the carer on decisions (approved / rejected / waitlisted).',
        'advanced' => 'Status updated',
        'advanced_body' => 'The request is now ":status".',
        'advance_failed' => 'Transition not allowed',

        'start_placement' => 'Start foster',
        'start_placement_heading' => 'Start the foster placement',
        'start_placement_help' => 'The animal will be placed with the carer and moved to "Fostered".',
        'start_placement_submit' => 'Start',
        'start_placement_failed' => 'Could not start the foster',
        'placement_started' => 'Foster started',
        'placement_started_body' => ':animal is now fostered.',

        'end_placement' => 'End foster',
        'end_placement_heading' => 'End the foster placement',
        'end_placement_help' => 'The foster will be closed and the animal will become available again.',
        'end_placement_submit' => 'End',
        'end_placement_failed' => 'Could not end the foster',
        'placement_ended' => 'Foster ended',
        'placement_ended_body' => 'The foster placement for :animal has ended.',
        'no_active_placement' => 'There is no active foster to end.',

        'assign' => 'Assign',
        'assign_heading' => 'Assign the request to a member',
        'assign_submit' => 'Assign',
        'assign_member' => 'Assigned member',
        'assign_done' => 'Request assigned.',
        'notes' => 'Internal notes',
        'notes_heading' => 'Edit the internal notes',
        'notes_submit' => 'Save',
        'notes_done' => 'Notes updated.',

        // View / infolist sections.
        'section_application' => 'Foster request',
        'section_placement' => 'Foster placement',
        'section_carer' => 'Carer profile',
        'section_carer_help' => 'Data minimized to what is needed to decide and organize the foster (GDPR).',
        'section_contact' => 'Contacts',
        'section_notes' => 'Internal management',

        // Carer profile fields (reuses the adoption screening).
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
            'contingency_plan' => 'Contingency plan',
            'phone' => 'Phone',
            'city' => 'City',
            'region' => 'Region',
            'country' => 'Country',
            'consent' => 'Data processing consent',
            'consented_at' => 'Consented on',
        ],

        'yes' => 'Yes',
        'no' => 'No',
        'unknown' => 'Not provided',

        'empty_title' => 'No foster requests',
        'empty_body' => 'When a carer offers to foster one of your animals, the request will appear here.',
    ],
];
