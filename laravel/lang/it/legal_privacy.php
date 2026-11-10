<?php

declare(strict_types=1);

/*
 | ⚠️ BOZZA DA VALIDARE — Informativa sulla privacy (GDPR art. 13).
 |
 | Testo tecnico di prima stesura, SENZA valore legale finché non è rivisto e approvato dal
 | titolare del trattamento (kiba.studio di Alessio Sada) ed eventualmente da un legale.
 | I recapiti del titolare e i tempi di conservazione sono SEGNAPOSTO [tra parentesi quadre]
 | e vanno completati prima della pubblicazione. L'avviso di bozza è anche visibile in cima
 | alla pagina (lang/it/legal.php → draft).
 */

return [
    'meta' => [
        'title' => 'Informativa sulla privacy — ArkVel',
        'description' => 'Come ArkVel tratta i dati personali di adottanti, enti e visitatori: dati raccolti, finalità, basi giuridiche, destinatari, cookie, conservazione e diritti (GDPR art. 13).',
        'breadcrumb' => 'Informativa privacy',
    ],

    'eyebrow' => 'Note legali',
    'title' => 'Informativa sulla privacy',
    'intro' => 'Questa informativa spiega quali dati personali ArkVel tratta, perché, per quanto tempo e quali diritti puoi esercitare, ai sensi dell’art. 13 del Regolamento (UE) 2016/679 (GDPR).',

    'controller' => [
        'title' => 'Titolare del trattamento',
        'body' => 'Il titolare del trattamento dei dati personali è:',
        'name' => 'kiba.studio di Alessio Sada',
        'address_label' => 'Sede',
        'address' => '[Indirizzo della sede — da inserire]',
        'email_label' => 'Email',
        'email' => '[Email del titolare — da inserire]',
        'pec_label' => 'PEC',
        'pec' => '[PEC del titolare — da inserire]',
        'note' => 'I recapiti qui sopra sono segnaposto e vanno completati prima della pubblicazione.',
    ],

    'data' => [
        'title' => 'Quali dati trattiamo',
        'intro' => 'A seconda di come usi ArkVel, trattiamo le seguenti categorie di dati:',
        'items' => [
            'account' => [
                'label' => 'Dati dell’account adottante',
                'text' => 'nome, indirizzo email e, se li fornisci, numero di telefono e indirizzo; le risposte al questionario di adozione e i consensi che esprimi.',
            ],
            'messages' => [
                'label' => 'Messaggi',
                'text' => 'contenuto e metadati delle comunicazioni tra te e gli enti (ad esempio per una candidatura o una visita).',
            ],
            'donations' => [
                'label' => 'Donazioni',
                'text' => 'nome, email e importo delle donazioni. I dati della carta di pagamento sono trattati direttamente da Stripe: ArkVel non li vede né li conserva.',
            ],
            'organizations' => [
                'label' => 'Dati di enti e staff',
                'text' => 'i dati degli enti (canili, rifugi, associazioni) e delle persone che li gestiscono, necessari a usare la piattaforma.',
            ],
            'technical' => [
                'label' => 'Dati tecnici',
                'text' => 'log di sistema e indirizzo IP, raccolti automaticamente per il funzionamento e la sicurezza del servizio.',
            ],
        ],
    ],

    'purposes' => [
        'title' => 'Finalità e basi giuridiche',
        'intro' => 'Trattiamo i dati per le finalità seguenti, ciascuna con la propria base giuridica:',
        'items' => [
            'service' => [
                'label' => 'Fornire il servizio',
                'text' => 'gestire il tuo account, le candidature, i messaggi con gli enti e le donazioni. Base giuridica: esecuzione del contratto o del servizio richiesto (art. 6.1.b GDPR).',
            ],
            'consent' => [
                'label' => 'Attività soggette a consenso',
                'text' => 'ove richiesto, ad esempio comunicazioni facoltative. Base giuridica: consenso, revocabile in ogni momento (art. 6.1.a GDPR).',
            ],
            'legal' => [
                'label' => 'Obblighi di legge',
                'text' => 'adempimenti contabili, fiscali e di legge. Base giuridica: obbligo legale (art. 6.1.c GDPR).',
            ],
            'security' => [
                'label' => 'Sicurezza e prevenzione degli abusi',
                'text' => 'protezione della piattaforma, prevenzione di frodi e usi illeciti. Base giuridica: legittimo interesse (art. 6.1.f GDPR).',
            ],
        ],
    ],

    'recipients' => [
        'title' => 'Destinatari e responsabili del trattamento',
        'intro' => 'Per erogare il servizio ci avvaliamo di fornitori terzi che trattano i dati per nostro conto, come responsabili del trattamento o come titolari autonomi:',
        'items' => [
            'stripe' => [
                'label' => 'Stripe',
                'text' => 'gestione dei pagamenti e delle donazioni; i dati della carta sono trattati direttamente da Stripe.',
            ],
            'email' => [
                'label' => 'Provider di posta elettronica',
                'text' => 'invio delle email transazionali (verifica dell’indirizzo, reimpostazione della password, notifiche).',
            ],
            'hosting' => [
                'label' => 'Hosting',
                'text' => 'infrastruttura su cui gira la piattaforma e sono archiviati i dati.',
            ],
            'search' => [
                'label' => 'Meilisearch',
                'text' => 'motore di ricerca interno del catalogo.',
            ],
            'maps' => [
                'label' => 'OpenFreeMap (tiles.openfreemap.org)',
                'text' => 'le mappe degli enti usano le tile di OpenFreeMap: per mostrarle il tuo browser contatta direttamente tiles.openfreemap.org, che riceve così il tuo indirizzo IP. Il servizio non usa cookie né tracker.',
            ],
        ],
        'note' => 'I dati non sono ceduti a terzi per finalità di marketing.',
    ],

    'cookies' => [
        'title' => 'Cookie e tecnologie simili',
        'body' => [
            'ArkVel usa solo cookie tecnici e di sessione, necessari al funzionamento del sito (ad esempio per mantenere l’accesso e la lingua scelta).',
            'Non usiamo cookie di profilazione, strumenti di analytics o tracker di terze parti. Per questo non è necessario alcun banner di consenso ai cookie: non c’è nulla da accettare o rifiutare.',
        ],
    ],

    'retention' => [
        'title' => 'Per quanto tempo conserviamo i dati',
        'body' => 'Conserviamo i dati per il tempo necessario alle finalità descritte: i dati dell’account finché l’account è attivo e, dopo la cancellazione, per i tempi richiesti da eventuali obblighi di legge (ad esempio quelli contabili per le donazioni). I log tecnici sono conservati per un periodo limitato, a fini di sicurezza. [Tempi di conservazione specifici da definire.]',
    ],

    'transfers' => [
        'title' => 'Trasferimenti di dati fuori dall’Unione Europea',
        'body' => 'Alcuni fornitori (ad esempio Stripe) possono trattare dati al di fuori dell’Unione Europea. In tal caso il trasferimento avviene nel rispetto del GDPR, sulla base di garanzie adeguate come le clausole contrattuali standard approvate dalla Commissione Europea. [Dettagli dei trasferimenti da verificare.]',
    ],

    'rights' => [
        'title' => 'I tuoi diritti',
        'intro' => 'In qualità di interessato puoi esercitare in ogni momento i seguenti diritti:',
        'items' => [
            'access' => 'accesso ai tuoi dati;',
            'rectify' => 'rettifica dei dati inesatti;',
            'erase' => 'cancellazione (diritto all’oblio);',
            'restrict' => 'limitazione del trattamento;',
            'portability' => 'portabilità dei dati;',
            'object' => 'opposizione al trattamento;',
            'complaint' => 'reclamo all’autorità di controllo.',
        ],
        'how' => 'Per esercitare questi diritti puoi scrivere ai recapiti del titolare indicati sopra. La cancellazione dell’account in autonomia sarà presto disponibile nell’area adottante.',
        'complaint_intro' => 'In Italia l’autorità di controllo è il',
        'garante' => 'Garante per la protezione dei dati personali',
        'account_link' => 'Vai all’area adottante',
    ],

    'minors' => [
        'title' => 'Minori',
        'body' => 'ArkVel non è rivolto ai minori di età e non raccoglie consapevolmente i loro dati. Se ritieni che un minore ci abbia fornito dati personali, contattaci e provvederemo a rimuoverli.',
    ],

    'changes' => [
        'title' => 'Modifiche e versione',
        'body' => 'Possiamo aggiornare questa informativa per adeguarla a cambiamenti del servizio o della normativa. La versione in vigore e la data dell’ultimo aggiornamento sono indicate in cima a questa pagina. Le modifiche sostanziali saranno comunicate attraverso il servizio.',
    ],
];
