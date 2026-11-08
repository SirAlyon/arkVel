{{--
 | /password-dimenticata (App\Livewire\Auth\ForgotPasswordPage). Dopo l'invio la conferma è
 | la stessa per qualunque email (nessuna rivelazione degli account). Il blocco di conferma
 | prende il focus (tabindex -1) e, come gruppo con titolo e descrizione collegati, viene
 | letto per intero dallo screen reader; il focus non resta sul body.
--}}
<x-site.auth-shell :title="__('auth_public.forgot.title')" :intro="$sent ? null : __('auth_public.forgot.intro')">
    @if ($sent)
        <x-ui.empty-state
            id="forgot-sent"
            icon="mail-check"
            block="mint"
            :framed="false"
            :title="__('auth_public.forgot.sent_title')"
            :description="trans_choice('auth_public.forgot.sent_body', $linkExpiryMinutes, ['count' => $linkExpiryMinutes])"
            role="group"
            aria-labelledby="forgot-sent-title"
            aria-describedby="forgot-sent-description"
            tabindex="-1"
            x-data
            x-init="$nextTick(() => $el.focus())"
            class="rounded-card"
            data-auth-sent
        />
    @else
        <form wire:submit="sendLink" novalidate class="flex flex-col gap-6" data-auth-form="forgot">
            <x-ui.error-summary :errors="$errors" :fields="['email']" wire:key="forgot-errors-{{ $submissions }}" />

            <x-ui.field name="email" :label="__('auth_public.fields.email')" required>
                <x-ui.input type="email" icon="mail" wire:model="email" autocomplete="email" inputmode="email" spellcheck="false" />
            </x-ui.field>

            <x-ui.button type="submit" size="lg" icon-trailing="send" class="w-full sm:w-auto sm:self-start" data-auth-submit>{{ __('auth_public.forgot.submit') }}</x-ui.button>
        </form>
    @endif

    <x-slot:footer>
        <p><x-ui.link :href="route('login')" wire:navigate.hover data-auth-link="login">{{ __('auth_public.forgot.back') }}</x-ui.link></p>
    </x-slot:footer>
</x-site.auth-shell>
