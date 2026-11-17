{{--
 | Avviso pre-beta del sito pubblico (token semantici: funziona in chiaro e in scuro).
 | Un modale informativo — non un alert — che alla prima visita spiega onestamente che il
 | sito è un'anteprima e che i dati sono temporanei. Si chiude e non torna più.
 |
 | - wrappa <x-ui.dialog> e ne controlla l'apertura con x-model="open" (uiDialog, x-modelable);
 | - all'init apre il dialog SOLO se la visita non l'ha già confermato: il gate è una chiave
 |   versionata in localStorage (arkvel.prebeta-notice.v1), così si può rimostrare in futuro
 |   alzando la versione. Ogni accesso a localStorage è in try/catch: se non è disponibile
 |   (finestra privata, storage bloccato) non mostriamo nulla, invece di rompere la pagina;
 | - chiudere in qualunque modo (bottone, Esc, clic sul backdrop) registra la conferma, così
 |   non riappare al prossimo caricamento. È dismissible e non blocca la navigazione.
--}}
<div
    data-prebeta-notice
    x-data="{
        open: false,
        storageKey: 'arkvel.prebeta-notice.v1',
        init() {
            let acknowledged = true;

            try {
                acknowledged = localStorage.getItem(this.storageKey) !== null;
            } catch (error) {
                acknowledged = true;
            }

            // Alla chiusura (bottone, Esc o backdrop) ricorda la conferma.
            this.$watch('open', (value) => value || this.remember());

            if (! acknowledged) {
                this.$nextTick(() => { this.open = true; });
            }
        },
        remember() {
            try {
                localStorage.setItem(this.storageKey, '1');
            } catch (error) {
                // localStorage non disponibile: pazienza, non blocchiamo la navigazione.
            }
        },
    }"
>
    <x-ui.dialog name="prebeta" x-model="open" icon="construction" :title="__('prebeta.title')">
        <x-ui.badge variant="highlight" icon="sparkles">{{ __('prebeta.eyebrow') }}</x-ui.badge>

        <div class="flex flex-col gap-3 text-fg-muted">
            <p>{{ __('prebeta.body.p1') }}</p>
            <p>{{ __('prebeta.body.p2') }}</p>
            <p>{{ __('prebeta.body.p3') }}</p>
        </div>

        <x-slot:footer>
            <x-ui.button x-on:click="close()" icon="check">{{ __('prebeta.acknowledge') }}</x-ui.button>
        </x-slot:footer>
    </x-ui.dialog>
</div>
