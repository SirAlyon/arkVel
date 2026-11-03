<?php

declare(strict_types=1);

return [
    // Public site: "Support the organization" section on the organization page + the
    // return landings from Checkout (thank-you / cancel).
    'public' => [
        'heading' => 'Support :name',
        'subheading' => 'Your donation helps the organization care for its animals. Payment is securely handled by Stripe.',
        'frequency_label' => 'How often?',
        'frequency_one_off' => 'One-off',
        'frequency_monthly' => 'Monthly',
        'monthly_requires_login' => 'Sign in to donate monthly and manage your donation from your account.',
        'amount_label' => 'Choose an amount',
        'custom_amount_label' => 'Or enter an amount',
        'name_label' => 'Your name (optional)',
        'email_label' => 'Your email',
        'message_label' => 'Message (optional)',
        'submit' => 'Donate now',
        'submitting' => 'Redirecting…',
        'secure_note' => 'Secure payment by card or SEPA debit via Stripe.',
        'not_configured' => 'Donations are currently unavailable for this organization.',

        'validation' => [
            'amount_required' => 'Please enter an amount to donate.',
            'amount_numeric' => 'The amount must be a number.',
            'amount_min' => 'The minimum amount is €1.',
            'amount_max' => 'The maximum amount is €10,000.',
            'email_required' => 'Enter your email to receive the receipt.',
            'email_invalid' => 'Enter a valid email address.',
        ],

        // Thank-you landing (success_url).
        'thanks_title' => 'Thank you for your donation',
        'thanks_body' => 'Your support for :name makes a difference for the animals.',
        'thanks_pending_note' => 'Payment confirmation may take a moment. You will receive a receipt by email.',
        'back_to_organization' => 'Back to the organization page',

        // Cancel landing (cancel_url).
        'cancel_title' => 'Donation cancelled',
        'cancel_body' => 'No payment was made. You can try again whenever you like.',
        'cancel_retry' => 'Try donating again',
    ],

    // Organization panel: donations register (tenant-scoped, read-only).
    'shelter' => [
        'nav_group' => 'Fundraising',
        'nav_label' => 'Donations',
        'label' => 'Donation',
        'plural_label' => 'Donations',
        'date' => 'Date',
        'donor' => 'Donor',
        'anonymous' => 'Anonymous',
        'amount' => 'Amount',
        'type' => 'Type',
        'status' => 'Status',
        'message' => 'Message',
        'filter_status' => 'Status',
        'empty_title' => 'No donations',
        'empty_body' => 'Donations received by your organization will appear here.',

        'tabs' => [
            'all' => 'All',
            'completed' => 'Completed',
            'pending' => 'Pending',
            'unsuccessful' => 'Unsuccessful',
        ],
    ],

    // Adopter panel: my recurring donations + charge history (ownership-scoped).
    'my' => [
        'title' => 'My donations',
        'organization' => 'Organization',
        'amount' => 'Amount',
        'amount_monthly' => '€:amount / month',
        'status' => 'Status',
        'started_at' => 'Active since',
        'canceled_at' => 'Cancelled on',
        'cancel' => 'Cancel',
        'cancel_confirm_heading' => 'Cancel the monthly donation?',
        'cancel_confirm_body' => 'You will no longer be charged. The recurring donation will be cancelled. Do you want to proceed?',
        'cancel_confirm_submit' => 'Cancel the donation',
        'cancel_done' => 'Recurring donation cancelled.',
        'empty_title' => 'No recurring donations',
        'empty_body' => 'You have no active monthly donations. From an organization page you can start a recurring donation.',
        'charges_heading' => 'Charge history',
        'charges_description' => 'The successful monthly charges of your recurring donations.',
        'charge_date' => 'Date',
    ],
];
