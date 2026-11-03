/*
 | JS dei componenti x-ui (store e dati Alpine, web component come Cally).
 |
 | Ogni gruppo di componenti ha il suo modulo in resources/js/ui/<gruppo>.js, caricato
 | qui in automatico. Alpine arriva da Livewire (@livewireScripts): i moduli registrano
 | store e `Alpine.data()` sull'evento `alpine:init`. Le librerie pesanti vanno caricate
 | con import dinamico, solo quando un componente le usa davvero.
 */
import.meta.glob('./ui/*.js', { eager: true });
