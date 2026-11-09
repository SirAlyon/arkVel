{{--
 | /reimposta-password/{token} (App\Livewire\Auth\ResetPasswordPage). Link non valido o
 | scaduto (e limite di tentativi): errore del modulo (chiave `form`) nel riepilogo, con
 | l'azione per chiedere un nuovo link.
--}}
<x-site.auth-shell :title="__('auth_public.reset.title')" :intro="__('auth_public.reset.intro')">
    <form wire:submit="resetPassword" novalidate class="flex flex-col gap-6" data-auth-form="reset">
        <x-ui.error-summary :errors="$errors" :fields="['email', 'password', 'password_confirmation']" wire:key="reset-errors-{{ $submissions }}" />

        @error('form')
            <x-ui.button variant="secondary" :href="route('password.request')" icon="mail" class="w-full sm:w-auto sm:self-start" data-auth-request-new>{{ __('auth_public.reset.request_new') }}</x-ui.button>
        @enderror

        <x-ui.field name="email" :label="__('auth_public.fields.email')" required>
            {{-- username: i gestori di password abbinano la nuova password a questo account. --}}
            <x-ui.input type="email" icon="mail" wire:model="email" autocomplete="username" inputmode="email" spellcheck="false" />
        </x-ui.field>

        {{-- Due colonne solo dove la card è larga: a lg la card divide la pagina con l'aside. --}}
        <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
            <x-ui.field name="password" :label="__('auth_public.fields.new_password')" :help="__('auth_public.fields.password_help', ['min' => $passwordMinLength])" required>
                <x-ui.input type="password" wire:model="password" autocomplete="new-password" />
            </x-ui.field>

            <x-ui.field name="password_confirmation" :label="__('auth_public.fields.password_confirmation')" required>
                <x-ui.input type="password" wire:model="password_confirmation" autocomplete="new-password" />
            </x-ui.field>
        </div>

        <x-ui.button type="submit" size="lg" icon-trailing="check" class="w-full sm:w-auto sm:self-start" data-auth-submit>{{ __('auth_public.reset.submit') }}</x-ui.button>
    </form>

    <x-slot:footer>
        <p><x-ui.link :href="route('password.request')" wire:navigate.hover data-auth-link="forgot">{{ __('auth_public.reset.request_new') }}</x-ui.link></p>
    </x-slot:footer>
</x-site.auth-shell>
