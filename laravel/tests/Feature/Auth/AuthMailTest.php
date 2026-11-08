<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\Auth\ResetPasswordLink;
use App\Notifications\Auth\VerifyEmailAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\SentMessage;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Mime\Email;

uses(RefreshDatabase::class);

/*
 | Mail di verifica e di reset del sito pubblico (App\Notifications\Auth): testi di
 | auth_public.mail.* nella lingua in cui l'utente stava usando il sito al momento della
 | richiesta, anche se la mail parte dopo, dalla coda, con un'altra lingua attiva.
 | Spedite davvero sul mailer `array` (phpunit.xml) per controllare oggetto e corpo.
 */

/** L'ultima mail spedita dal mailer `array`. */
function lastAuthMail(): Email
{
    /** @var SentMessage $sent */
    $sent = app('mail.manager')->mailer()->getSymfonyTransport()->messages()->last();

    /** @var Email $email */
    $email = $sent->getOriginalMessage();

    return $email;
}

/**
 * Esegue in lingua $sentIn l'azione che manda la mail, poi la spedisce dalla coda con
 * un'altra lingua attiva ($queueLocale), come farebbe il worker.
 */
function sendThroughQueue(string $sentIn, string $queueLocale, Closure $send): Email
{
    Queue::fake();
    app()->setLocale($sentIn);

    $send();

    $jobs = Queue::pushed(SendQueuedNotifications::class);
    expect($jobs)->toHaveCount(1);

    app()->setLocale($queueLocale);
    app()->call([$jobs->first(), 'handle']);

    return lastAuthMail();
}

it('sends the verification mail in Italian', function (): void {
    $user = User::factory()->unverified()->create(['name' => 'Giulia']);

    $mail = sendThroughQueue('it', 'en', fn () => $user->sendEmailVerificationNotification());
    $html = (string) $mail->getHtmlBody();

    expect($mail->getSubject())->toBe('Conferma il tuo indirizzo email')
        ->and($html)->toContain('Ciao Giulia!')
        ->toContain('Grazie per esserti registrato su ArkVel.')
        ->toContain('Conferma l’indirizzo email')
        ->toContain('Il link vale 60 minuti.')
        ->toContain('Il team di ArkVel')
        ->toContain('Se il pulsante «Conferma l’indirizzo email» non funziona')
        ->toContain(url('/verifica-email/'.$user->getKey().'/'))
        ->toContain('lang="it"')
        ->not->toContain('Regards')
        ->not->toContain('Hello!')
        ->not->toContain('having trouble clicking');
});

it('sends the verification mail in English', function (): void {
    $user = User::factory()->unverified()->create(['name' => 'Giulia']);

    $mail = sendThroughQueue('en', 'it', fn () => $user->sendEmailVerificationNotification());
    $html = (string) $mail->getHtmlBody();

    expect($mail->getSubject())->toBe('Confirm your email address')
        ->and($html)->toContain('Hi Giulia!')
        ->toContain('Confirm email address')
        ->toContain('The link is valid for 60 minutes.')
        ->toContain('The ArkVel team')
        ->toContain('If the “Confirm email address” button does not work')
        ->toContain('lang="en"')
        ->not->toContain('Il team di ArkVel');
});

it('sends the reset mail in Italian, with the expiry from the config', function (): void {
    config(['auth.passwords.users.expire' => 30]);
    $user = User::factory()->create(['name' => 'Mario']);

    $mail = sendThroughQueue('it', 'en', fn () => Password::broker()->sendResetLink(['email' => $user->email]));
    $html = (string) $mail->getHtmlBody();

    expect($mail->getSubject())->toBe('Reimposta la password')
        ->and($html)->toContain('Ciao Mario!')
        ->toContain('Scegli una nuova password')
        ->toContain('Il link vale 30 minuti.')
        ->toContain('Se non l’hai chiesto tu, ignora questa mail')
        ->toContain('Il team di ArkVel')
        ->toContain(url('/reimposta-password/'))
        ->not->toContain('Reset Password');
});

it('sends the reset mail in English', function (): void {
    $user = User::factory()->create(['name' => 'Mario']);

    $mail = sendThroughQueue('en', 'it', fn () => Password::broker()->sendResetLink(['email' => $user->email]));
    $html = (string) $mail->getHtmlBody();

    expect($mail->getSubject())->toBe('Reset your password')
        ->and($html)->toContain('Hi Mario!')
        ->toContain('Choose a new password')
        ->toContain('The link is valid for 60 minutes.')
        ->toContain('The ArkVel team')
        ->toContain('If the “Choose a new password” button does not work');
});

it('greets without a name when the account has none', function (): void {
    $user = User::factory()->unverified()->create(['name' => '  ']);

    expect((new VerifyEmailAddress)->toMail($user)->greeting)->toBe('Ciao!')
        ->and((new ResetPasswordLink('token'))->toMail($user)->greeting)->toBe('Ciao!');
});
