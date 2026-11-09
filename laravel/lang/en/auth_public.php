<?php

declare(strict_types=1);

/*
 | Authentication pages of the public site (UI-5): sign in, sign up, forgotten password,
 | reset and email verification. Components in App\Livewire\Auth, logic in
 | App\Actions\Auth, views in resources/views/livewire/auth.
 | These are not the Filament translations (the /manage and /admin panels use their own).
 */

return [
    'throttled' => [
        'seconds' => 'Too many attempts. Try again in :count second.|Too many attempts. Try again in :count seconds.',
        'minutes' => 'Too many attempts. Try again in :count minute.|Too many attempts. Try again in :count minutes.',
    ],

    'validation' => [
        'name_required' => 'Enter your name.',
        'name_max' => 'Your name can be at most 255 characters long.',
        'email_required' => 'Enter your email address.',
        'email_invalid' => 'Enter a valid email address, like name@example.com.',
        'email_max' => 'Your email address can be at most 255 characters long.',
        'email_taken' => 'An account with this email address already exists. Sign in or reset your password.',
        'password_required' => 'Enter your password.',
        'password_confirmed' => 'The two passwords do not match.',
        'password_min' => 'Your password must be at least :min characters long.',
        'password_mixed' => 'Your password must contain at least one uppercase and one lowercase letter.',
        'password_letters' => 'Your password must contain at least one letter.',
        'password_numbers' => 'Your password must contain at least one number.',
        'password_symbols' => 'Your password must contain at least one symbol.',
        'password_uncompromised' => 'This password has appeared in a data leak: choose a different one.',
        'privacy_required' => 'To create an account, confirm you have read how we process your data.',
    ],

    'fields' => [
        'name' => 'Full name',
        'email' => 'Email',
        'password' => 'Password',
        'new_password' => 'New password',
        'password_confirmation' => 'Repeat the password',
        'password_help' => 'At least :min characters.',
        'honeypot' => 'Leave this field empty',
    ],

    'aside' => [
        'eyebrow' => 'Your ArkVel account',
        'title' => 'One place to follow your adoption',
        'perks' => [
            'favorites' => 'Save the animals you care about',
            'apply' => 'Send and follow your applications',
            'visits' => 'Book visits and message shelters',
        ],
    ],

    'login' => [
        'title' => 'Sign in',
        'meta' => 'Sign in to your ArkVel account to follow applications, visits and favourites.',
        'intro' => 'Welcome back! Enter your email and password.',
        'remember' => 'Remember me on this device',
        'forgot' => 'Forgot your password?',
        'submit' => 'Sign in',
        'failed' => 'Wrong email or password.',
        'no_account' => 'No account yet?',
        'register' => 'Sign up',
    ],

    'register' => [
        'title' => 'Create your account',
        'meta' => 'Sign up to ArkVel to apply for adoption, book visits and save favourites.',
        'intro' => 'It takes a minute. We will send you an email to confirm your address.',
        'email_help' => 'We will use it to sign you in and for news about your applications.',
        'privacy' => 'I have read how my data is processed to manage the account.',
        'privacy_link' => 'Read the privacy notice',
        'privacy_help' => 'We use your name, email and the information you give us only to manage your account and your requests to shelters. You can ask to delete your account at any time.',
        'submit' => 'Create account',
        'rejected' => 'We could not complete the sign-up. Wait a few seconds and try again.',
        'have_account' => 'Already have an account?',
        'login' => 'Sign in',
    ],

    'forgot' => [
        'title' => 'Forgot password',
        'meta' => 'Get an email with a link to reset the password of your ArkVel account.',
        'intro' => 'Enter your account email: we will send you a link to choose a new one.',
        'submit' => 'Send me the link',
        'sent_title' => 'Request sent',
        'sent_body' => 'If an account exists for this address, you will shortly receive an email with the link. The link is valid for :count minute. Check your spam folder too.|If an account exists for this address, you will shortly receive an email with the link. The link is valid for :count minutes. Check your spam folder too.',
        'back' => 'Back to sign in',
    ],

    'reset' => [
        'title' => 'Choose a new password',
        'meta' => 'Reset the password of your ArkVel account.',
        'intro' => 'Choose the new password for your account.',
        'submit' => 'Save password',
        'done' => 'Password updated: you can sign in now.',
        'invalid_link' => 'This link is not valid or has expired. Ask for a new one.',
        'request_new' => 'Ask for a new link',
    ],

    'verify' => [
        'title' => 'Confirm your email',
        'meta' => 'Confirm the email address of your ArkVel account.',
        'intro' => 'We sent an email to :email. Open the link inside to confirm your address and use your personal area.',
        'not_received' => 'Can’t find the email? Check your spam folder, or have it sent again.',
        'resend' => 'Send the email again',
        'resent' => 'We sent you a new confirmation email.',
        'verified' => 'Email confirmed. Welcome!',
        'wrong_account' => 'Not your address?',
        'logout' => 'Sign out',
    ],

    /*
     | Verification and reset emails (App\Notifications\Auth\AuthMailMessage), in the
     | locale stored with the notification when it is sent.
     */
    'mail' => [
        'greeting' => 'Hi :name!',
        'greeting_anonymous' => 'Hi!',
        'signature' => 'The ArkVel team',
        'fallback' => 'If the “:action” button does not work, copy this address and paste it into your browser:',

        'verify' => [
            'subject' => 'Confirm your email address',
            'intro' => 'Thanks for signing up to ArkVel. Confirm your email address to use your personal area.',
            'action' => 'Confirm email address',
            'expiry' => 'The link is valid for :count minute.|The link is valid for :count minutes.',
            'ignore' => 'If you did not create the account, ignore this email.',
        ],

        'reset' => [
            'subject' => 'Reset your password',
            'intro' => 'You are receiving this email because a password reset was requested for your ArkVel account.',
            'action' => 'Choose a new password',
            'expiry' => 'The link is valid for :count minute.|The link is valid for :count minutes.',
            'ignore' => 'If you did not ask for it, ignore this email: your current password stays valid.',
        ],
    ],
];
