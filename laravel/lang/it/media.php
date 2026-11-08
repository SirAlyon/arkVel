<?php

declare(strict_types=1);

/*
 | Pipeline delle immagini: comando arkvel:media:regenerate
 | (App\Console\Commands\RegenerateMediaConversions).
 */

return [
    'regenerate' => [
        'unknown_model' => 'Model sconosciuto o senza media: :model',
        'failure' => 'Media :id: :message',
        'summary' => '{0} Nessuna conversione da rigenerare.|{1} Rigenerazione avviata: :conversions conversione su :media media (quelle in coda le esegue il worker).|[2,*] Rigenerazione avviata: :conversions conversioni su :media media (quelle in coda le esegue il worker).',
        'dry_run_summary' => '{0} Prova: nessuna conversione da rigenerare.|{1} Prova: si rigenererebbe :conversions conversione su :media media.|[2,*] Prova: si rigenererebbero :conversions conversioni su :media media.',
    ],
];
