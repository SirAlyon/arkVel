<?php

declare(strict_types=1);

return [
    // Motivazione con cui vengono chiuse le candidature sorelle quando l'animale
    // viene adottato da un'altra candidatura.
    'sibling_rejected_reason' => 'Animale adottato',

    // Notifica all'adottante quando l'adozione è finalizzata.
    'completed' => [
        'subject' => 'Adozione confermata: :animal è tuo!',
        'greeting' => 'Ciao :name,',
        'line' => 'Congratulazioni! L\'adozione di :animal è stata finalizzata.',
        'contract_line' => 'In allegato trovi il contratto di adozione in formato PDF.',
        'action' => 'Vai al mio account',
        'salutation' => 'A presto, il team di ArkVel',
        'database' => 'La tua adozione di :animal è stata confermata.',
        'contract_filename' => 'contratto-adozione-:id.pdf',
    ],

    // Contenuti del contratto PDF di adozione.
    'contract' => [
        'title' => 'Contratto di adozione',
        'reference' => 'Rif. adozione n. :id',
        'dated' => 'del :date',
        'preamble' => 'Il presente contratto disciplina l\'adozione dell\'animale di seguito indicato tra l\'ente affidatario e l\'adottante.',
        'parties' => 'Parti',
        'party_organization' => 'Ente affidatario',
        'party_adopter' => 'Adottante',
        'animal' => 'Animale',
        'animal_name' => 'Nome',
        'species' => 'Specie',
        'breed' => 'Razza',
        'sex' => 'Sesso',
        'microchip' => 'Microchip',
        'terms' => 'Condizioni',
        'adoption_date' => 'Data di adozione',
        'fee' => 'Contributo di adozione',
        'fee_free' => 'Gratuito',
        'clauses' => 'Clausole',
        'clause_care' => 'L\'adottante si impegna a garantire all\'animale cure adeguate, alimentazione idonea, spazi consoni e attenzione al suo benessere.',
        'clause_health' => 'L\'adottante si impegna a garantire le cure veterinarie necessarie, comprese profilassi e trattamenti obbligatori per legge.',
        'clause_no_transfer' => 'L\'adottante non cederà l\'animale a terzi senza previo consenso scritto dell\'ente affidatario.',
        'clause_follow_up' => 'L\'adottante acconsente a eventuali controlli post-adozione da parte dell\'ente per verificare il benessere dell\'animale.',
        'clause_return' => 'In caso di impossibilità sopravvenuta a mantenere l\'animale, l\'adottante contatterà l\'ente prima di qualsiasi altra soluzione.',
        'clause_gdpr' => 'I dati personali sono trattati ai soli fini della gestione dell\'adozione, nel rispetto del Regolamento (UE) 2016/679 (GDPR).',
        'signature_organization' => 'Firma dell\'ente affidatario',
        'signature_adopter' => 'Firma dell\'adottante',
    ],

    // Azione di finalizzazione nel pannello shelter.
    'shelter' => [
        'finalize' => 'Finalizza adozione',
        'finalize_heading' => 'Finalizza l\'adozione',
        'finalize_help' => 'Conferma l\'adozione: verrà creato il record, l\'animale passerà ad "Adottato", le altre candidature verranno chiuse e all\'adottante sarà inviato il contratto.',
        'finalize_submit' => 'Finalizza',
        'fee' => 'Contributo di adozione',
        'fee_help' => 'Facoltativo. Lascia vuoto se l\'adozione è gratuita.',
        'currency' => 'Valuta',
        'finalized' => 'Adozione finalizzata',
        'finalized_body' => 'L\'adozione di :animal è stata confermata e il contratto è stato inviato.',
        'finalize_failed' => 'Impossibile finalizzare l\'adozione',
    ],
];
