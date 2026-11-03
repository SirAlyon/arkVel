{{--
    Bundle Vite del realtime (Echo su Reverb) iniettato nei pannelli Filament via
    render hook (HEAD_END). Fornisce window.Echo, consumato sia dalla campanella
    notifiche di Filament (evento `database-notifications.sent`) sia dal refresh live
    delle conversazioni (resources/js/messaging-realtime.js). Se la build/Reverb non
    sono disponibili, il polling di Filament resta il fallback.
--}}
@vite(['resources/js/app.js'])
