<?php

declare(strict_types=1);

/*
 | Image pipeline: arkvel:media:regenerate command
 | (App\Console\Commands\RegenerateMediaConversions).
 */

return [
    'regenerate' => [
        'unknown_model' => 'Unknown model, or a model without media: :model',
        'failure' => 'Media :id: :message',
        'summary' => '{0} No conversions to regenerate.|{1} Regeneration started: :conversions conversion on :media media (queued ones run on the worker).|[2,*] Regeneration started: :conversions conversions on :media media (queued ones run on the worker).',
        'dry_run_summary' => '{0} Dry run: no conversions to regenerate.|{1} Dry run: :conversions conversion on :media media would be regenerated.|[2,*] Dry run: :conversions conversions on :media media would be regenerated.',
    ],
];
