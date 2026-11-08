{{--
 | Outlet dei toast dentro un dialog o drawer (uso interno: lo include x-ui.dialog.shell).
 |
 | showModal() rende inerte tutto ciò che sta fuori dal modale, compreso <x-ui.toaster>:
 | niente annunci, toast sotto il ::backdrop, bottone di chiusura irraggiungibile. Neanche
 | un popover esterno mostrato sopra il dialog basta: resta inerte (verificato in Chrome).
 | L'outlet sta invece dentro il <dialog>: quando il suo modale è quello aperto più in
 | alto, le sue regioni live (role="status" / role="alert") annunciano i toast dello store
 | e la lista si apre come popover nel top layer, sopra dialog e backdrop (il toaster della
 | pagina intanto tace e svuota la sua lista). Con Tab i toast si raggiungono dopo il
 | contenuto del dialog.
 |
 | `placement`: dove compare la lista, per non coprire le azioni del modale. Su mobile un
 | dialog alto può finire in parte sotto i toast: restano chiudibili e, con Tab, vengono
 | subito dopo il contenuto del dialog.
 | - dialog (default): in basso a destra come il toaster della pagina (il dialog è centrato);
 | - drawer-right / drawer-left: dal tablet in su in basso, dal lato opposto al pannello;
 |   su mobile in alto (il pannello occupa lo schermo e il footer ha le azioni);
 | - drawer-bottom: in alto (il bottom sheet occupa il fondo).
--}}
@props([
    'placement' => 'dialog',
])

@php
    $placements = [
        'dialog' => 'top-auto bottom-4 sm:right-6 sm:bottom-6',
        'drawer-right' => 'top-4 bottom-auto sm:top-auto sm:bottom-6 sm:left-6',
        'drawer-left' => 'top-4 bottom-auto sm:top-auto sm:right-6 sm:bottom-6',
        'drawer-bottom' => 'top-4 bottom-auto sm:top-6 sm:right-6',
    ];
@endphp

<div x-data="uiToasterOutlet" class="contents" data-toaster-outlet="{{ array_key_exists($placement, $placements) ? $placement : 'dialog' }}">
    <x-ui.toaster.stack
        popover="manual"
        @class([
            'ui-layer pointer-events-none inset-x-4 mx-auto my-0 h-auto w-auto max-w-sm overflow-visible border-0 bg-transparent p-0 open:flex sm:inset-x-auto sm:mx-0 sm:w-sm',
            // In dark il toast (surface-raised) ha lo stesso fondo del modale e l'ombra quasi
            // non si vede: un bordo line-strong lo stacca quando gli sta sopra.
            'dark:[&_[data-toast]]:ring-2 dark:[&_[data-toast]]:ring-line-strong',
            $placements[$placement] ?? $placements['dialog'],
        ])
    />
</div>
