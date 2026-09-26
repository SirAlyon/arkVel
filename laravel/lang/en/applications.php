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

    // Application flow from the public animal detail page.
    'apply' => [
        'submitting' => 'Sending…',
        'applied' => 'Application sent',
        'my_applications' => 'My applications',
        'flash_success' => 'Application sent: the organization will get in touch.',
        'flash_duplicate' => 'You have already applied for this animal.',
        'complete_profile' => 'Complete your adopter profile before applying.',
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
