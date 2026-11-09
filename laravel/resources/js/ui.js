/*
 | JS dei componenti x-ui (store e dati Alpine, web component come Cally).
 |
 | Ogni gruppo di componenti ha il suo modulo in resources/js/ui/<gruppo>.js, caricato
 | qui in automatico. Alpine arriva da Livewire (@livewireScripts): i moduli registrano
 | store e `Alpine.data()` sull'evento `alpine:init`. Le librerie pesanti vanno caricate
 | con import dinamico, solo quando un componente le usa davvero.
 */
// L'esclusione dei nomi con spazio protegge dalle copie dei client di sincronizzazione
// ("overlays 2.js"): caricate due volte, registrerebbero due volte store e listener.
import.meta.glob(['./ui/*.js', '!./ui/* *.js'], { eager: true });
