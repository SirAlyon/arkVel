<?php

declare(strict_types=1);

namespace App\Notifications\Auth;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Mail dei flussi di autenticazione pubblici (verifica dell'email, reset della password):
 * testi da `auth_public.mail.*`, nella lingua della notifica (la sceglie
 * User::send…Notification() al momento dell'invio; il NotificationSender la applica anche
 * quando la mail parte dalla coda).
 *
 * Usa il template markdown `mail.auth.action` invece di quello di Laravel, che ha saluto,
 * firma, riga di riserva del bottone e piè di pagina in inglese (chiavi JSON del framework,
 * senza un lang/it.json).
 */
final class AuthMailMessage
{
    /**
     * @param  'verify'|'reset'  $kind  gruppo di testi in auth_public.mail
     */
    public static function make(string $kind, mixed $notifiable, string $url, int $expiryMinutes): MailMessage
    {
        $name = $notifiable instanceof User ? trim((string) $notifiable->name) : '';

        return (new MailMessage)
            ->subject(__("auth_public.mail.{$kind}.subject"))
            ->greeting($name !== '' ? __('auth_public.mail.greeting', ['name' => $name]) : __('auth_public.mail.greeting_anonymous'))
            ->line(__("auth_public.mail.{$kind}.intro"))
            ->action(__("auth_public.mail.{$kind}.action"), $url)
            ->line(trans_choice("auth_public.mail.{$kind}.expiry", $expiryMinutes, ['count' => $expiryMinutes]))
            ->line(__("auth_public.mail.{$kind}.ignore"))
            ->salutation(__('auth_public.mail.signature'))
            ->markdown('mail.auth.action');
    }
}
