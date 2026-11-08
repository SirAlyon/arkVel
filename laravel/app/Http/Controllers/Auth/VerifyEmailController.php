<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\VerifyUserEmail;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Auth\IntendedUrl;
use App\Support\Auth\PostLoginDestination;
use App\Support\Auth\SuccessNotice;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

/**
 * `GET /verifica-email/{id}/{hash}` (verification.verify): link firmato della mail di
 * verifica (middleware auth, signed, throttle).
 *
 * EmailVerificationRequest autorizza solo l'utente autenticato a cui il link appartiene
 * (id e hash dell'email), altrimenti 403. La logica è in {@see VerifyUserEmail}; poi
 * l'utente prosegue verso l'intended interno o la sua destinazione
 * ({@see PostLoginDestination}), con la conferma nel sistema di notifiche della pagina di
 * arrivo ({@see SuccessNotice}: notifica Filament nei pannelli, toast sul sito).
 */
class VerifyEmailController extends Controller
{
    public function __invoke(
        EmailVerificationRequest $request,
        VerifyUserEmail $verify,
        IntendedUrl $intendedUrl,
        PostLoginDestination $postLoginDestination,
        SuccessNotice $notice,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();

        $verify->handle($user);

        $destination = $intendedUrl->pull($request->session(), $postLoginDestination->for($user));

        $notice->flash($request->session(), $destination, __('auth_public.verify.verified'));

        return redirect()->to($destination);
    }
}
