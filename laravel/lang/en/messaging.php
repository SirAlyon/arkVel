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

    // Shared conversation-thread strings (list + modal + reply), reused by the
    // adopter panel and the organization panel.
    'thread' => [
        'organization' => 'Organization',
        'adopter' => 'Adopter',
        'animal' => 'Animal',
        'subject' => 'Subject',
        'last_message' => 'Last message',
        'last_message_at' => 'Updated',
        'unread' => 'Unread',
        'no_subject' => 'No subject',
        'no_messages' => 'No messages yet.',
        'open' => 'Open',
        'open_heading' => 'Conversation',
        'close' => 'Close',
        'you' => 'You',
        'sent_at' => 'Sent on :date',
    ],

    // Reply box for an existing conversation (delegates to PostMessage).
    'reply' => [
        'placeholder' => 'Write a message…',
        'label' => 'Message',
        'send' => 'Send',
        'sending' => 'Sending…',
        'sent' => 'Message sent.',
        'empty' => 'The message cannot be empty.',
    ],

    // Adopter panel: "Messages" page.
    'adopter' => [
        'title' => 'Messages',
        'nav_label' => 'Messages',
        'empty_title' => 'No conversations',
        'empty_body' => 'When you contact an organization, conversations show up here.',
    ],

    // Organization panel: "Conversations" page (tenant-scoped).
    'shelter' => [
        'title' => 'Conversations',
        'nav_label' => 'Conversations',
        'nav_group' => 'Communication',
        'empty_title' => 'No conversations',
        'empty_body' => 'Conversations with adopters show up here.',
        // Staff starting a new conversation.
        'new' => 'New conversation',
        'new_heading' => 'Write to an adopter',
        'new_submit' => 'Send',
        'new_adopter' => 'Adopter',
        'new_adopter_help' => 'Only people already related to your organization.',
        'new_subject' => 'Subject (optional)',
        'new_body' => 'Message',
        'new_done' => 'Conversation started.',
        'new_failed' => 'Could not start the conversation.',
    ],

    // Public site: "Contact the organization" CTA on the animal page.
    'contact' => [
        'cta_title' => 'Contact the organization',
        'cta_body' => 'Questions about :name? Message the organization that cares for them directly.',
        'placeholder' => 'Write your message…',
        'submit' => 'Send message',
        'submitting' => 'Sending…',
        'my_messages' => 'My messages',
        'flash_success' => 'Message sent to the organization. You will find the reply in your messages.',
        'flash_appended' => 'Message added to the existing conversation.',
        'empty' => 'Write a message before sending.',
    ],
];
