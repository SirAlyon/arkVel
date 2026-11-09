<?php

declare(strict_types=1);

namespace App\Notifications\Auth;

use App\Support\Auth\PasswordPolicy;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Mail di verifica dell'indirizzo dell'adottante (UI-5).
 *
 * Come la mail del pannello Filament che sostituisce (anche quella estendeva la
 * VerifyEmail di Laravel ed era in coda), ma con il link alla route pubblica firmata
 * `verification.verify` (/verifica-email/{id}/{hash}) e i testi di ArkVel nella lingua
 * dell'utente ({@see AuthMailMessage}). La spedisce User::sendEmailVerificationNotification().
 */
final class VerifyEmailAddress extends VerifyEmail implements ShouldQueue
{
    use Queueable;

    /**
     * @param  mixed  $notifiable
     */
    public function toMail($notifiable): MailMessage
    {
        return AuthMailMessage::make(
            'verify',
            $notifiable,
            $this->verificationUrl($notifiable),
            PasswordPolicy::verificationLinkExpiryMinutes(),
        );
    }
}
