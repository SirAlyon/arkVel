{{-- /accedi (App\Livewire\Auth\LoginPage). Errori: riepilogo in cima + messaggio sul campo. --}}
<x-site.auth-shell :title="__('auth_public.login.title')" :intro="__('auth_public.login.intro')">
    <form wire:submit="authenticate" novalidate class="flex flex-col gap-6" data-auth-form="login">
        <x-ui.error-summary :errors="$errors" :fields="['email', 'password']" wire:key="login-errors-{{ $submissions }}" />

        <x-ui.field name="email" :label="__('auth_public.fields.email')" required>
            <x-ui.input type="email" icon="mail" wire:model="email" autocomplete="email" inputmode="email" spellcheck="false" />
        </x-ui.field>

        <x-ui.field name="password" :label="__('auth_public.fields.password')" required>
            <x-ui.input type="password" wire:model="password" autocomplete="current-password" />
        </x-ui.field>

        <div class="flex flex-wrap items-center justify-between gap-x-6 gap-y-2">
            <x-ui.checkbox name="remember" wire:model="remember">{{ __('auth_public.login.remember') }}</x-ui.checkbox>
            <x-ui.link :href="route('password.request')" wire:navigate.hover data-auth-link="forgot">{{ __('auth_public.login.forgot') }}</x-ui.link>
        </div>

        <x-ui.button type="submit" size="lg" icon-trailing="arrow-right" class="w-full sm:w-auto sm:self-start" data-auth-submit>{{ __('auth_public.login.submit') }}</x-ui.button>
    </form>

    <x-slot:footer>
        <p class="flex flex-wrap items-baseline gap-x-2 gap-y-1">
            <span class="text-fg-muted">{{ __('auth_public.login.no_account') }}</span>
            <x-ui.link :href="route('register')" wire:navigate.hover data-auth-link="register">{{ __('auth_public.login.register') }}</x-ui.link>
        </p>
    </x-slot:footer>
</x-site.auth-shell>
