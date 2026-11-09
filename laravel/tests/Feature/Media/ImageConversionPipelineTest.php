<?php

declare(strict_types=1);

use App\Enums\ImageConversion;
use App\Enums\ImageFormat;
use App\Models\Animal;
use App\Support\Media\GalleryImages;
use App\Support\Media\ImageFormatSupport;
use App\Support\Media\MediaPictures;
use App\Support\Media\PictureSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\Conversions\Conversion;
use Spatie\MediaLibrary\Conversions\Jobs\PerformConversionsJob;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/*
 | Pipeline delle immagini (UI-4) su un Animal: conversioni in coda, file generati,
 | dati di rendering per x-ui.picture e x-ui.gallery. I formati moderni attesi sono quelli
 | del GD del container (con libwebp/libavif nel Dockerfile: AVIF e WebP).
 */

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('public');
});

function animalPhoto(Animal $animal, string $collection = 'photos', int $width = 1600, int $height = 1200): Media
{
    return $animal->addMedia(UploadedFile::fake()->image('luna.jpg', $width, $height))->toMediaCollection($collection);
}

/**
 * @return list<string>
 */
function expectedModernTypes(): array
{
    $support = app(ImageFormatSupport::class);

    return array_values(array_map(
        static fn (ImageFormat $format): string => $format->mimeType(),
        array_filter([ImageFormat::Avif, ImageFormat::Webp], $support->supports(...)),
    ));
}

it('queues the new conversions on upload and runs only the thumb right away', function (): void {
    Queue::fake();

    $media = animalPhoto(Animal::factory()->create());

    Queue::assertPushed(PerformConversionsJob::class, function (PerformConversionsJob $job): bool {
        /** @var Collection<int, Conversion> $conversions */
        $conversions = (fn () => $this->conversions)->call($job);
        $names = $conversions->map(static fn (Conversion $conversion): string => $conversion->getName())->all();

        return in_array('card', $names, true)
            && in_array('hero', $names, true)
            && in_array('og', $names, true)
            && ! in_array('thumb', $names, true);
    });

    expect($media->refresh()->hasGeneratedConversion('thumb'))->toBeTrue()
        ->and($media->hasGeneratedConversion('card'))->toBeFalse();
});

it('shows the synchronous thumb, never the original, while the queued conversions are pending', function (): void {
    Queue::fake();

    $media = animalPhoto(Animal::factory()->create())->refresh();
    $pictures = app(MediaPictures::class);
    $picture = $pictures->picture($media, ImageConversion::Card);

    expect($picture)->not->toBeNull()
        ->and($picture?->src)->toBe($media->getUrl('thumb'))
        ->and($picture?->src)->not->toBe($media->getUrl())
        ->and($picture?->preview)->toBeTrue()
        ->and($picture?->srcset)->toBeNull()
        ->and($picture?->sources)->toBe([])
        ->and($picture?->placeholder)->toBeNull()
        ->and([$picture?->width, $picture?->height])->toBe([800, 1000])
        ->and($pictures->zoom($media))->toBeNull();
});

it('opens the thumb in the lightbox with its real size while nothing else is ready', function (): void {
    Queue::fake();

    $media = animalPhoto(Animal::factory()->create(), width: 1600, height: 1200)->refresh();
    $lightbox = app(MediaPictures::class)->lightbox($media);

    // thumb: Fit::Max entro 400×400, proporzioni della foto.
    expect($lightbox?->src)->toBe($media->getUrl('thumb'))
        ->and([$lightbox?->width, $lightbox?->height])->toBe([400, 300])
        ->and($lightbox?->srcset)->toBeNull();
});

it('opens hero, then card, in the lightbox until zoom is ready', function (): void {
    Queue::fake();

    $media = animalPhoto(Animal::factory()->create())->refresh();
    $pictures = app(MediaPictures::class);

    $media->markAsConversionGenerated('card');
    expect($pictures->lightbox($media)?->src)->toBe($media->getUrl('card'))
        ->and([$pictures->lightbox($media)?->width, $pictures->lightbox($media)?->height])->toBe([800, 1000]);

    $media->markAsConversionGenerated('hero');
    expect($pictures->lightbox($media)?->src)->toBe($media->getUrl('hero'))
        ->and([$pictures->lightbox($media)?->width, $pictures->lightbox($media)?->height])->toBe([1536, 1024]);
});

