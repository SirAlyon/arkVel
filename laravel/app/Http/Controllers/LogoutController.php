<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Auth\LogoutUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * `POST /esci` (logout): uscita dalla shell del sito pubblico, poi la home.
 * La logica è in {@see LogoutUser}.
 */
class LogoutController extends Controller
{
    public function __invoke(Request $request, LogoutUser $logout): RedirectResponse
    {
        $logout->handle($request->session());

        return redirect()->route('home');
    }
}
