<?php

declare(strict_types=1);

/*
 | Testi della scheda pubblica di un animale (UI-6c, App\Livewire\AnimalDetail,
 | livewire/animal-detail.blade.php e livewire/animal-detail/*). I testi delle singole
 | CTA (candidatura, affido, prenotazione, contatto) restano nei loro file di dominio
 | (applications, fostering, appointments, messaging).
 */

return [
    'age' => [
        'months' => '{1}:count mese|[2,*]:count mesi',
        'years' => '{1}:count anno|[2,*]:count anni',
    ],

    'header' => [
        'traits' => 'Caratteristiche di :name',
        'location' => 'Dove si trova:',
        'organization' => 'Ente:',
        'size' => 'Taglia :size',
    ],

    'about' => [
        'title' => 'Chi è :name',
        'empty' => 'L\'ente non ha ancora scritto una presentazione di :name: puoi chiedere di più con un messaggio.',
    ],

    'facts' => [
        'title' => 'Da sapere su :name',
        'tabs' => 'Informazioni su :name',
        'tab' => [
            'profile' => 'Identikit',
            'character' => 'Carattere',
            'compatibility' => 'Compatibilità',
            'health' => 'Salute',
        ],
        'species' => 'Specie',
        'breed' => 'Razza',
        'sex' => 'Sesso',
        'age' => 'Età',
        'size' => 'Taglia',
        'temperament' => 'Temperamento',
        'energy' => 'Energia',
        'kids' => 'Con i bambini',
        'dogs' => 'Con i cani',
        'cats' => 'Con i gatti',
        'compatible' => [
            'yes' => 'Va d\'accordo',
            'no' => 'Meglio di no',
            'unknown' => 'Da valutare',
        ],
        'vaccinated' => 'Vaccinazioni',
        'sterilized' => 'Sterilizzazione',
        'dewormed' => 'Sverminatura',
        'done' => 'Fatta',
        'not_done' => 'Non ancora',
        'not_specified' => 'Non indicato',
        'health_note' => 'Per la storia sanitaria completa chiedi all\'ente: la condivide con chi si candida.',
        'compatibility_note' => '«Da valutare» vuol dire che l\'ente non l\'ha ancora visto alla prova: chiedi pure.',
    ],

    'organization' => [
        'title' => 'Chi si prende cura di :name',
    ],

    'actions' => [
        'meet_title' => 'Prima di candidarti',
        'meet_body' => 'Conosci :name di persona o fai una domanda all\'ente.',
        'book' => 'Prenota una visita',
        'contact' => 'Scrivi all\'ente',
        'bar_open' => 'Adotta o incontra',
        'sheet_title' => 'Adotta o incontra :name',
        'sheet_description' => 'Candidati, offriti per l\'affido, prenota una visita o scrivi all\'ente.',
    ],

    'booking' => [
        'when' => 'Data e ora',
        'when_help' => 'Scegli il giorno e l\'orario che preferisci: l\'ente ti confermerà l\'appuntamento.',
        'date' => 'Giorno della visita',
        'time' => 'Orario della visita',
        'guest_note' => 'Per prenotare una visita serve un account: dopo l\'accesso torni qui.',
    ],

    'contact' => [
        'guest_note' => 'Per scrivere all\'ente serve un account: dopo l\'accesso torni qui.',
    ],

    'guest' => [
        'login' => 'Accedi',
    ],

    'similar' => [
        'title' => 'Potrebbero piacerti anche',
        'description' => 'Altri animali della stessa specie o dello stesso ente.',
        'browse' => 'Sfoglia il catalogo',
    ],
];
