<x-filament-panels::page>
    <div class="mx-auto flex w-full max-w-2xl flex-col gap-6">
        <div class="rounded-xl border border-danger-300 bg-danger-50 p-6 dark:border-danger-500/30 dark:bg-danger-500/10">
            <h2 class="text-base font-semibold text-danger-700 dark:text-danger-400">
                {{ __('account.delete.zone_heading') }}
            </h2>

            <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
                {{ __('account.delete.zone_intro') }}
            </p>

            <ul class="mt-4 list-disc space-y-1 pl-5 text-sm text-gray-700 dark:text-gray-300">
                <li>{{ __('account.delete.zone_removed_profile') }}</li>
                <li>{{ __('account.delete.zone_removed_favorites') }}</li>
                <li>{{ __('account.delete.zone_removed_login') }}</li>
                <li>{{ __('account.delete.zone_removed_recurring') }}</li>
            </ul>

            <p class="mt-4 text-sm text-gray-700 dark:text-gray-300">
                {{ __('account.delete.zone_kept') }}
            </p>

            <p class="mt-2 text-sm font-medium text-danger-700 dark:text-danger-400">
                {{ __('account.delete.zone_irreversible') }}
            </p>

            <div class="mt-6">
                {{ $this->deleteAccountAction }}
            </div>
        </div>
    </div>
</x-filament-panels::page>
