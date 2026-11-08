{{--
 | Riepilogo degli errori di un modulo, in cima al modulo (pattern GOV.UK): titolo con il
 | numero di errori ed elenco dei messaggi, ognuno un link che porta il focus al campo.
 |
 | - `messages`: mappa id del controllo → messaggio (link al campo), oppure chiave numerica
 |   → messaggio (errore del modulo, senza link). In alternativa a `errors`;
 | - `errors`: un MessageBag o il ViewErrorBag $errors (bag `default`). Si mostra il primo messaggio di ogni
 |   chiave: prima quelle di `fields` nell'ordine del modulo, con il link al controllo
 |   `field-<chiave>` (lo stesso id che genera x-ui.field), poi le altre senza link
 |   (es. un errore generale del modulo);
 | - `fields`: le chiavi dei campi del modulo, nell'ordine in cui compaiono;
 | - `title`: titolo personalizzato (default: "C'è un problema / Ci sono N problemi");
 | - `heading-level` (default 2): livello del titolo secondo la pagina (h2…h6).
 |
 | Focus: il riepilogo è focalizzabile (tabindex -1) e prende il focus quando compare
 | (Alpine x-init). Con Livewire va reso con un wire:key che cambia a ogni invio, così a
 | ogni tentativo fallito il blocco viene ricreato e il focus torna qui. Senza errori non
 | rende nulla. I link spostano il focus sul campo (non solo lo scroll).
 |
 | <x-ui.error-summary :errors="$errors" :fields="['email', 'password']" wire:key="errors-{{ $submissions }}" />
 | <x-ui.error-summary :messages="['apply-email' => __('…')]" />
--}}
@use('App\Support\Ui\ControlAttributes')

@props([
    'messages' => null,
    'errors' => null,
    'fields' => [],
    'title' => null,
    'headingLevel' => 2,
])

@php
    /** @var array<int|string, string> $items id del controllo (o indice) → messaggio */
    $items = [];

    if ($errors instanceof \Illuminate\Support\ViewErrorBag) {
        $errors = $errors->getBag('default');
    }

    if (is_array($messages)) {
        $items = array_filter($messages, static fn (mixed $message): bool => is_string($message) && $message !== '');
    } elseif ($errors instanceof \Illuminate\Contracts\Support\MessageBag) {
        foreach ($fields as $field) {
            if ($errors->has($field)) {
                $items[ControlAttributes::fieldId(null, $field, null)] = $errors->first($field);
            }
        }
        foreach ($errors->keys() as $key) {
            if (! in_array($key, $fields, true)) {
                $items[] = $errors->first($key);
            }
        }
    }

    $count = count($items);
    $id = $attributes->get('id') ?? 'error-summary';
    $titleId = $id.'-title';
    $level = min(6, max(2, (int) $headingLevel));
@endphp

@if ($count > 0)
    <div
        id="{{ $id }}"
        tabindex="-1"
        aria-labelledby="{{ $titleId }}"
        data-ui-error-summary
        x-data
        x-init="$nextTick(() => $el.focus())"
        {{ $attributes->except('id')->class('flex flex-col gap-3 rounded-card bg-danger-subtle p-5 text-on-danger-subtle') }}
    >
        <h{{ $level }} id="{{ $titleId }}" class="flex items-center gap-2 text-h5">
            <x-ui.icon name="circle-alert" />
            {{ $title ?? trans_choice('ui_forms.error_summary.title', $count, ['count' => $count]) }}
        </h{{ $level }}>
        <ul class="flex list-disc flex-col gap-1 ps-6">
            @foreach ($items as $target => $message)
                <li>
                    @if (is_string($target))
                        <x-ui.link :href="'#'.$target" variant="inherit" x-on:click.prevent="document.getElementById($el.hash.slice(1))?.focus()">{{ $message }}</x-ui.link>
                    @else
                        {{ $message }}
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
@endif
