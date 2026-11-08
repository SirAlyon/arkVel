<?php

declare(strict_types=1);

use App\Enums\ImageConversion;
use App\Enums\ImageFormat;
use App\Models\Animal;
use App\Support\Media\ImageConversions;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\Conversions\Conversion;
use Tests\Support\Media\FakeImageFormatSupport;

/**
 * Le conversioni registrate sul model Animal, per nome.
 *
 * @return array<string, Conversion>
 */
function animalConversions(): array
{
    $animal = new Animal;
    $animal->registerAllMediaConversions();

    return collect($animal->mediaConversions)
        ->keyBy(static fn (Conversion $conversion): string => $conversion->getName())
        ->all();
}

it('registers every format on card and hero, and one format on og and zoom, with AVIF and WebP', function (): void {
    FakeImageFormatSupport::bind(ImageFormat::Avif, ImageFormat::Webp);

    expect(array_keys(animalConversions()))->toBe([
        'thumb',
        'card', 'hero', 'og',
        'card-webp', 'hero-webp', 'zoom-webp',
        'card-avif', 'hero-avif',
    ]);
});

it('runs every JPEG first and AVIF last, so a failing AVIF cannot block the fallback', function (): void {
    FakeImageFormatSupport::bind(ImageFormat::Avif, ImageFormat::Webp);

    $names = array_keys(animalConversions());
    $lastJpeg = max(array_map(static fn (string $name): int|false => array_search($name, $names, true), ['card', 'hero', 'og']));
    $firstModern = min(array_map(static fn (string $name): int|false => array_search($name, $names, true), ['card-webp', 'card-avif', 'hero-avif']));

    expect($lastJpeg)->toBeLessThan($firstModern);
});

it('registers only the requested presets', function (): void {
    FakeImageFormatSupport::bind(ImageFormat::Webp);

    $animal = new Animal;
    app(ImageConversions::class)->register($animal, ['logo'], ImageConversion::Card, ImageConversion::Og);

    expect(array_map(static fn (Conversion $conversion): string => $conversion->getName(), $animal->mediaConversions))
        ->toBe(['card', 'og', 'card-webp'])
        ->and($animal->mediaConversions[0]->getPerformOnCollections())->toBe(['logo']);
});

it('degrades to WebP without AVIF and to JPEG without either', function (): void {
    FakeImageFormatSupport::bind(ImageFormat::Webp);

    expect(array_keys(animalConversions()))->toBe(['thumb', 'card', 'hero', 'og', 'card-webp', 'hero-webp', 'zoom-webp']);

    FakeImageFormatSupport::bind();

    expect(array_keys(animalConversions()))->toBe(['thumb', 'card', 'hero', 'og', 'zoom']);
});

it('queues every new conversion and keeps the thumb synchronous', function (): void {
    FakeImageFormatSupport::bind(ImageFormat::Avif, ImageFormat::Webp);

    $conversions = animalConversions();

    expect($conversions['thumb']->shouldBeQueued())->toBeFalse();

    foreach (array_diff_key($conversions, ['thumb' => true]) as $conversion) {
        expect($conversion->shouldBeQueued())->toBeTrue()
            ->and($conversion->getPerformOnCollections())->toBe(['photos', 'avatar']);
    }
});

it('configures size, format, quality and responsive images from the enum', function (string $name, string $extension, int $quality, bool $responsive, array $fit): void {
    FakeImageFormatSupport::bind(ImageFormat::Avif, ImageFormat::Webp);

    $conversion = animalConversions()[$name];

    expect($conversion->getResultExtension('png'))->toBe($extension)
        ->and($conversion->getManipulations()->getFirstManipulationArgument('quality'))->toBe($quality)
        ->and($conversion->shouldGenerateResponsiveImages())->toBe($responsive)
        ->and($conversion->getManipulations()->getManipulationArgument('fit'))->toBe($fit);
})->with([
    'card avif' => ['card-avif', 'avif', 55, true, [Fit::Crop, 800, 1000]],
    'card jpeg' => ['card', 'jpg', 82, true, [Fit::Crop, 800, 1000]],
    'hero webp' => ['hero-webp', 'webp', 78, true, [Fit::Crop, 1536, 1024]],
    'og' => ['og', 'jpg', 82, false, [Fit::Crop, 1200, 630]],
    'zoom' => ['zoom-webp', 'webp', 78, true, [Fit::Max, 2048, 2048]],
]);

it('reports the formats generated in this environment', function (): void {
    FakeImageFormatSupport::bind(ImageFormat::Avif);

    $conversions = app(ImageConversions::class);

    expect($conversions->formatsFor(ImageConversion::Card))->toBe([ImageFormat::Avif, ImageFormat::Jpeg])
        ->and($conversions->formatsFor(ImageConversion::Zoom))->toBe([ImageFormat::Jpeg])
        ->and($conversions->formatsFor(ImageConversion::Og))->toBe([ImageFormat::Jpeg]);
});
