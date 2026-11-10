{{--
 | Impaginazione comune delle pagine legali (resources/views/livewire/legal): avviso di
 | bozza in cima, intestazione con un solo h1 e i dati di versione, indice (TOC) ancorato
 | alle sezioni e la colonna di lettura (max-w-prose). Solo token semantici: segue il tema.
 |
 | Gerarchia: h1 della pagina → h2 di ogni sezione → h3 per i sotto-punti. Le sezioni
 | arrivano dallo slot; ognuna deve avere un id corrispondente a una voce di `toc` e un h2.
 |
 | - `title` (obbligatorio): h1 della pagina (id `legal-title`);
 | - `intro`: testo introduttivo sotto il titolo;
 | - `eyebrow`: etichetta breve sopra il titolo;
 | - `version` (obbligatorio): versione del documento (stringa, es. `2026-11`);
 | - `updatedOn`: data dell'ultimo aggiornamento (Carbon) o null;
 | - `toc` (obbligatorio): array di ['id' => string, 'label' => string] per l'indice.
 |
 | I testi dell'intelaiatura (avviso di bozza, etichette) sono in lang/*/legal.php.
--}}
@props([
    'title',
    'intro' => null,
    'eyebrow' => null,
    'version',
    'updatedOn' => null,
    'toc' => [],
])

<div class="mx-auto flex w-full max-w-page flex-col gap-10 px-gutter py-section" data-legal-page>
    {{-- Avviso di bozza: il testo è provvisorio finché il titolare del trattamento non lo valida. --}}
    <aside
        role="note"
        aria-label="{{ __('legal.draft.label') }}"
        class="flex items-start gap-4 rounded-card bg-warning-subtle p-6 text-on-warning-subtle"
        data-legal-draft
    >
        <span class="mt-0.5 shrink-0" aria-hidden="true"><x-ui.icon name="triangle-alert" /></span>
        <div class="flex flex-col gap-1">
            <p class="font-bold">{{ __('legal.draft.title') }}</p>
            <p class="text-small">{{ __('legal.draft.body') }}</p>
        </div>
    </aside>

    <header class="flex max-w-prose flex-col gap-4" data-legal-header>
        @if (filled($eyebrow))
            <p><x-ui.badge variant="highlight" icon="scale" class="-rotate-2">{{ $eyebrow }}</x-ui.badge></p>
        @endif
        <h1 id="legal-title" class="text-h1 text-fg">{{ $title }}</h1>
        @if (filled($intro))
            <p class="text-lead text-fg-muted">{{ $intro }}</p>
        @endif
        <p class="flex flex-wrap items-center gap-x-4 gap-y-1 text-small text-fg-muted" data-legal-meta>
            @if ($updatedOn !== null)
                <span class="inline-flex items-center gap-2">
                    <x-ui.icon name="calendar-check" size="sm" />
                    {{ __('legal.updated', ['date' => $updatedOn->translatedFormat('F Y')]) }}
                </span>
            @endif
            <span class="inline-flex items-center gap-2">
                <x-ui.icon name="file-text" size="sm" />
                {{ __('legal.version', ['version' => $version]) }}
            </span>
        </p>
    </header>

    <div class="grid gap-10 lg:grid-cols-[18rem_minmax(0,1fr)]" data-legal-layout>
        @if (! empty($toc))
            <nav aria-labelledby="legal-toc-title" class="lg:sticky lg:top-28 lg:self-start" data-legal-toc>
                <h2 id="legal-toc-title" class="text-h5 text-fg">{{ __('legal.toc') }}</h2>
                <ol class="mt-4 flex flex-col gap-2">
                    @foreach ($toc as $entry)
                        <li>
                            <x-ui.link href="#{{ $entry['id'] }}" class="text-small">{{ $entry['label'] }}</x-ui.link>
                        </li>
                    @endforeach
                </ol>
            </nav>
        @endif

        <div class="flex max-w-prose flex-col gap-10 text-body text-fg [overflow-wrap:anywhere]" data-legal-body>
            {{ $slot }}
        </div>
    </div>
</div>
