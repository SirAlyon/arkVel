<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Media\RegenerateImageConversions;
use App\Data\Media\RegenerateConversionsData;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;

/**
 * Rigenera le conversioni medialibrary dei media esistenti (UI-4: card, hero, og, zoom in
 * AVIF/WebP/JPEG con responsive images). Sottile: traduce le opzioni nei filtri e delega
 * a App\Actions\Media\RegenerateImageConversions.
 *
 *     php artisan arkvel:media:regenerate --only-missing
 *     php artisan arkvel:media:regenerate --model=animal --collection=photos --conversion=card
 *     php artisan arkvel:media:regenerate --conversion=card-avif --dry-run
 *
 * Le conversioni in coda vanno al worker (servizio `queue`): il comando accoda e termina.
 *
 * Quando rilanciarlo:
 * - dopo aver aggiunto o cambiato una conversione (App\Enums\ImageConversion);
 * - dopo un cambio di driver immagine (`IMAGE_DRIVER`) o di librerie (libwebp/libavif nel
 *   Dockerfile): i formati registrati seguono ciò che il driver sa scrivere
 *   (App\Support\Media\ImageFormatSupport), quindi abilitare AVIF/WebP aggiunge
 *   conversioni che i media esistenti non hanno ancora: `--only-missing` le genera.
 *   Le conversioni di un formato tolto restano sul disco ma non vengono più lette.
 *
 * `--only-missing` legge `generated_conversions`, che si aggiorna solo quando un job
 * finisce: non vede i job ancora in coda. Lanciato mentre il worker smaltisce un lavoro
 * precedente, riaccoda quei media: nessun danno (lo stesso file si riscrive uguale), solo
 * lavoro doppio. Meglio lanciarlo a coda vuota.
 */
final class RegenerateMediaConversions extends Command
{
    use ConfirmableTrait;

    protected $signature = 'arkvel:media:regenerate
        {--model=* : Model proprietario: alias (animal) o classe (App\\Models\\Animal)}
        {--collection=* : Media collection (es. photos, avatar)}
        {--conversion=* : Conversione: nome esatto (card-webp) o base (card = tutti i formati)}
        {--only-missing : Solo le conversioni non ancora generate (non vede i job ancora in coda: lancialo a coda vuota)}
        {--dry-run : Conta media e conversioni senza generare né accodare nulla}
        {--force : Esegue anche in produzione senza chiedere conferma}';

    protected $description = 'Rigenera le conversioni immagine (card, hero, og, zoom…) dei media esistenti';

    public function handle(RegenerateImageConversions $regenerate): int
    {
        $modelTypes = [];

        foreach ($this->listOption('model') as $model) {
            $class = $this->resolveModel($model);

            if ($class === null) {
                $this->error(__('media.regenerate.unknown_model', ['model' => $model]));

                return self::FAILURE;
            }

            $modelTypes[] = $class;
        }

        $dryRun = (bool) $this->option('dry-run');

        if (! $dryRun && ! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        $summary = $regenerate->handle(new RegenerateConversionsData(
            modelTypes: $modelTypes,
            collections: $this->listOption('collection'),
            conversions: $this->listOption('conversion'),
            onlyMissing: (bool) $this->option('only-missing'),
            dryRun: $dryRun,
        ));

        foreach ($summary->failures as $mediaId => $message) {
            $this->warn(__('media.regenerate.failure', ['id' => $mediaId, 'message' => $message]));
        }

        $this->info(trans_choice(
            $dryRun ? 'media.regenerate.dry_run_summary' : 'media.regenerate.summary',
            $summary->conversions,
            ['media' => $summary->media, 'conversions' => $summary->conversions],
        ));

        return $summary->failures === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Un alias (`animal`) o una classe con media (HasMedia). Null se non esiste.
     *
     * @return class-string|null
     */
    private function resolveModel(string $model): ?string
    {
        foreach ([$model, 'App\\Models\\'.Str::studly($model)] as $class) {
            if (class_exists($class) && is_subclass_of($class, HasMedia::class)) {
                return $class;
            }
        }

        return null;
    }

    /**
     * Valori di un'opzione ripetibile, anche separati da virgole (`--collection=photos,avatar`).
     *
     * @return list<string>
     */
    private function listOption(string $name): array
    {
        $values = (array) $this->option($name);

        return array_values(array_filter(
            array_map(trim(...), explode(',', implode(',', array_map(strval(...), $values)))),
            static fn (string $value): bool => $value !== '',
        ));
    }
}
