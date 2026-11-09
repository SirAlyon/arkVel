<?php

declare(strict_types=1);

use App\Actions\Media\RegenerateImageConversions;
use App\Data\Media\RegenerateConversionsData;
use App\Models\Animal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\Conversions\Conversion;
use Spatie\MediaLibrary\Conversions\FileManipulator;
use Spatie\MediaLibrary\Conversions\Jobs\PerformConversionsJob;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/*
 | arkvel:media:regenerate → App\Actions\Media\RegenerateImageConversions.
 | Le conversioni nuove sono in coda: con Queue::fake() si verificano i job accodati.
 */

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('public');
    Queue::fake();

    $this->animal = Animal::factory()->create();
    $this->photo = $this->animal->addMedia(UploadedFile::fake()->image('luna.jpg', 900, 900))->toMediaCollection('photos');
    $this->avatar = $this->animal->addMedia(UploadedFile::fake()->image('luna-avatar.jpg', 900, 900))->toMediaCollection('avatar');

    // Solo i job accodati dal comando, non quelli dell'upload.
    Queue::fake();
});

/**
 * Nomi delle conversioni accodate per un media.
 *
 * @return list<string>
 */
function queuedConversionNames(Media $media): array
{
    $names = [];

    Queue::assertPushed(PerformConversionsJob::class, function (PerformConversionsJob $job) use ($media, &$names): bool {
        $jobMedia = (fn () => $this->media)->call($job);

        if ($jobMedia->is($media)) {
            $names = (fn () => $this->conversions)->call($job)
                ->map(static fn (Conversion $conversion): string => $conversion->getName())
                ->values()
                ->all();
        }

        return true;
    });

    return $names;
}

/**
 * Le conversioni in coda attese per un media (tutte tranne la thumb, sincrona).
 *
 * @return list<string>
 */
function expectedQueuedNames(?string $prefix = null): array
{
    $animal = new Animal;
    $animal->registerAllMediaConversions();

    return collect($animal->mediaConversions)
        ->filter(static fn (Conversion $conversion): bool => $conversion->shouldBeQueued())
        ->map(static fn (Conversion $conversion): string => $conversion->getName())
        ->filter(static fn (string $name): bool => $prefix === null || $name === $prefix || str_starts_with($name, $prefix.'-'))
        ->values()
        ->all();
}

it('queues every image conversion of every media and regenerates the thumb right away', function (): void {
    $thumb = $this->photo->getPath('thumb');
    unlink($thumb);

    $this->artisan('arkvel:media:regenerate')
        ->expectsOutputToContain('Rigenerazione avviata')
        ->assertSuccessful();

    Queue::assertPushed(PerformConversionsJob::class, 2);
    expect(queuedConversionNames($this->photo))->toBe(expectedQueuedNames())
        ->and(file_exists($thumb))->toBeTrue();
});

it('queues only the conversions not generated yet', function (): void {
    $this->artisan('arkvel:media:regenerate', ['--only-missing' => true])->assertSuccessful();

    expect(queuedConversionNames($this->photo))->toBe(expectedQueuedNames())
        ->not->toContain('thumb');
});

it('skips the media whose conversions are all generated', function (): void {
    $names = array_map(static fn (Conversion $conversion): string => $conversion->getName(), (function (): array {
        $animal = new Animal;
        $animal->registerAllMediaConversions();

        return $animal->mediaConversions;
    })());

    foreach ($names as $name) {
        $this->photo->markAsConversionGenerated($name);
        $this->avatar->markAsConversionGenerated($name);
    }

    $this->artisan('arkvel:media:regenerate', ['--only-missing' => true])
        ->expectsOutputToContain('Nessuna conversione da rigenerare')
        ->assertSuccessful();

    Queue::assertNothingPushed();
});

it('filters by model and collection', function (string $model): void {
    $this->artisan('arkvel:media:regenerate', ['--model' => [$model], '--collection' => ['photos'], '--only-missing' => true])
        ->assertSuccessful();

    Queue::assertPushed(PerformConversionsJob::class, 1);
    expect(queuedConversionNames($this->photo))->not->toBe([]);
})->with(['alias' => 'animal', 'class' => Animal::class]);

it('expands a base conversion name to all its formats', function (): void {
    $this->artisan('arkvel:media:regenerate', ['--conversion' => ['card,og']])->assertSuccessful();

    expect(queuedConversionNames($this->photo))->toEqualCanonicalizing([...expectedQueuedNames('card'), 'og']);
});

it('counts without queueing anything on a dry run', function (): void {
    $perMedia = count(expectedQueuedNames());

    $this->artisan('arkvel:media:regenerate', ['--dry-run' => true, '--only-missing' => true])
        ->expectsOutputToContain('Prova: si rigenererebbero '.(2 * $perMedia).' conversioni su 2 media.')
        ->assertSuccessful();

    Queue::assertNothingPushed();
});

it('counts every conversion on a dry run without --only-missing, even the generated ones', function (): void {
    $perMedia = count(expectedQueuedNames()) + 1; // + la thumb, già generata

    $this->artisan('arkvel:media:regenerate', ['--dry-run' => true])
        ->expectsOutputToContain('Prova: si rigenererebbero '.(2 * $perMedia).' conversioni su 2 media.')
        ->assertSuccessful();

    Queue::assertNothingPushed();
});

it('asks for confirmation in production and stops when declined', function (): void {
    app()->detectEnvironment(static fn (): string => 'production');

    $this->artisan('arkvel:media:regenerate')
        ->expectsConfirmation('Are you sure you want to run this command?', 'no')
        ->assertFailed();

    Queue::assertNothingPushed();
});

it('runs in production without asking when forced', function (): void {
    app()->detectEnvironment(static fn (): string => 'production');

    $this->artisan('arkvel:media:regenerate', ['--force' => true, '--collection' => ['photos']])
        ->assertSuccessful();

    Queue::assertPushed(PerformConversionsJob::class, 1);
});

it('rejects a model that does not exist or has no media', function (string $model): void {
    $this->artisan('arkvel:media:regenerate', ['--model' => [$model]])
        ->expectsOutputToContain('Model sconosciuto o senza media: '.$model)
        ->assertFailed();

    Queue::assertNothingPushed();
})->with(['missing' => 'unicorn', 'without media' => 'organization']);

it('keeps going after a failing media and reports it', function (): void {
    $this->mock(FileManipulator::class)
        ->shouldReceive('createDerivedFiles')
        ->twice()
        ->andThrow(new RuntimeException('disk full'));

    $this->artisan('arkvel:media:regenerate', ['--force' => true])
        ->expectsOutputToContain('Media '.$this->photo->getKey().': disk full')
        ->expectsOutputToContain('Media '.$this->avatar->getKey().': disk full')
        ->assertFailed();
});

it('returns a summary of media, conversions and failures', function (): void {
    $summary = app(RegenerateImageConversions::class)->handle(new RegenerateConversionsData(
        collections: ['avatar'],
        conversions: ['hero'],
    ));

    expect($summary->media)->toBe(1)
        ->and($summary->conversions)->toBe(count(expectedQueuedNames('hero')))
        ->and($summary->failures)->toBe([]);
});
