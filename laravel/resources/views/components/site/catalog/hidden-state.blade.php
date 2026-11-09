{{--
 | Campi nascosti con lo stato corrente del catalogo (ricerca, filtri, ordinamento), per i
 | moduli GET della pagina senza JavaScript: inviando un modulo, gli altri parametri
 | restano. Con JavaScript i moduli non partono (wire:submit) e Livewire aggiorna lo stato.
 |
 | - `parameters`: nome => valore (CatalogResults::stateParameters());
 | - `except`: i nomi già gestiti dai controlli del modulo.
 |
 | <x-site.catalog.hidden-state :parameters="$results->stateParameters()" :except="['q']" />
--}}
@props([
    'parameters' => [],
    'except' => [],
])

{{-- Niente @continue nei @foreach: rompe le chiavi compilate di Livewire 4. --}}
@foreach (array_diff_key($parameters, array_flip($except)) as $parameter => $value)
    <input type="hidden" name="{{ $parameter }}" value="{{ $value }}">
@endforeach
