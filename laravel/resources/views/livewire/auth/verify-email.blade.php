{{--
 | /verifica-email (App\Livewire\Auth\VerifyEmailPage). Reinvio e uscita sono moduli POST
 | classici (con @csrf) verso verification.send e logout: funzionano anche senza JavaScript;
 | l'esito torna come toast (flash di sessione letto dal toaster).
--}}
<x-site.auth-shell :title="__('auth_public.verify.title')" :intro="__('auth_public.verify.intro', ['email' => $email])">
    <div class="flex flex-col gap-4" data-auth-verify>
        <p class="text-fg-muted">{{ __('auth_public.verify.not_received') }}</p>
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-ui.button type="submit" icon="mail" class="w-full sm:w-auto" data-auth-resend>{{ __('auth_public.verify.resend') }}</x-ui.button>
        </form>
    </div>

    <x-slot:footer>
        <form method="POST" action="{{ route('logout') }}" class="flex flex-wrap items-center gap-x-3 gap-y-2">
            @csrf
            <span class="text-fg-muted">{{ __('auth_public.verify.wrong_account') }}</span>
            <x-ui.button type="submit" variant="secondary" size="sm" icon="log-out" data-auth-logout>{{ __('auth_public.verify.logout') }}</x-ui.button>
        </form>
    </x-slot:footer>
</x-site.auth-shell>
