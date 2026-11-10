<?php

declare(strict_types=1);

/*
 | ⚠️ BOZZA DA VALIDARE — Termini di servizio.
 |
 | Testo tecnico di prima stesura, SENZA valore legale finché non è rivisto e approvato dal
 | titolare (kiba.studio di Alessio Sada) ed eventualmente da un legale. L'avviso di bozza è
 | anche visibile in cima alla pagina (lang/it/legal.php → draft).
 */

return [
    'meta' => [
        'title' => 'Termini di servizio — ArkVel',
        'description' => 'I termini che regolano l’uso di ArkVel: oggetto del servizio, account, condotta, contenuti e annunci, donazioni, responsabilità e legge applicabile.',
        'breadcrumb' => 'Termini di servizio',
    ],

    'eyebrow' => 'Note legali',
    'title' => 'Termini di servizio',
    'intro' => 'Questi termini regolano l’uso di ArkVel. Usando la piattaforma accetti quanto segue; se non sei d’accordo, ti preghiamo di non utilizzarla.',

    'object' => [
        'title' => 'Oggetto del servizio',
        'body' => 'ArkVel è una piattaforma open source che mette in contatto le persone interessate all’adozione con gli enti (canili, rifugi, associazioni) che pubblicano gli animali adottabili. ArkVel fornisce lo strumento: non è parte del rapporto di adozione, affido o donazione, che intercorre direttamente tra l’utente e l’ente.',
    ],

    'account' => [
        'title' => 'Account e responsabilità dell’utente',
        'body' => [
            'Per usare alcune funzioni devi creare un account fornendo dati veritieri e aggiornati. Sei responsabile della custodia delle tue credenziali e delle attività svolte con il tuo account.',
            'Devi avere la maggiore età per registrarti. Avvisaci tempestivamente se sospetti un uso non autorizzato del tuo account.',
        ],
    ],

    'conduct' => [
        'title' => 'Condotta ammessa',
        'intro' => 'Usando ArkVel ti impegni a non:',
        'items' => [
            'law' => 'violare la legge o i diritti di terzi;',
            'abuse' => 'inserire contenuti falsi, ingannevoli, offensivi o illeciti;',
            'scrape' => 'raccogliere in modo automatizzato i dati della piattaforma o aggirarne le misure di sicurezza;',
            'disrupt' => 'interferire con il funzionamento del servizio o tentare di accedere ad aree riservate.',
        ],
    ],

    'content' => [
        'title' => 'Contenuti e annunci',
        'body' => 'Gli enti sono gli unici responsabili dei contenuti che pubblicano (schede degli animali, testi, immagini, recapiti) e della loro esattezza e liceità. ArkVel non verifica preventivamente ogni contenuto e può rimuovere ciò che risulta contrario a questi termini o alla legge. I contenuti che inserisci restano tuoi; concedendoli ad ArkVel autorizzi la loro pubblicazione sulla piattaforma per il funzionamento del servizio.',
    ],

    'donations' => [
        'title' => 'Donazioni',
        'body' => 'Le donazioni agli enti sono volontarie e a fondo perduto: non danno diritto a un bene o servizio in cambio. I pagamenti sono gestiti da Stripe, che tratta i dati della carta; ArkVel non li vede né li conserva. Eventuali rimborsi e la gestione fiscale della donazione competono all’ente beneficiario secondo la normativa applicabile.',
    ],

    'liability' => [
        'title' => 'Limitazione di responsabilità',
        'body' => 'ArkVel è un progetto open source fornito “così com’è”, senza garanzie di disponibilità continua, assenza di errori o idoneità a uno scopo specifico. Nei limiti consentiti dalla legge, ArkVel non risponde dei rapporti tra utenti ed enti, della veridicità degli annunci né dei danni derivanti dall’uso o dal mancato funzionamento del servizio. Restano impregiudicati i diritti inderogabili riconosciuti ai consumatori dalla legge.',
    ],

    'law' => [
        'title' => 'Legge applicabile e foro competente',
        'body' => 'Questi termini sono regolati dalla legge italiana. Per le controversie è competente il foro del luogo in cui ha sede il titolare, fatte salve le disposizioni inderogabili di legge, incluso il foro del consumatore quando applicabile. [Foro competente da confermare.]',
    ],

    'changes' => [
        'title' => 'Modifiche',
        'body' => 'Possiamo aggiornare questi termini per adeguarli a cambiamenti del servizio o della normativa. La versione in vigore e la data dell’ultimo aggiornamento sono indicate in cima a questa pagina. Continuando a usare ArkVel dopo un aggiornamento accetti i termini modificati.',
    ],
];
