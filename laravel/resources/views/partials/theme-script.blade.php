{{--
 | Tema chiaro/scuro: va incluso nel <head>, PRIMA dei fogli di stile, per applicare
 | la classe `.dark` prima del primo paint (niente lampo di tema sbagliato).
 |
 | Scelta salvata in localStorage (`arkvel-theme`: light | dark | system, default
 | system = prefers-color-scheme). Espone window.arkvelTheme.get()/set() per il
 | componente <x-ui.theme-toggle> e notifica i cambi (anche da altre schede) con
 | l'evento `arkvel-theme` su document, così più selettori restano allineati.
 |
 | Solo i layout che usano i token semantici lo includono (oggi la styleguide;
 | la shell pubblica da UI-3): le pagine legacy restano in tema chiaro.
--}}
<script>
    (() => {
        const key = 'arkvel-theme';
        const modes = ['light', 'dark', 'system'];
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
            document.documentElement.classList.toggle('dark', mode === 'dark' || (mode === 'system' && media.matches));
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
        document.addEventListener('livewire:navigated', () => apply(read()));
    })();
</script>
