// Aggiornamento live di una conversazione aperta (best-effort).
//
// Il backend è la fonte di verità: il canale privato `message-thread.{id}` è
// autorizzato dalla MessageThreadPolicy::view, e l'evento App\Events\MessageSent vi
// trasporta SOLO identificatori (niente PII). Qui, lato client, ci limitiamo a
// REAGIRE a quell'evento chiedendo a Livewire di ri-renderizzare il componente che
// mostra lo storico: il contenuto aggiornato (coi corpi messaggio) viaggia sul
// canale Livewire già autorizzato, non sul websocket. Se Echo non è disponibile
// (realtime disattivato / offline), il polling di Filament resta il fallback.
//
// Integrazione via Alpine (incluso in Filament): il partial della conversazione
// espone x-data="messagingThread(<id>)". All'init ci iscriviamo al canale del
// thread; al destroy (chiusura del modale) ci disiscriviamo, per non lasciare
// listener appesi.
document.addEventListener('alpine:init', () => {
    window.Alpine.data('messagingThread', (threadId) => ({
        channelName: `message-thread.${threadId}`,

        init() {
            if (! window.Echo || ! threadId) {
                return;
            }

            window.Echo.private(this.channelName).listen('.message.sent', () => {
                // Chiediamo a Livewire di rinfrescare il componente che ospita il
                // partial: rifetcha lo storico tramite il canale già autorizzato.
                if (this.$wire) {
                    this.$wire.$refresh();
                }
            });
        },

        destroy() {
            if (window.Echo && threadId) {
                window.Echo.leave(this.channelName);
            }
        },
    }));
});
