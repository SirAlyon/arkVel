{{--
 | Nota per gli ospiti in cima ai drawer di prenotazione e contatto della scheda animale:
 | serve un account, e dopo l'accesso si torna alla scheda. Il flusso non cambia (un ospite
 | che invia il modulo va comunque alla login): la nota lo dice prima.
 |
 | Il link è un vero <a href> alla pagina di accesso. Con JS chiama `signIn` del componente
 | Livewire che lo contiene (App\Livewire\AnimalDetail\Concerns\ActsOnAnimal), che salva
 | la scheda come intended e porta alla login: lo stesso percorso di un invio da ospite.
 | Senza JS resta un link alla login.
 |
 | Fondo `surface` dentro il drawer (surface-raised): testo e link usano le coppie
 | verificate da ContrastPairs (fg e link su surface).
 |
 | <x-site.animal.guest-note :message="__('page_animal.booking.guest_note')" />
--}}
@props([
    'message',
])

<p {{ $attributes->class('flex items-start gap-3 rounded-card bg-surface p-4 text-small text-fg') }} data-animal-guest-note>
    <x-ui.icon name="log-in" class="mt-0.5 shrink-0" />
    <span class="flex min-w-0 flex-wrap items-baseline gap-x-2 gap-y-1">
        <span>{{ $message }}</span>
        <x-ui.link :href="route('login')" wire:click.prevent="signIn" class="font-bold" data-animal-guest-login>{{ __('page_animal.guest.login') }}</x-ui.link>
    </span>
</p>
