<?php

declare(strict_types=1);

/*
 | ⚠️ DRAFT PENDING REVIEW — Privacy policy (GDPR art. 13).
 |
 | First-pass technical text with NO legal value until reviewed and approved by the data
 | controller (kiba.studio, Alessio Sada) and, where appropriate, by legal counsel. The
 | controller's contact details and the retention periods are PLACEHOLDERS [in brackets] and
 | must be completed before publication. The draft notice is also shown at the top of the
 | page (lang/en/legal.php → draft).
 */

return [
    'meta' => [
        'title' => 'Privacy policy — ArkVel',
        'description' => 'How ArkVel processes the personal data of adopters, organizations and visitors: data collected, purposes, legal bases, recipients, cookies, retention and rights (GDPR art. 13).',
        'breadcrumb' => 'Privacy policy',
    ],

    'eyebrow' => 'Legal',
    'title' => 'Privacy policy',
    'intro' => 'This policy explains what personal data ArkVel processes, why, for how long and which rights you can exercise, in accordance with Article 13 of Regulation (EU) 2016/679 (GDPR).',

    'controller' => [
        'title' => 'Data controller',
        'body' => 'The controller of your personal data is:',
        'name' => 'kiba.studio, Alessio Sada',
        'address_label' => 'Address',
        'address' => '[Registered address — to be added]',
        'email_label' => 'Email',
        'email' => '[Controller email — to be added]',
        'pec_label' => 'Certified email (PEC)',
        'pec' => '[Controller PEC — to be added]',
        'note' => 'The contact details above are placeholders and must be completed before publication.',
    ],

    'data' => [
        'title' => 'What data we process',
        'intro' => 'Depending on how you use ArkVel, we process the following categories of data:',
        'items' => [
            'account' => [
                'label' => 'Adopter account data',
                'text' => 'your name, email address and, if you provide them, phone number and address; your adoption questionnaire answers and the consents you give.',
            ],
            'messages' => [
                'label' => 'Messages',
                'text' => 'the content and metadata of communications between you and organizations (for example about an application or a visit).',
            ],
            'donations' => [
                'label' => 'Donations',
                'text' => 'name, email and amount of donations. Card data is processed directly by Stripe: ArkVel neither sees nor stores it.',
            ],
            'organizations' => [
                'label' => 'Organization and staff data',
                'text' => 'data about organizations (shelters, rescues, associations) and the people who manage them, needed to use the platform.',
            ],
            'technical' => [
                'label' => 'Technical data',
                'text' => 'system logs and IP address, collected automatically to run and secure the service.',
            ],
        ],
    ],

    'purposes' => [
        'title' => 'Purposes and legal bases',
        'intro' => 'We process data for the following purposes, each with its own legal basis:',
        'items' => [
            'service' => [
                'label' => 'Providing the service',
                'text' => 'managing your account, applications, messages with organizations and donations. Legal basis: performance of the contract or requested service (art. 6.1.b GDPR).',
            ],
            'consent' => [
                'label' => 'Consent-based activities',
                'text' => 'where required, for example optional communications. Legal basis: consent, which you can withdraw at any time (art. 6.1.a GDPR).',
            ],
            'legal' => [
                'label' => 'Legal obligations',
                'text' => 'accounting, tax and other statutory obligations. Legal basis: legal obligation (art. 6.1.c GDPR).',
            ],
            'security' => [
                'label' => 'Security and abuse prevention',
                'text' => 'protecting the platform and preventing fraud and misuse. Legal basis: legitimate interest (art. 6.1.f GDPR).',
            ],
        ],
    ],

    'recipients' => [
        'title' => 'Recipients and processors',
        'intro' => 'To run the service we rely on third-party providers that process data on our behalf, as processors or as independent controllers:',
        'items' => [
            'stripe' => [
                'label' => 'Stripe',
                'text' => 'payment and donation processing; card data is handled directly by Stripe.',
            ],
            'email' => [
                'label' => 'Email provider',
                'text' => 'delivery of transactional emails (address verification, password reset, notifications).',
            ],
            'hosting' => [
                'label' => 'Hosting',
                'text' => 'the infrastructure the platform runs on and where data is stored.',
            ],
            'search' => [
                'label' => 'Meilisearch',
                'text' => 'the internal search engine for the catalogue.',
            ],
            'maps' => [
                'label' => 'OpenFreeMap (tiles.openfreemap.org)',
                'text' => 'organization maps use OpenFreeMap tiles: to display them your browser contacts tiles.openfreemap.org directly, which therefore receives your IP address. The service uses no cookies or trackers.',
            ],
        ],
        'note' => 'We do not sell or share data with third parties for marketing purposes.',
    ],

    'cookies' => [
        'title' => 'Cookies and similar technologies',
        'body' => [
            'ArkVel only uses technical and session cookies that are necessary for the site to work (for example to keep you signed in and remember your chosen language).',
            'We do not use profiling cookies, analytics tools or third-party trackers. For this reason no cookie consent banner is needed: there is nothing to accept or reject.',
        ],
    ],

    'retention' => [
        'title' => 'How long we keep data',
        'body' => 'We keep data for as long as needed for the purposes described: account data while the account is active and, after deletion, for the periods required by any legal obligations (for example accounting rules for donations). Technical logs are kept for a limited period for security purposes. [Specific retention periods to be defined.]',
    ],

    'transfers' => [
        'title' => 'Transfers of data outside the European Union',
        'body' => 'Some providers (for example Stripe) may process data outside the European Union. Where this happens, the transfer takes place in compliance with the GDPR, on the basis of appropriate safeguards such as the standard contractual clauses approved by the European Commission. [Transfer details to be verified.]',
    ],

    'rights' => [
        'title' => 'Your rights',
        'intro' => 'As a data subject you can exercise the following rights at any time:',
        'items' => [
            'access' => 'access to your data;',
            'rectify' => 'rectification of inaccurate data;',
            'erase' => 'erasure (right to be forgotten);',
            'restrict' => 'restriction of processing;',
            'portability' => 'data portability;',
            'object' => 'objection to processing;',
            'complaint' => 'lodging a complaint with a supervisory authority.',
        ],
        'how' => 'To exercise these rights you can write to the controller’s contact details above. Self-service account deletion will soon be available in the adopter area.',
        'complaint_intro' => 'In Italy the supervisory authority is the',
        'garante' => 'Italian Data Protection Authority (Garante)',
        'account_link' => 'Go to the adopter area',
    ],

    'minors' => [
        'title' => 'Minors',
        'body' => 'ArkVel is not aimed at minors and does not knowingly collect their data. If you believe a minor has provided us with personal data, please contact us and we will remove it.',
    ],

    'changes' => [
        'title' => 'Changes and version',
        'body' => 'We may update this policy to reflect changes to the service or to the law. The version in force and the date of the last update are shown at the top of this page. Significant changes will be communicated through the service.',
    ],
];
