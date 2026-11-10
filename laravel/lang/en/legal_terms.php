<?php

declare(strict_types=1);

/*
 | ⚠️ DRAFT PENDING REVIEW — Terms of service.
 |
 | First-pass technical text with NO legal value until reviewed and approved by the
 | controller (kiba.studio, Alessio Sada) and, where appropriate, by legal counsel. The
 | draft notice is also shown at the top of the page (lang/en/legal.php → draft).
 */

return [
    'meta' => [
        'title' => 'Terms of service — ArkVel',
        'description' => 'The terms governing the use of ArkVel: scope of the service, accounts, conduct, content and listings, donations, liability and applicable law.',
        'breadcrumb' => 'Terms of service',
    ],

    'eyebrow' => 'Legal',
    'title' => 'Terms of service',
    'intro' => 'These terms govern your use of ArkVel. By using the platform you accept them; if you do not agree, please do not use it.',

    'object' => [
        'title' => 'Scope of the service',
        'body' => 'ArkVel is an open-source platform that connects people interested in adoption with organizations (shelters, rescues, associations) that list adoptable animals. ArkVel provides the tool: it is not a party to the adoption, fostering or donation relationship, which is directly between the user and the organization.',
    ],

    'account' => [
        'title' => 'Accounts and user responsibilities',
        'body' => [
            'To use some features you must create an account with accurate, up-to-date information. You are responsible for keeping your credentials safe and for activity carried out with your account.',
            'You must be of legal age to register. Tell us promptly if you suspect unauthorized use of your account.',
        ],
    ],

    'conduct' => [
        'title' => 'Acceptable conduct',
        'intro' => 'When using ArkVel you agree not to:',
        'items' => [
            'law' => 'break the law or infringe the rights of others;',
            'abuse' => 'post false, misleading, offensive or unlawful content;',
            'scrape' => 'collect platform data by automated means or bypass its security measures;',
            'disrupt' => 'interfere with the operation of the service or attempt to access restricted areas.',
        ],
    ],

    'content' => [
        'title' => 'Content and listings',
        'body' => 'Organizations are solely responsible for the content they publish (animal profiles, text, images, contact details) and for its accuracy and lawfulness. ArkVel does not review every piece of content in advance and may remove anything that breaches these terms or the law. Content you submit remains yours; by providing it to ArkVel you authorise its publication on the platform for the operation of the service.',
    ],

    'donations' => [
        'title' => 'Donations',
        'body' => 'Donations to organizations are voluntary and non-reciprocal: they do not entitle you to any goods or services in return. Payments are handled by Stripe, which processes card data; ArkVel neither sees nor stores it. Any refunds and the tax treatment of a donation are the responsibility of the receiving organization under applicable law.',
    ],

    'liability' => [
        'title' => 'Limitation of liability',
        'body' => 'ArkVel is an open-source project provided “as is”, without warranties of continuous availability, freedom from errors or fitness for a particular purpose. To the extent permitted by law, ArkVel is not liable for relationships between users and organizations, for the accuracy of listings, or for damages arising from the use or unavailability of the service. Mandatory consumer rights granted by law remain unaffected.',
    ],

    'law' => [
        'title' => 'Governing law and jurisdiction',
        'body' => 'These terms are governed by Italian law. Disputes fall under the jurisdiction of the courts of the controller’s registered seat, without prejudice to mandatory provisions of law, including consumer jurisdiction where applicable. [Jurisdiction to be confirmed.]',
    ],

    'changes' => [
        'title' => 'Changes',
        'body' => 'We may update these terms to reflect changes to the service or to the law. The version in force and the date of the last update are shown at the top of this page. By continuing to use ArkVel after an update you accept the amended terms.',
    ],
];
