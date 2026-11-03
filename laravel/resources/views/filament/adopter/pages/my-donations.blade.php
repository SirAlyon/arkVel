<x-filament-panels::page>
    {{ $this->table }}

    @php
        $charges = $this->chargeHistory();
    @endphp

    @if ($charges->isNotEmpty())
        <x-filament::section>
            <x-slot name="heading">{{ __('donations.my.charges_heading') }}</x-slot>
            <x-slot name="description">{{ __('donations.my.charges_description') }}</x-slot>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 dark:text-gray-400">
                            <th class="py-2 pr-4 font-medium">{{ __('donations.my.charge_date') }}</th>
                            <th class="py-2 pr-4 font-medium">{{ __('donations.my.organization') }}</th>
                            <th class="py-2 font-medium">{{ __('donations.my.amount') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                        @foreach ($charges as $charge)
                            <tr>
                                <td class="py-2 pr-4 text-gray-700 dark:text-gray-200">
                                    {{ $charge->paid_at?->format('d/m/Y') ?? '—' }}
                                </td>
                                <td class="py-2 pr-4 text-gray-700 dark:text-gray-200">
                                    {{ $charge->organization->name }}
                                </td>
                                <td class="py-2 font-medium text-gray-900 dark:text-white">
                                    {{ \Illuminate\Support\Number::currency($charge->amountInEuros(), strtoupper($charge->currency)) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
