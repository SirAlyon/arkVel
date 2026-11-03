<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Application enum labels (App\Enums)
|--------------------------------------------------------------------------
|
| One key per enum (snake_case class name), one per case (the backing value).
| Exceptions:
|  - permission_name: the `<resource>.<action>` value becomes a nested path;
|  - role_name: the value contains spaces, so the snake_case case name is used.
| AppLocale is not here: it uses endonyms, independent of the current locale.
*/

return [
    'animal_status' => [
        'draft' => 'Draft',
        'available' => 'Available',
        'reserved' => 'Reserved',
        'adoption_pending' => 'Adoption in progress',
        'adopted' => 'Adopted',
        'fostered' => 'In foster care',
        'medical_hold' => 'Medical hold',
        'not_available' => 'Not available',
    ],

    'application_status' => [
        'submitted' => 'Submitted',
        'under_review' => 'Under review',
        'interview' => 'Interview / check',
        'approved' => 'Approved',
        'adoption_pending' => 'Adoption in progress',
        'completed' => 'Completed',
        'rejected' => 'Rejected',
        'withdrawn' => 'Withdrawn',
        'waitlisted' => 'Waiting list',
    ],

    'appointment_status' => [
        'requested' => 'Requested',
        'confirmed' => 'Confirmed',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'no_show' => 'No-show',
    ],

    'appointment_type' => [
        'visit' => 'Visit',
        'interview' => 'Interview',
        'home_check' => 'Home check',
    ],

    'calendar_connection_status' => [
        'active' => 'Active',
        'error' => 'Error',
        'disconnected' => 'Disconnected',
    ],

    'calendar_provider' => [
        'google' => 'Google Calendar',
        'caldav' => 'CalDAV',
        'log' => 'Demo (log only)',
    ],

    'calendar_sync_direction' => [
        'push' => 'Send only (ArkVel → external)',
        'both' => 'Two-way (send and receive)',
    ],

    'donation_status' => [
        'pending' => 'Pending',
        'completed' => 'Completed',
        'failed' => 'Failed',
        'refunded' => 'Refunded',
        'canceled' => 'Cancelled',
    ],

    'donation_type' => [
        'one_off' => 'One-off',
        'recurring' => 'Recurring',
    ],

    'energy_level' => [
        'low' => 'Low',
        'medium' => 'Medium',
        'high' => 'High',
    ],

    'follow_up_status' => [
        'pending' => 'To be scheduled',
        'scheduled' => 'Scheduled',
        'completed' => 'Completed',
        'not_needed' => 'Not needed',
    ],

    'foster_application_status' => [
        'submitted' => 'Submitted',
        'under_review' => 'Under review',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'waitlisted' => 'Waiting list',
        'active' => 'Foster in progress',
        'completed' => 'Completed',
        'withdrawn' => 'Withdrawn',
    ],

    'foster_placement_status' => [
        'active' => 'Ongoing',
        'ended' => 'Ended',
    ],

    'foster_placement_type' => [
        'pre_adoption' => 'Pre-adoption',
        'medical' => 'Medical',
        'emergency' => 'Emergency',
        'long_term' => 'Long-term',
    ],

    'housing_type' => [
        'apartment' => 'Apartment',
        'house' => 'Detached house',
        'farm' => 'Rural home / with land',
        'other' => 'Other',
    ],

    'medical_record_type' => [
        'vaccination' => 'Vaccination',
        'treatment' => 'Treatment',
        'surgery' => 'Surgery',
        'checkup' => 'Check-up',
        'deworming' => 'Deworming',
    ],

    'membership_role' => [
        'owner' => 'Owner',
        'staff' => 'Staff',
        'volunteer' => 'Volunteer',
    ],

    'organization_status' => [
        'pending' => 'Pending',
        'verified' => 'Verified',
        'suspended' => 'Suspended',
    ],

    'organization_type' => [
        'canile_comunale' => 'Municipal dog shelter',
        'canile_privato' => 'Private dog shelter',
        'rifugio' => 'Animal shelter',
        'associazione' => 'Association',
        'societa' => 'Company',
    ],

    'permission_name' => [
        'organization' => [
            'update' => 'Edit organization',
            'manage_staff' => 'Manage organization staff',
        ],
        'animal' => [
            'view' => 'View animals',
            'create' => 'Create animals',
            'update' => 'Edit animals',
            'delete' => 'Delete animals',
        ],
        'medical_record' => [
            'view' => 'View medical records',
            'create' => 'Create medical records',
            'update' => 'Edit medical records',
            'delete' => 'Delete medical records',
        ],
        'application' => [
            'view' => 'View applications',
            'manage' => 'Manage applications',
        ],
        'foster_application' => [
            'view' => 'View foster requests',
            'manage' => 'Manage foster requests',
        ],
        'appointment' => [
            'view' => 'View appointments',
            'manage' => 'Manage appointments',
        ],
        'calendar' => [
            'manage' => 'Manage calendar sync',
        ],
        'message' => [
            'view' => 'View messages',
            'manage' => 'Manage messages',
        ],
        'donation' => [
            'view' => 'View donations',
            'manage' => 'Manage donations',
        ],
    ],

    'recurring_donation_status' => [
        'pending' => 'Pending',
        'active' => 'Active',
        'past_due' => 'Past due',
        'canceled' => 'Cancelled',
    ],

    'rent_or_own' => [
        'own' => 'Owned',
        'rent' => 'Rented',
    ],

    'role_name' => [
        'super_admin' => 'Super Administrator',
        'org_owner' => 'Organization owner',
        'org_staff' => 'Organization staff',
        'org_volunteer' => 'Organization volunteer',
        'adopter' => 'Adopter',
    ],

    'sex' => [
        'male' => 'Male',
        'female' => 'Female',
    ],

    'size' => [
        'small' => 'Small',
        'medium' => 'Medium',
        'large' => 'Large',
    ],

    'species' => [
        'dog' => 'Dog',
        'cat' => 'Cat',
        'other' => 'Other',
    ],
];
