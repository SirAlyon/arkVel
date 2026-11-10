<?php

declare(strict_types=1);

return [
    // Adopter panel: account deletion "danger zone" (GDPR art. 17, in-place
    // anonymization). Keys used by the DeleteAccount page, the
    // AnonymizeUserAccount action and the CannotAnonymizeSoleOwner exception.
    'delete' => [
        'nav' => 'Delete account',
        'title' => 'Delete my account',

        // Explanatory panel.
        'zone_heading' => 'Permanently delete your account',
        'zone_intro' => 'Deleting your account exercises your right to be forgotten: your personal data is removed or anonymized and access is blocked.',
        'zone_removed_profile' => 'Your adopter questionnaire and related data are deleted.',
        'zone_removed_favorites' => 'Your favorite animals and notifications are removed.',
        'zone_removed_login' => 'Your name and email are anonymized and you will no longer be able to sign in.',
        'zone_removed_recurring' => 'Active recurring donations are cancelled.',
        'zone_kept' => 'For legal obligations and the organization\'s history, applications, fostering, appointments, messages and donations already made are kept in anonymized form (without your personal data).',
        'zone_irreversible' => 'This action is irreversible and cannot be undone.',

        // Action and confirmation modal.
        'button' => 'Delete my account',
        'confirm_heading' => 'Confirm account deletion?',
        'confirm_body' => 'This action is irreversible. To confirm, type your email address below.',
        'confirm_submit' => 'Delete permanently',
        'confirm_email_label' => 'Confirm your email',
        'confirm_email_help' => 'Type the email address you sign in with to confirm.',
        'confirm_email_mismatch' => 'The email does not match your account.',

        // Outcome.
        'done_flash' => 'Your account has been deleted. Thank you for being part of ArkVel.',
        'sole_owner_title' => 'Cannot delete the account',
        'sole_owner_body' => 'You are the sole owner of an organization. Before deleting your account, transfer ownership to another member or close the organization.',

        // Placeholder values written in place of the PII.
        'name_placeholder' => 'Deleted account',
        'donor_placeholder' => 'Anonymous donor',

        // Domain exception message (sole-owner guard).
        'sole_owner_exception' => 'You cannot anonymize the account: you are the sole owner of organization #:organization.',
    ],
];
