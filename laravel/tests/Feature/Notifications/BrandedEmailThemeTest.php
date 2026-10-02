<?php

declare(strict_types=1);

use Illuminate\Notifications\Messages\MailMessage;

// Prova che il tema markdown GLOBALE 'arkvel' è in effetto: ogni MailMessage
// markdown (quindi tutte le notifiche del progetto) è brandizzata ArkVel senza
// toccare le singole notifiche. Rendiamo una MailMessage generica attraverso lo
// stesso renderer usato dal canale mail.

it('wires the arkvel markdown theme as the global default in config', function () {
    expect(config('mail.markdown.theme'))->toBe('arkvel')
        ->and(config('mail.markdown.paths'))->toContain(resource_path('views/vendor/mail'));
});

it('renders any MailMessage with the ArkVel wordmark, brand color and footer tagline', function () {
    $html = (string) (new MailMessage)
        ->greeting('Ciao Mario')
        ->line('Contenuto di prova.')
        ->action('Apri', 'https://arkvel.test/account')
        ->salutation('A presto, il team di ArkVel')
        ->render();

    expect($html)
        // Wordmark testuale nell'header (nessun logo image): compare almeno nel
        // link dell'header.
        ->toContain('ArkVel')
        // Il CSS del tema è inlineato: il terracotta ArkVel prova che è 'arkvel'
        // (il default Laravel userebbe #18181b per i bottoni/link primari).
        ->toContain('c5522a')
        // Footer brandizzato con la tagline localizzata.
        ->toContain((string) __('notifications_center.email_footer_tagline'));
});

it('brands primary action buttons with the terracotta color, not the Laravel default', function () {
    $html = (string) (new MailMessage)
        ->line('Con un bottone.')
        ->action('Vai', 'https://arkvel.test')
        ->render();

    expect($html)
        ->toContain('c5522a')
        // Il nero di default di Laravel per i bottoni primari NON deve comparire
        // come colore di sfondo del bottone: prova che il tema è sostituito.
        ->not->toContain('background-color: #18181b');
});
