{{--
    Calendario appuntamenti (pannello ente).

    L'inizializzazione di FullCalendar vive in un componente Alpine. FullCalendar
    (global build v6) è caricato via FilamentAsset (CalendarAssetServiceProvider),
    quindi `window.FullCalendar` è disponibile: nessun import ES module qui.

    Il feed eventi passa dal server (Livewire): `events` è una funzione che chiama
    `await $wire.fetchEvents(start, end)` per la finestra corrente — così i dati
    restano tenant-scoped lato PHP e non c'è alcun endpoint pubblico. Il click su un
    evento monta l'azione server `manage` col solo id; il resto (risoluzione
    tenant-scoped, autorizzazione, transizioni) è lato server.

    Slice Cal-2: interattivo per chi può gestire (canManageAppointments()) —
    drag-drop per spostare (eventDrop → $wire.reschedule) e create-by-click su slot
    vuoto (select → $wire.mountAction('create')). Il volontario (sola vista) ottiene un
    calendario read-only: `editable`/`selectable` sono false. La durata dello slot è
    fissa, quindi il resize è disattivato (eventDurationEditable: false).
--}}
<x-filament-panels::page>
    {{--
        FullCalendar v6 inietta un CSS a tema CHIARO (sfondi bianchi, testo scuro, bordi
        chiari) che in dark mode "buca" il tema di Filament. Qui rimappiamo le sue CSS
        custom properties (--fc-*) e i colori di testo/bottoni sotto `.dark` (classe che
        Filament mette su <html>), così il calendario segue il tema. Il tema chiaro resta
        quello di default. Scoping su `.fc`: tocca solo il calendario.
    --}}
    <style>
        .dark .fc {
            --fc-border-color: rgba(255, 255, 255, 0.10);
            --fc-page-bg-color: transparent;
            --fc-neutral-bg-color: rgba(255, 255, 255, 0.05);
            --fc-neutral-text-color: rgb(209 213 219);
            --fc-today-bg-color: rgba(245, 158, 11, 0.12);
            --fc-highlight-color: rgba(255, 255, 255, 0.08);
            --fc-list-event-hover-bg-color: rgba(255, 255, 255, 0.06);
            --fc-event-text-color: #fff;
            color: rgb(229 231 235);
        }

        .dark .fc a {
            color: inherit;
        }

        .dark .fc .fc-col-header-cell-cushion,
        .dark .fc .fc-daygrid-day-number,
        .dark .fc .fc-timegrid-slot-label-cushion,
        .dark .fc .fc-timegrid-axis-cushion,
        .dark .fc .fc-list-day-text,
        .dark .fc .fc-list-day-side-text,
        .dark .fc .fc-toolbar-title {
            color: rgb(243 244 246);
        }

        .dark .fc-theme-standard .fc-list-day-cushion {
            background-color: rgba(255, 255, 255, 0.05);
        }

        .dark .fc .fc-list-empty {
            background-color: transparent;
        }

        /* Bottoni della toolbar: neutri e leggibili sul fondo scuro. */
        .dark .fc .fc-button-primary {
            background-color: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 255, 255, 0.14);
            color: rgb(229 231 235);
        }

        .dark .fc .fc-button-primary:hover {
            background-color: rgba(255, 255, 255, 0.14);
        }

        .dark .fc .fc-button-primary:disabled {
            background-color: rgba(255, 255, 255, 0.04);
            border-color: rgba(255, 255, 255, 0.08);
            color: rgba(255, 255, 255, 0.40);
        }

        .dark .fc .fc-button-primary:not(:disabled).fc-button-active,
        .dark .fc .fc-button-primary:not(:disabled):active {
            background-color: rgba(255, 255, 255, 0.20);
            border-color: rgba(255, 255, 255, 0.28);
            color: #fff;
        }
    </style>

    <div
        x-data="appointmentCalendar()"
        x-on:calendar-refresh.window="refresh()"
        wire:ignore
    >
        <div x-ref="calendar"></div>
    </div>

    {{-- Legenda colori per stato: allineata alla mappa hex della pagina. --}}
    <div class="mt-4 flex flex-wrap gap-4 text-sm">
        <span class="flex items-center gap-2">
            <span class="inline-block h-3 w-3 rounded-full" style="background:#6b7280"></span>
            {{ __('appointments.calendar.legend.requested') }}
        </span>
        <span class="flex items-center gap-2">
            <span class="inline-block h-3 w-3 rounded-full" style="background:#059669"></span>
            {{ __('appointments.calendar.legend.confirmed') }}
        </span>
        <span class="flex items-center gap-2">
            <span class="inline-block h-3 w-3 rounded-full" style="background:#16a34a"></span>
            {{ __('appointments.calendar.legend.completed') }}
        </span>
        <span class="flex items-center gap-2">
            <span class="inline-block h-3 w-3 rounded-full" style="background:#dc2626"></span>
            {{ __('appointments.calendar.legend.cancelled') }}
        </span>
        <span class="flex items-center gap-2">
            <span class="inline-block h-3 w-3 rounded-full" style="background:#d97706"></span>
            {{ __('appointments.calendar.legend.no_show') }}
        </span>
    </div>

    <script>
        function appointmentCalendar() {
            return {
                calendar: null,

                init() {
                    // La global build espone window.FullCalendar (v6 inietta il proprio CSS).
                    this.calendar = new FullCalendar.Calendar(this.$refs.calendar, {
                        initialView: 'dayGridMonth',
                        locale: 'it',
                        firstDay: 1,
                        height: 'auto',
                        // scheduled_at è memorizzato/queryato in UTC lato server e gli
                        // eventi sono emessi con offset via toIso8601String(): fissiamo
                        // il fuso a UTC così la finestra [startStr,endStr] richiesta al
                        // server combacia esattamente con la colonna, senza scarti al
                        // bordo per i client in fusi diversi.
                        timeZone: 'UTC',
                        // Cal-2: interattivo SOLO per chi può gestire (gate server
                        // canManageAppointments); il volontario resta read-only.
                        editable: @js($this->canManageAppointments()),
                        // Si può trascinare l'inizio dell'evento (sposta lo slot); la
                        // DURATA è fissa (SLOT_MINUTES), quindi il resize è disattivato.
                        eventStartEditable: @js($this->canManageAppointments()),
                        eventDurationEditable: false,
                        // Selezione di uno slot vuoto → creazione (stesso gate).
                        selectable: @js($this->canManageAppointments()),
                        nowIndicator: true,
                        headerToolbar: {
                            left: 'prev,next today',
                            center: 'title',
                            right: 'dayGridMonth,timeGridWeek,timeGridDay',
                        },
                        buttonText: {
                            today: @js(__('appointments.calendar.toolbar.today')),
                            month: @js(__('appointments.calendar.toolbar.month')),
                            week: @js(__('appointments.calendar.toolbar.week')),
                            day: @js(__('appointments.calendar.toolbar.day')),
                        },
                        // Feed tenant-scoped dal server: FullCalendar passa la finestra
                        // visibile [startStr, endStr]; il resto è filtrato in PHP.
                        events: async (info, successCallback, failureCallback) => {
                            try {
                                const events = await this.$wire.fetchEvents(info.startStr, info.endStr);
                                successCallback(events);
                            } catch (error) {
                                failureCallback(error);
                            }
                        },
                        // Click su evento → monta l'azione server `manage` col solo id.
                        eventClick: (info) => {
                            info.jsEvent.preventDefault();
                            this.$wire.mountAction('manage', { id: info.event.id });
                        },
                        // Drag-drop → sposta l'appuntamento. Il server autorizza,
                        // valida e delega a RescheduleAppointment; se rifiuta (false)
                        // riportiamo l'evento all'orario precedente (revert). La durata
                        // è fissa, quindi il solo movimento sposta l'inizio.
                        eventDrop: async (info) => {
                            const ok = await this.$wire.reschedule(info.event.id, info.event.startStr);
                            if (! ok) {
                                info.revert();
                            }
                        },
                        // Selezione di uno slot vuoto → monta l'azione server `create`
                        // con l'inizio selezionato (prefill di scheduled_at).
                        select: (info) => {
                            this.$wire.mountAction('create', { start: info.startStr });
                            this.calendar.unselect();
                        },
                    });

                    this.calendar.render();
                },

                // Ri-fetch dopo una transizione (evento browser `calendar-refresh`).
                refresh() {
                    if (this.calendar) {
                        this.calendar.refetchEvents();
                    }
                },
            };
        }
    </script>
</x-filament-panels::page>
