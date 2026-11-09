<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\SendEmailVerification;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Auth\PostLoginDestination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * `POST /verifica-email/reinvia` (verification.send): rimanda la mail di verifica
 * (middleware auth, throttle; CSRF del gruppo web). Modulo HTML semplice: funziona anche
 * senza JavaScript. La logica è in {@see SendEmailVerification}; l'esito torna alla pagina
 * /verifica-email come toast (anche il limite di invii raggiunto). Chi è già verificato va
 * alla sua destinazione ({@see PostLoginDestination}).
 */
class SendEmailVerificationController extends Controller
{
    public function __invoke(Request $request, SendEmailVerification $send, PostLoginDestination $destination): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $sent = $send->handle($user);
        } catch (ValidationException $exception) {
            return redirect()->route('verification.notice')
                ->with('toast', ['type' => 'danger', 'title' => $exception->validator->errors()->first('verification')]);
        }

        if (! $sent) {
            return redirect()->to($destination->for($user));
        }

        return redirect()->route('verification.notice')
            ->with('toast', ['type' => 'success', 'title' => __('auth_public.verify.resent')]);
    }
}