it('has nothing to show without the thumb either', function (): void {
    Queue::fake();

    $animal = Animal::factory()->create();
    $media = animalPhoto($animal)->refresh();
    $media->markAsConversionNotGenerated('thumb');

    expect(app(MediaPictures::class)->picture($media, ImageConversion::Hero))->toBeNull()
        ->and(app(MediaPictures::class)->lightbox($media))->toBeNull()
        ->and(app(GalleryImages::class)->fromMedia([$media], 'Luna'))->toBe([]);
});

it('does not upscale the thumb of a small photo', function (): void {
    Queue::fake();

    $media = animalPhoto(Animal::factory()->create(), width: 300, height: 200)->refresh();

    expect(array_slice((array) getimagesize($media->getPath('thumb')), 0, 2))->toBe([300, 200]);
});

it('generates card, hero and og with responsive images and a blurred placeholder', function (): void {
    $media = animalPhoto(Animal::factory()->create())->refresh();
    $pictures = app(MediaPictures::class);

    $card = $pictures->picture($media, ImageConversion::Card);
    $hero = $pictures->picture($media, ImageConversion::Hero);

    expect($media->hasGeneratedConversion('og'))->toBeTrue()
        ->and($media->hasResponsiveImages('og'))->toBeFalse()
        ->and($card->src)->toBe($media->getUrl('card'))
        ->and([$card->width, $card->height])->toBe([800, 1000])
        ->and([$hero->width, $hero->height])->toBe([1536, 1024])
        ->and($card->srcset)->toMatch('/^\S+ \d+w(, \S+ \d+w)*$/')
        ->and($card->srcset)->not->toContain('data:')
        ->and($card->placeholder)->toStartWith('data:image/svg+xml;base64,')
        ->and(array_map(static fn (PictureSource $source): string => $source->type, $card->sources))->toBe(expectedModernTypes());

    Storage::disk('public')->assertExists($media->getPathRelativeToRoot('card'));
    Storage::disk('public')->assertExists($media->getPathRelativeToRoot('og'));
});

it('writes AVIF and WebP files when the image driver supports them', function (ImageFormat $format): void {
    if (! app(ImageFormatSupport::class)->supports($format)) {
        $this->markTestSkipped("The image driver in this environment cannot write {$format->value}.");
    }

    $media = animalPhoto(Animal::factory()->create())->refresh();
    $name = ImageConversion::Hero->conversionName($format);

    expect($media->hasGeneratedConversion($name))->toBeTrue()
        ->and($media->getPathRelativeToRoot($name))->toEndWith('.'.$format->value)
        ->and(mime_content_type($media->getPath($name)))->toBe($format->mimeType());
})->with([ImageFormat::Avif, ImageFormat::Webp]);

it('keeps the whole photo for the lightbox, without upscaling it', function (): void {
    $media = animalPhoto(Animal::factory()->create(), 'avatar', 1200, 900)->refresh();
    $zoom = app(MediaPictures::class)->zoom($media);

    expect($zoom)->not->toBeNull()
        ->and([$zoom?->width, $zoom?->height])->toBe([1200, 900])
        ->and($zoom?->srcset)->toContain('1200w');
});

it('refuses to render the zoom conversion as a fixed-size picture', function (): void {
    Queue::fake();

    app(MediaPictures::class)->picture(animalPhoto(Animal::factory()->create()), ImageConversion::Zoom);
})->throws(InvalidArgumentException::class);

it('gives every gallery photo its own alt text', function (): void {
    Queue::fake();
    app()->setLocale('en');

    $animal = Animal::factory()->create(['name' => 'Luna']);
    animalPhoto($animal)->setCustomProperty('alt', 'Luna asleep on the sofa')->save();
    animalPhoto($animal)->setCustomProperty('alt', ['it' => 'Luna in giardino', 'en' => 'Luna in the garden'])->save();
    animalPhoto($animal)->setCustomProperty('alt', ['it' => 'Solo in italiano'])->save();
    animalPhoto($animal);

    $images = app(GalleryImages::class)->fromMedia($animal->getMedia('photos'), 'Luna');

    expect(array_map(static fn ($image): string => $image->alt, $images))->toBe([
        'Luna asleep on the sofa',
        'Luna in the garden',
        'Photo 3 of 4 of Luna',
        'Photo 4 of 4 of Luna',
    ]);
});

it('falls back to the app fallback locale for a per-language alt', function (): void {
    Queue::fake();
    app()->setLocale('de');
    config()->set('app.fallback_locale', 'en');

    $animal = Animal::factory()->create();
    animalPhoto($animal)->setCustomProperty('alt', ['en' => 'Luna in the garden'])->save();

    expect(app(GalleryImages::class)->fromMedia($animal->getMedia('photos'), 'Luna')[0]->alt)->toBe('Luna in the garden');
});
