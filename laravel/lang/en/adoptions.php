<?php

declare(strict_types=1);

return [
    // Reason used to close sibling applications when the animal is adopted by
    // another application.
    'sibling_rejected_reason' => 'Animal adopted',

    // Notification to the adopter when the adoption is finalized.
    'completed' => [
        'subject' => 'Adoption confirmed: :animal is yours!',
        'greeting' => 'Hi :name,',
        'line' => 'Congratulations! The adoption of :animal has been finalized.',
        'contract_line' => 'Attached you will find the adoption contract in PDF format.',
        'action' => 'Go to my account',
        'salutation' => 'See you soon, the ArkVel team',
        'database' => 'Your adoption of :animal has been confirmed.',
        'contract_filename' => 'adoption-contract-:id.pdf',
    ],

    // Adoption PDF contract contents.
    'contract' => [
        'title' => 'Adoption contract',
        'reference' => 'Adoption ref. no. :id',
        'dated' => 'dated :date',
        'preamble' => 'This contract governs the adoption of the animal indicated below between the rehoming organization and the adopter.',
        'parties' => 'Parties',
        'party_organization' => 'Rehoming organization',
        'party_adopter' => 'Adopter',
        'animal' => 'Animal',
        'animal_name' => 'Name',
        'species' => 'Species',
        'breed' => 'Breed',
        'sex' => 'Sex',
        'microchip' => 'Microchip',
        'terms' => 'Terms',
        'adoption_date' => 'Adoption date',
        'fee' => 'Adoption fee',
        'fee_free' => 'Free',
        'clauses' => 'Clauses',
        'clause_care' => 'The adopter commits to providing the animal with adequate care, suitable nutrition, appropriate spaces and attention to its welfare.',
        'clause_health' => 'The adopter commits to providing the necessary veterinary care, including prophylaxis and treatments required by law.',
        'clause_no_transfer' => 'The adopter shall not transfer the animal to third parties without the prior written consent of the rehoming organization.',
        'clause_follow_up' => 'The adopter consents to possible post-adoption checks by the organization to verify the animal\'s welfare.',
        'clause_return' => 'Should the adopter become unable to keep the animal, they will contact the organization before any other solution.',
        'clause_gdpr' => 'Personal data is processed solely for the purpose of managing the adoption, in compliance with Regulation (EU) 2016/679 (GDPR).',
        'signature_organization' => 'Rehoming organization signature',
        'signature_adopter' => 'Adopter signature',
    ],

    // Finalization action in the shelter panel.
    'shelter' => [
        'finalize' => 'Finalize adoption',
        'finalize_heading' => 'Finalize the adoption',
        'finalize_help' => 'Confirm the adoption: the record will be created, the animal will move to "Adopted", the other applications will be closed and the contract will be sent to the adopter.',
        'finalize_submit' => 'Finalize',
        'fee' => 'Adoption fee',
        'fee_help' => 'Optional. Leave empty if the adoption is free.',
        'currency' => 'Currency',
        'finalized' => 'Adoption finalized',
        'finalized_body' => 'The adoption of :animal has been confirmed and the contract has been sent.',
        'finalize_failed' => 'Could not finalize the adoption',
    ],
];
