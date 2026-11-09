{{--
 | /registrati (App\Livewire\Auth\RegisterPage). Il campo esca del honeypot
 | (App\Support\Auth\Honeypot::FIELD) è fuori dall'albero di accessibilità e dal focus
 | (aria-hidden + inert): un utente reale non lo vede né lo raggiunge.
--}}
<x-site.auth-shell :title="__('auth_public.register.title')" :intro="__('auth_public.register.intro')">
    <form wire:submit="register" novalidate class="flex flex-col gap-6" data-auth-form="register">
        <x-ui.error-summary :errors="$errors" :fields="['name', 'email', 'password', 'password_confirmation', 'privacy']" wire:key="register-errors-{{ $submissions }}" />

        <x-ui.field name="name" :label="__('auth_public.fields.name')" required>
            <x-ui.input wire:model="name" autocomplete="name" maxlength="255" />
        </x-ui.field>

        <x-ui.field name="email" :label="__('auth_public.fields.email')" :help="__('auth_public.register.email_help')" required>
            <x-ui.input type="email" icon="mail" wire:model="email" autocomplete="email" inputmode="email" spellcheck="false" maxlength="255" />
        </x-ui.field>

        {{-- Due colonne solo dove la card è larga: a lg la card divide la pagina con l'aside. --}}
        <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
            <x-ui.field name="password" :label="__('auth_public.fields.password')" :help="__('auth_public.fields.password_help', ['min' => $passwordMinLength])" required>
                <x-ui.input type="password" wire:model="password" autocomplete="new-password" />
            </x-ui.field>

            <x-ui.field name="password_confirmation" :label="__('auth_public.fields.password_confirmation')" required>
                <x-ui.input type="password" wire:model="password_confirmation" autocomplete="new-password" />
            </x-ui.field>
        </div>

        {{--
         | Presa visione dell'informativa (base contrattuale, non un consenso): il testo resta
         | vero anche senza link. Il link compare quando esiste la pagina
         | (App\Support\Site\LegalLinks::privacyPolicy()).
        --}}
        <x-ui.checkbox name="privacy" wire:model="privacy" required :description="__('auth_public.register.privacy_help')">
            {{ __('auth_public.register.privacy') }}
            @if ($privacyPolicyUrl !== null)
                <x-ui.link :href="$privacyPolicyUrl" target="_blank" data-auth-privacy-link>{{ __('auth_public.register.privacy_link') }}</x-ui.link>
            @endif
        </x-ui.checkbox>

        <div class="sr-only" aria-hidden="true" inert data-honeypot>
            <label for="field-{{ \App\Support\Auth\Honeypot::FIELD }}">{{ __('auth_public.fields.honeypot') }}</label>
            <input type="text" id="field-{{ \App\Support\Auth\Honeypot::FIELD }}" name="{{ \App\Support\Auth\Honeypot::FIELD }}" wire:model="website" tabindex="-1" autocomplete="off">
        </div>

        <x-ui.button type="submit" size="lg" icon-trailing="arrow-right" class="w-full sm:w-auto sm:self-start" data-auth-submit>{{ __('auth_public.register.submit') }}</x-ui.button>
    </form>

    <x-slot:footer>
        <p class="flex flex-wrap items-baseline gap-x-2 gap-y-1">
            <span class="text-fg-muted">{{ __('auth_public.register.have_account') }}</span>
            <x-ui.link :href="route('login')" wire:navigate.hover data-auth-link="login">{{ __('auth_public.register.login') }}</x-ui.link>
        </p>
    </x-slot:footer>
</x-site.auth-shell>
