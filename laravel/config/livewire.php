<?php

declare(strict_types=1);

/*
 | Override della configurazione di Livewire: le chiavi non elencate restano quelle del
 | pacchetto (vendor/livewire/livewire/config/livewire.php, unite con mergeConfigFrom).
 */
return [
    /*
     | Barra di progresso di wire:navigate (shell pubblica, UI-3).
     |
     | Livewire scrive il valore così com'è in `:root { --livewire-progress-bar-color: … }`.
     | Invece di un hex usiamo il token semantico `primary` (resources/css/app.css): la
     | variabile si risolve su <html>, quindi segue anche la dark mode (navy in chiaro,
     | giallo in scuro). Nei pannelli Filament il token non esiste e vale il colore di
     | ripiego di Livewire, ma lì wire:navigate non è attivo.
     */
    'navigate' => [
        'show_progress_bar' => true,
        'progress_bar_color' => 'var(--color-primary)',
    ],
];
