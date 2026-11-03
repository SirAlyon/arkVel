import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Bootstrap di Laravel Echo sul driver Reverb (self-hosted, protocollo
// Pusher-compatibile). Esposto su window.Echo così da essere consumabile sia dal
// codice applicativo sia dal layer realtime di Filament (campanella notifiche).
//
// La configurazione viene dalle VITE_REVERB_* (iniettate a build time): se la
// chiave non è presente (es. ambienti dove il realtime è disattivato) non
// inizializziamo nulla e il polling resta il fallback. Nessun crash se Reverb è
// offline: Echo riprova la connessione in background.
window.Pusher = Pusher;

const reverbKey = import.meta.env.VITE_REVERB_APP_KEY;

if (reverbKey) {
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: reverbKey,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
        wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
    });

    // La campanella notifiche di Filament aggancia il suo listener Echo al segnale
    // `EchoLoaded`; lo emettiamo qui così il badge va live anche se questo bundle è
    // valutato dopo il render del componente Livewire.
    window.dispatchEvent(new CustomEvent('EchoLoaded'));
}
