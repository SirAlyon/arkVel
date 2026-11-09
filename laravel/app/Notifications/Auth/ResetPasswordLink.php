<?php

declare(strict_types=1);

namespace App\Notifications\Auth;

use App\Support\Auth\PasswordPolicy;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Mail con il link per reimpostare la password (UI-5).
 *
 * Come la mail del pannello Filament che sostituisce (anche quella estendeva la
 * ResetPassword di Laravel ed era in coda), ma con il link alla route pubblica
 * `password.reset` (/reimposta-password/{token}?email=…) e i testi di ArkVel nella lingua
 * dell'utente ({@see AuthMailMessage}). La spedisce User::sendPasswordResetNotification()
 * per le richieste da /password-dimenticata; il reset del pannello /manage manda ancora la
 * notifica di Filament (callback propria del broker, non passa da qui).
 */
final class ResetPasswordLink extends ResetPassword implements ShouldQueue
{
    use Queueable;

    /**
     * @param  mixed  $notifiable
     */
    public function toMail($notifiable): MailMessage
    {
        return AuthMailMessage::make(
            'reset',
            $notifiable,
            $this->resetUrl($notifiable),
            PasswordPolicy::resetLinkExpiryMinutes(),
        );
    }
}
