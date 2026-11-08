{{--
 | Regione delle notifiche toast: una per layout (in fondo al <body>). Adattato da Pines
 | (Toast), MIT.
 |
 | - mostra lo store Alpine `toasts` (resources/js/ui/overlays.js), alimentato dall'evento
 |   `toast`: window.dispatchEvent(new CustomEvent('toast', { detail: { type, title, description } })),
 |   $dispatch('toast', { … }) da Alpine, $this->dispatch('toast', type: 'success', title: '…')
 |   da Livewire;
 | - al render legge il flash di sessione `toast` (array con type/title/description, o una
 |   stringa: diventa il titolo di un toast success):
 |   return redirect()->route('…')->with('toast', ['type' => 'success', 'title' => __('…')]);
 | - annunci: role="status" (polite) per success/info/warning, role="alert" (assertive) per
 |   danger, in due regioni live sempre presenti nella pagina; il testo segue gli schemi
 |   tradotti ui_overlays.toast.announcement(_short);
 | - chiusura automatica (danger resta finché non la chiudi), in pausa con il mouse sopra
 |   o con il focus dentro; bottone di chiusura tradotto; al massimo 5 toast impilati;
 | - in basso a destra, in basso al centro su mobile. Riserva scroll-padding in fondo alla
 |   pagina, così i toast non coprono l'elemento con il focus (WCAG 2.4.11);
 | - con un dialog o drawer aperto la pagina è inerte: annunci e lista passano all'outlet
 |   dentro il modale (<x-ui.toaster.outlet>), e qui la lista si svuota.
--}}
@php
    $flash = session('toast');

    if (is_string($flash)) {
        $flash = ['type' => 'success', 'title' => $flash];
    }

    $options = [
        'messages' => [
            'types' => [
                'success' => __('ui_overlays.toast.types.success'),
                'warning' => __('ui_overlays.toast.types.warning'),
                'danger' => __('ui_overlays.toast.types.danger'),
                'info' => __('ui_overlays.toast.types.info'),
            ],
            'announcement' => __('ui_overlays.toast.announcement'),
            'announcementShort' => __('ui_overlays.toast.announcement_short'),
        ],
        'flash' => is_array($flash) ? $flash : null,
    ];
@endphp

<section
    x-data="uiToaster(@js($options))"
    aria-label="{{ __('ui_overlays.toaster.label') }}"
    data-toaster
    {{ $attributes->class('pointer-events-none fixed inset-x-0 bottom-0 z-50 flex justify-center p-4 sm:inset-x-auto sm:right-0 sm:p-6') }}
>
    <x-ui.toaster.stack class="flex w-full max-w-sm sm:w-sm" />
</section>
