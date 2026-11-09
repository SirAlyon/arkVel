{{--
 | Dialog modale sull'elemento nativo <dialog> aperto con showModal(): focus trap, resto
 | della pagina inert ed Esc sono nativi. Adattato da Pines (Modal) e Sheaf UI (Modal), MIT.
 | Contenitore Alpine, trigger e <dialog> stanno in <x-ui.dialog.shell>, comune al drawer;
 | i toast lanciati con il dialog aperto compaiono sopra di lui (<x-ui.toaster.outlet>).
 |
 | Apertura (una qualunque):
 | - slot `trigger`: un clic sul suo contenuto apre il dialog;
 | - evento con nome: $dispatch('open-dialog', 'nome') / $dispatch('close-dialog', 'nome'),
 |   da Livewire $this->dispatch('open-dialog', name: 'nome');
 | - modello booleano: x-model="aperto" (Alpine) o wire:model="showDialog" (Livewire, anche
 |   .live): il valore segue apertura e chiusura;
 | - `open`: aperto già al caricamento (es. dopo un errore di validazione).
 | Dentro il dialog, `close()` lo chiude (x-on:click="close()" sui bottoni del footer).
 |
 | - `title` (obbligatorio) diventa l'h2 collegato con aria-labelledby; `description`
 |   (facoltativa) è collegata con aria-describedby;
 | - focus iniziale sull'elemento con `autofocus`, altrimenti sul bottone di chiusura; alla
 |   chiusura torna all'elemento che aveva il focus (di solito il trigger);
 | - Esc chiude sempre; il clic sul backdrop chiude salvo `:dismissible="false"`;
 | - `alert`: dialog di conferma (role="alertdialog", il clic fuori non chiude, icona nel
 |   tono danger). Metti `autofocus` sull'azione meno distruttiva (Annulla);
 | - `size`: sm · md (default) · lg; `icon`: icona Lucide accanto al titolo;
 | - lo scroll della pagina è bloccato finché il dialog è aperto; il dialog scorre al suo
 |   interno se il contenuto è lungo;
 | - attributi wire:model* / x-model* vanno sul contenitore Alpine, gli altri sul <dialog>.
 |
 | <x-ui.dialog name="adopt" :title="__('…')" :description="__('…')">
 |     <x-slot:trigger><x-ui.button>{{ __('…') }}</x-ui.button></x-slot:trigger>
 |     <p>…</p>
 |     <x-slot:footer>
 |         <x-ui.button variant="secondary" x-on:click="close()">{{ __('…') }}</x-ui.button>
 |         <x-ui.button wire:click="confirm">{{ __('…') }}</x-ui.button>
 |     </x-slot:footer>
 | </x-ui.dialog>
 |
 | <x-ui.dialog name="delete" alert icon="trash-2" wire:model="confirmingDelete" :title="__('…')">…</x-ui.dialog>
--}}
@props([
    'name' => null,
    'title',
    'description' => null,
    'size' => 'md',
    'alert' => false,
    'dismissible' => null,
    'icon' => null,
    'open' => false,
])

@php
    $sizes = [
        'sm' => 'w-md',
        'md' => 'w-lg',
        'lg' => 'w-2xl',
    ];

    $model = $attributes->whereStartsWith(['wire:model', 'x-model']);
    $options = [
        'name' => $name,
        'dismissible' => $dismissible ?? ! $alert,
        'open' => (bool) $open,
    ];
@endphp

<x-ui.dialog.shell
    kind="dialog"
    :options="$options"
    :model="$model"
    :trigger="$trigger ?? null"
    :described="(bool) $description"
    :attributes="$attributes->whereDoesntStartWith(['wire:model', 'x-model'])
        ->class(['ui-overlay ui-layer ui-dialog m-auto rounded-panel bg-surface-raised text-fg shadow-overlay', $sizes[$size] ?? $sizes['md']])
        ->merge(['role' => $alert ? 'alertdialog' : null, 'data-dialog' => $name])"
>
    <div class="flex flex-col gap-6 p-6 sm:p-8">
        <x-ui.dialog.header
            :title="$title"
            :description="$description"
            :icon="$icon"
            :tone="$alert ? 'danger' : 'highlight'"
            :close-label="__('ui_overlays.dialog.close')"
        />

        @if ($slot->isNotEmpty())
            <div class="flex flex-col gap-4" data-dialog-body>{{ $slot }}</div>
        @endif

        @isset($footer)
            <footer class="flex flex-col-reverse gap-3 sm:flex-row sm:flex-wrap sm:justify-end" data-dialog-footer>{{ $footer }}</footer>
        @endisset
    </div>
</x-ui.dialog.shell>
