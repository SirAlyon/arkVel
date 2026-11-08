<?php

declare(strict_types=1);

/*
 | Pagine di autenticazione del sito pubblico (UI-5): accesso, registrazione, password
 | dimenticata, reimpostazione e verifica dell'email. Componenti in App\Livewire\Auth,
 | logica in App\Actions\Auth, viste in resources/views/livewire/auth.
 | Non sono le traduzioni di Filament (i pannelli /manage e /admin usano le loro).
 */

return [
    'throttled' => [
        'seconds' => 'Troppi tentativi. Riprova tra :count secondo.|Troppi tentativi. Riprova tra :count secondi.',
        'minutes' => 'Troppi tentativi. Riprova tra :count minuto.|Troppi tentativi. Riprova tra :count minuti.',
    ],

    'validation' => [
        'name_required' => 'Scrivi il tuo nome.',
        'name_max' => 'Il nome può avere al massimo 255 caratteri.',
        'email_required' => 'Scrivi il tuo indirizzo email.',
        'email_invalid' => 'Scrivi un indirizzo email valido, per esempio nome@esempio.it.',
        'email_max' => 'L’indirizzo email può avere al massimo 255 caratteri.',
        'email_taken' => 'Esiste già un account con questo indirizzo email. Accedi o reimposta la password.',
        'password_required' => 'Scrivi la password.',
        'password_confirmed' => 'Le due password non coincidono.',
        'password_min' => 'La password deve avere almeno :min caratteri.',
        'password_mixed' => 'La password deve contenere almeno una lettera maiuscola e una minuscola.',
        'password_letters' => 'La password deve contenere almeno una lettera.',
        'password_numbers' => 'La password deve contenere almeno un numero.',
        'password_symbols' => 'La password deve contenere almeno un simbolo.',
        'password_uncompromised' => 'Questa password è comparsa in una fuga di dati: scegline un’altra.',
        'privacy_required' => 'Per creare l’account conferma di aver preso visione di come trattiamo i tuoi dati.',
    ],

    'fields' => [
        'name' => 'Nome e cognome',
        'email' => 'Email',
        'password' => 'Password',
        'new_password' => 'Nuova password',
        'password_confirmation' => 'Ripeti la password',
        'password_help' => 'Almeno :min caratteri.',
        'honeypot' => 'Non compilare questo campo',
    ],

    'aside' => [
        'eyebrow' => 'Il tuo account ArkVel',
        'title' => 'Un posto per seguire la tua adozione',
        'perks' => [
            'favorites' => 'Salva gli animali che ti stanno a cuore',
            'apply' => 'Invia e segui le tue candidature',
            'visits' => 'Prenota le visite e scrivi agli enti',
        ],
    ],

    'login' => [
        'title' => 'Accedi',
        'meta' => 'Accedi al tuo account ArkVel per seguire candidature, visite e preferiti.',
        'intro' => 'Bentornato! Inserisci email e password.',
        'remember' => 'Ricordami su questo dispositivo',
        'forgot' => 'Hai dimenticato la password?',
        'submit' => 'Accedi',
        'failed' => 'Email o password non corretti.',
        'no_account' => 'Non hai un account?',
        'register' => 'Registrati',
    ],

    'register' => [
        'title' => 'Crea il tuo account',
        'meta' => 'Registrati su ArkVel per candidarti all’adozione, prenotare visite e salvare i preferiti.',
        'intro' => 'Ci vuole un minuto. Ti manderemo una mail per confermare l’indirizzo.',
        'email_help' => 'Lo useremo per l’accesso e per le notizie sulle tue candidature.',
        'privacy' => 'Ho preso visione di come vengono trattati i miei dati per gestire l’account.',
        'privacy_link' => 'Leggi l’informativa sulla privacy',
        'privacy_help' => 'Usiamo nome, email e le informazioni che ci darai solo per gestire il tuo account e le tue richieste agli enti. Puoi chiedere in ogni momento di cancellare l’account.',
        'submit' => 'Crea l’account',
        'rejected' => 'Non siamo riusciti a completare la registrazione. Attendi qualche secondo e riprova.',
        'have_account' => 'Hai già un account?',
        'login' => 'Accedi',
    ],

    'forgot' => [
        'title' => 'Password dimenticata',
        'meta' => 'Ricevi via email un link per reimpostare la password del tuo account ArkVel.',
        'intro' => 'Scrivi l’email del tuo account: ti manderemo un link per sceglierne una nuova.',
        'submit' => 'Inviami il link',
        'sent_title' => 'Richiesta inviata',
        'sent_body' => 'Se esiste un account con questo indirizzo, a breve riceverai una mail con il link. Il link vale :count minuto. Controlla anche lo spam.|Se esiste un account con questo indirizzo, a breve riceverai una mail con il link. Il link vale :count minuti. Controlla anche lo spam.',
        'back' => 'Torna all’accesso',
    ],

    'reset' => [
        'title' => 'Scegli una nuova password',
        'meta' => 'Reimposta la password del tuo account ArkVel.',
        'intro' => 'Scegli la nuova password per il tuo account.',
        'submit' => 'Salva password',
        'done' => 'Password aggiornata: ora puoi accedere.',
        'invalid_link' => 'Il link non è valido o è scaduto. Chiedine uno nuovo.',
        'request_new' => 'Chiedi un nuovo link',
    ],

    'verify' => [
        'title' => 'Conferma la tua email',
        'meta' => 'Conferma l’indirizzo email del tuo account ArkVel.',
        'intro' => 'Ti abbiamo mandato una mail a :email. Apri il link che contiene per confermare l’indirizzo e usare la tua area personale.',
        'not_received' => 'Non trovi la mail? Controlla lo spam, oppure fattela rimandare.',
        'resend' => 'Rimanda la mail',
        'resent' => 'Ti abbiamo mandato una nuova mail di conferma.',
        'verified' => 'Email confermata. Benvenuto!',
        'wrong_account' => 'Non è il tuo indirizzo?',
        'logout' => 'Esci',
    ],

    /*
     | Mail di verifica e di reset (App\Notifications\Auth\AuthMailMessage), nella lingua
     | salvata con la notifica al momento dell'invio.
     */
    'mail' => [
        'greeting' => 'Ciao :name!',
        'greeting_anonymous' => 'Ciao!',
        'signature' => 'Il team di ArkVel',
        'fallback' => 'Se il pulsante «:action» non funziona, copia questo indirizzo e incollalo nel browser:',

        'verify' => [
            'subject' => 'Conferma il tuo indirizzo email',
            'intro' => 'Grazie per esserti registrato su ArkVel. Conferma il tuo indirizzo email per usare la tua area personale.',
            'action' => 'Conferma l’indirizzo email',
            'expiry' => 'Il link vale :count minuto.|Il link vale :count minuti.',
            'ignore' => 'Se non hai creato tu l’account, ignora questa mail.',
        ],

        'reset' => [
            'subject' => 'Reimposta la password',
            'intro' => 'Ricevi questa mail perché è stato chiesto di reimpostare la password del tuo account ArkVel.',
            'action' => 'Scegli una nuova password',
            'expiry' => 'Il link vale :count minuto.|Il link vale :count minuti.',
            'ignore' => 'Se non l’hai chiesto tu, ignora questa mail: la password attuale resta valida.',
        ],
    ],
];
