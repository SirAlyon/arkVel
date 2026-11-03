<div>
    @php
        $location = collect([$organization->city, $organization->region, $organization->country])->filter()->implode(', ');
        $description = $organization->getTranslation('description', app()->getLocale(), false);
    @endphp

    {{-- Intestazione ente --}}
    <section class="border-b border-brand-100 bg-gradient-to-br from-brand-100 via-brand-50 to-accent-50">
        <div class="mx-auto max-w-6xl px-4 py-12 sm:px-6">
            <nav class="mb-5 flex items-center gap-2 text-sm text-stone-500" aria-label="{{ __('public.detail.breadcrumb') }}">
                <a href="{{ route('organizations.index') }}" class="hover:text-brand-700">{{ __('public.nav.organizations') }}</a>
                <span aria-hidden="true">/</span>
                <span class="text-stone-700">{{ $organization->name }}</span>
            </nav>

            <x-badge color="accent">{{ $organization->type->getLabel() }}</x-badge>
            <h1 class="mt-3 text-3xl font-bold tracking-tight text-stone-900">{{ $organization->name }}</h1>

            @if ($location)
                <p class="mt-2 flex items-center gap-1.5 text-stone-600">
                    <svg class="h-4 w-4 text-brand-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M9.69 18.933c.11.058.204.1.256.14.052-.04.146-.082.256-.14a12.14 12.14 0 0 0 3.394-2.63C15.14 14.606 17 11.777 17 8A7 7 0 1 0 3 8c0 3.777 1.86 6.606 3.404 8.303a12.14 12.14 0 0 0 3.286 2.63ZM10 10.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z" clip-rule="evenodd" />
                    </svg>
                    {{ $location }}
                </p>
            @endif

            @if ($description)
                <p class="mt-4 max-w-3xl whitespace-pre-line leading-relaxed text-stone-600">{{ $description }}</p>
            @endif

            {{-- Contatti pubblici --}}
            <div class="mt-6 flex flex-wrap gap-4 text-sm">
                @if ($organization->email)
                    <a href="mailto:{{ $organization->email }}" class="inline-flex items-center gap-1.5 text-brand-700 hover:text-brand-800">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M3 4a2 2 0 0 0-2 2v1.161l8.441 4.221a1.25 1.25 0 0 0 1.118 0L19 7.162V6a2 2 0 0 0-2-2H3Z"/><path d="m19 8.839-7.77 3.885a2.75 2.75 0 0 1-2.46 0L1 8.839V14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V8.839Z"/></svg>
                        {{ $organization->email }}
                    </a>
                @endif
                @if ($organization->phone)
                    <a href="tel:{{ $organization->phone }}" class="inline-flex items-center gap-1.5 text-brand-700 hover:text-brand-800">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M2 3.5A1.5 1.5 0 0 1 3.5 2h1.148a1.5 1.5 0 0 1 1.465 1.175l.716 3.223a1.5 1.5 0 0 1-1.052 1.767l-.933.267c-.41.117-.643.555-.48.95a11.542 11.542 0 0 0 6.254 6.254c.395.163.833-.07.95-.48l.267-.933a1.5 1.5 0 0 1 1.767-1.052l3.223.716A1.5 1.5 0 0 1 18 15.352V16.5a1.5 1.5 0 0 1-1.5 1.5H15c-7.18 0-13-5.82-13-13V3.5Z" clip-rule="evenodd"/></svg>
                        {{ $organization->phone }}
                    </a>
                @endif
                @if ($organization->website)
                    <a href="{{ $organization->website }}" target="_blank" rel="noopener noreferrer"
                       class="inline-flex items-center gap-1.5 text-brand-700 hover:text-brand-800">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm.5-13.95a6.5 6.5 0 0 1 0 11.9V4.05Zm-1 0v11.9a6.5 6.5 0 0 1 0-11.9Z" clip-rule="evenodd"/></svg>
                        {{ __('public.organization.website') }}
                    </a>
                @endif
            </div>
        </div>
    </section>

    {{-- Sostieni l'ente (donazione una tantum, P4-6) --}}
    @if ($donationsEnabled)
        <section class="border-b border-brand-100 bg-white/60">
            <div class="mx-auto max-w-6xl px-4 py-12 sm:px-6">
                <div class="mx-auto max-w-2xl rounded-3xl border border-brand-100 bg-white p-6 shadow-sm sm:p-8">
                    <h2 class="text-2xl font-bold tracking-tight text-stone-900">
                        {{ __('donations.public.heading', ['name' => $organization->name]) }}
                    </h2>
                    <p class="mt-2 text-stone-600">{{ __('donations.public.subheading') }}</p>

                    @if ($donationError)
                        <div class="mt-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700" role="alert">
                            {{ $donationError }}
                        </div>
                    @endif

                    <form wire:submit="donate" class="mt-6 space-y-5">
                        {{-- Importi preset --}}
                        <div>
                            <span class="block text-sm font-medium text-stone-700">{{ __('donations.public.amount_label') }}</span>
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach ($donationPresets as $preset)
                                    <button type="button"
                                            wire:click="$set('donationAmount', '{{ $preset }}')"
                                            @class([
                                                'rounded-xl border px-4 py-2 text-sm font-semibold transition',
                                                'border-brand-600 bg-brand-600 text-white' => (string) $donationAmount === (string) $preset,
                                                'border-brand-300 bg-white text-brand-800 hover:bg-brand-50' => (string) $donationAmount !== (string) $preset,
                                            ])>
                                        {{ $preset }} €
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        {{-- Importo libero --}}
                        <div>
                            <label for="donationAmount" class="block text-sm font-medium text-stone-700">
                                {{ __('donations.public.custom_amount_label') }}
                            </label>
                            <div class="mt-1 flex items-center gap-2">
                                <input type="number" step="0.01" min="1" id="donationAmount" wire:model="donationAmount"
                                       class="w-40 rounded-xl border-stone-300 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                                       placeholder="25" />
                                <span class="text-stone-500">€</span>
                            </div>
                            @error('donationAmount')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Dati ospite (solo per non autenticati) --}}
                        @if ($isGuestDonor)
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label for="donorName" class="block text-sm font-medium text-stone-700">
                                        {{ __('donations.public.name_label') }}
                                    </label>
                                    <input type="text" id="donorName" wire:model="donorName"
                                           class="mt-1 block w-full rounded-xl border-stone-300 shadow-sm focus:border-brand-500 focus:ring-brand-500" />
                                    @error('donorName')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label for="donorEmail" class="block text-sm font-medium text-stone-700">
                                        {{ __('donations.public.email_label') }}
                                    </label>
                                    <input type="email" id="donorEmail" wire:model="donorEmail"
                                           class="mt-1 block w-full rounded-xl border-stone-300 shadow-sm focus:border-brand-500 focus:ring-brand-500" />
                                    @error('donorEmail')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        @endif

                        {{-- Messaggio facoltativo --}}
                        <div>
                            <label for="donationMessage" class="block text-sm font-medium text-stone-700">
                                {{ __('donations.public.message_label') }}
                            </label>
                            <textarea id="donationMessage" wire:model="donationMessage" rows="2"
                                      class="mt-1 block w-full rounded-xl border-stone-300 shadow-sm focus:border-brand-500 focus:ring-brand-500"></textarea>
                            @error('donationMessage')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <x-button type="submit" variant="accent" size="lg" wire:loading.attr="disabled" wire:target="donate">
                            <span wire:loading.remove wire:target="donate">{{ __('donations.public.submit') }}</span>
                            <span wire:loading wire:target="donate">{{ __('donations.public.submitting') }}</span>
                        </x-button>
                        <p class="text-xs text-stone-500">{{ __('donations.public.secure_note') }}</p>
                    </form>
                </div>
            </div>
        </section>
    @endif

    {{-- Animali dell'ente --}}
    <section class="mx-auto max-w-6xl px-4 py-12 sm:px-6">
        <h2 class="text-2xl font-bold tracking-tight text-stone-900">
            {{ trans_choice('public.organization.animals_count', $animals->total(), ['count' => $animals->total()]) }}
        </h2>

        @if ($animals->isNotEmpty())
            <div class="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @foreach ($animals as $animal)
                    <x-animal-card :animal="$animal" :wire:key="'animal-'.$animal->id" />
                @endforeach
            </div>

            <div class="mt-10">
                {{ $animals->onEachSide(1)->links() }}
            </div>
        @else
            <p class="mt-6 rounded-2xl border border-dashed border-brand-200 bg-white/60 p-10 text-center text-stone-500">
                {{ __('public.organization.no_animals') }}
            </p>
        @endif
    </section>
</div>
