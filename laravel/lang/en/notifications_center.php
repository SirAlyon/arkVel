<?php

declare(strict_types=1);

return [
    // Navigation and title of the "Notifications" page (notification center: full
    // history, beyond the bell dropdown). Used in the adopter and shelter panels.
    'nav_label' => 'Notifications',
    'title' => 'Notifications',

    // Notification actions.
    'mark_read' => 'Mark as read',
    'mark_all_read' => 'Mark all as read',
    'open' => 'Open',
    'marked_read' => 'Notification marked as read.',
    'all_marked_read' => 'All notifications have been marked as read.',

    // Read state (badge/column).
    'state' => [
        'unread' => 'Unread',
        'read' => 'Read',
    ],

    // "Unread only" filter.
    'filter' => [
        'unread_only' => 'Unread only',
    ],

    // Fallback when the notification payload has no readable message.
    'fallback_message' => 'You have received a notification.',

    // Table columns.
    'column' => [
        'message' => 'Message',
        'received_at' => 'Received',
        'state' => 'State',
    ],

    // Empty state.
    'empty_title' => 'No notifications',
    'empty_body' => 'Notifications about applications, fostering, appointments and messages will appear here.',

    // Footer tagline for transactional emails (ArkVel brand).
    'email_footer_tagline' => 'ArkVel — platform for animal adoption and management.',
];
