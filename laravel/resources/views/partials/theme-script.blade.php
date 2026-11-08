{{--
 | Tema chiaro/scuro: va incluso nel <head>, PRIMA dei fogli di stile, per applicare
 | la classe `.dark` prima del primo paint (niente lampo di tema sbagliato).
 |
 | Scelta salvata in localStorage (`arkvel-theme`: light | dark | system, default
 | system = prefers-color-scheme). Espone window.arkvelTheme.get()/set() per il
 | componente <x-ui.theme-toggle> e notifica i cambi (anche da altre schede) con
 | l'evento `arkvel-theme` su document, così più selettori restano allineati.
 |
 | Pagine non ancora migrate ai token semantici: `data-theme-forced="light"` su <html>
 | (lo mette layouts/app quando la pagina non passa `themeable`) blocca il tema chiaro,
 | qualunque sia la scelta salvata; la scelta resta in memoria per le pagine themeable.
 |
 | Navigazione wire:navigate: Livewire sostituisce gli attributi di <html> con quelli
 | della nuova pagina (che non ha `.dark`). Il tema si riapplica in `onSwap`, subito dopo
 | lo scambio e prima del paint, e di nuovo su `livewire:navigated`. Lo script sta nel
 | <head>, identico su ogni pagina: Livewire lo esegue una volta sola, quindi i listener
 | non si registrano due volte.
 |
 | syncToggle(): i selettori del tema lo chiamano subito dopo il loro markup, così
 | aria-pressed è giusto prima che parta Alpine (niente stato iniziale sbagliato).
--}}
<script>
    (() => {
        const key = 'arkvel-theme';
        const modes = ['light', 'dark', 'system'];
        const root = document.documentElement;
        const media = window.matchMedia('(prefers-color-scheme: dark)');

        const read = () => {
            try {
                const stored = window.localStorage.getItem(key);

                return modes.includes(stored) ? stored : 'system';
            } catch {
                return 'system';
            }
        };

        const apply = (mode) => {
            const forced = root.dataset.themeForced;
            const dark = forced ? forced === 'dark' : mode === 'dark' || (mode === 'system' && media.matches);

            root.classList.toggle('dark', dark);
        };

        const notify = (mode) => document.dispatchEvent(new CustomEvent('arkvel-theme', { detail: { mode } }));

        window.arkvelTheme = {
            get: read,
            set(mode) {
                if (! modes.includes(mode)) {
                    return;
                }

                try {
                    window.localStorage.setItem(key, mode);
                } catch {
                    // Storage non disponibile (es. navigazione privata): vale solo per questa pagina.
                }

                apply(mode);
                notify(mode);
            },
            syncToggle(group) {
                const mode = read();

                group?.querySelectorAll('[data-theme-option]').forEach((option) => {
                    option.setAttribute('aria-pressed', String(option.dataset.themeOption === mode));
                });
            },
        };

        apply(read());
        // Un'altra scheda ha cambiato tema: allinea questa pagina e i suoi selettori.
        window.addEventListener('storage', (event) => {
            if (event.key === key) {
                apply(read());
                notify(read());
            }
        });
        media.addEventListener('change', () => read() === 'system' && apply('system'));
        document.addEventListener('livewire:navigating', (event) => event.detail?.onSwap?.(() => apply(read())));
        document.addEventListener('livewire:navigated', () => apply(read()));
    })();
</script>
