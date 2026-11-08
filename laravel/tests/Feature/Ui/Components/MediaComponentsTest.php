<?php

declare(strict_types=1);

use App\Models\Animal;
use App\Support\Media\GalleryImage;
use App\Support\Media\Picture;
use App\Support\Media\ZoomImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\ViewException;

/*
 | Componenti x-ui del gruppo "media": picture, gallery. Test di rendering (markup, ARIA,
 | proporzioni, priorità). Comportamento nel browser:
 | tests/Browser/Components/MediaComponentsTest.php.
 */

uses(RefreshDatabase::class);

function samplePicture(string $name = 'luna', ?string $placeholder = 'data:image/svg+xml;base64,AAA'): Picture
{
    return Picture::fromUrl(
        src: "/img/{$name}-960.jpg",
        width: 960,
        height: 640,
        srcset: "/img/{$name}-480.jpg 480w, /img/{$name}-960.jpg 960w",
        sources: [
            ['type' => 'image/avif', 'srcset' => "/img/{$name}-480.avif 480w, /img/{$name}-960.avif 960w"],
            ['type' => 'image/webp', 'srcset' => "/img/{$name}-480.webp 480w, /img/{$name}-960.webp 960w"],
        ],
        placeholder: $placeholder,
    );
}

/**
 * @return list<GalleryImage>
 */
function sampleGallery(int $count = 3): array
{
    return array_map(static fn (int $index): GalleryImage => new GalleryImage(
        slide: samplePicture('slide'.$index),
        thumb: samplePicture('thumb'.$index),
        alt: 'Foto '.$index.' di Luna, diversa',
        zoom: $index === 1 ? null : new ZoomImage('/img/zoom'.$index.'.webp', 1200, 800, '/img/zoom'.$index.'.webp 1200w'),
    ), range(1, $count));
}

describe('picture', function (): void {
    it('renders AVIF and WebP sources before the JPEG fallback, with srcset and sizes', function (): void {
        $html = (string) $this->blade('<x-ui.picture :picture="$picture" alt="Luna sul divano" sizes="(min-width: 1024px) 50vw, 100vw" />', ['picture' => samplePicture()]);

        expect($html)
            ->toMatch('/<picture[^>]*data-ui-picture[^>]*>\s*<source type="image\/avif" srcset="\/img\/luna-480.avif 480w, \/img\/luna-960.avif 960w" sizes="\(min-width: 1024px\) 50vw, 100vw">\s*<source type="image\/webp"/')
            ->toContain('src="/img/luna-960.jpg"')
            ->toContain('srcset="/img/luna-480.jpg 480w, /img/luna-960.jpg 960w" sizes="(min-width: 1024px) 50vw, 100vw"')
            ->toContain('alt="Luna sul divano"')
            ->toContain('width="960"')
            ->toContain('height="640"')
            ->toContain('rounded-media');
    });

    it('lazy loads by default and decodes asynchronously', function (): void {
        $html = (string) $this->blade('<x-ui.picture :picture="$picture" alt="Luna" />', ['picture' => samplePicture()]);

        expect($html)
            ->toContain('loading="lazy"')
            ->toContain('decoding="async"')
            ->not->toContain('fetchpriority');
    });

    it('loads the LCP image eagerly with high priority', function (): void {
        $html = (string) $this->blade('<x-ui.picture :picture="$picture" alt="Luna" priority />', ['picture' => samplePicture()]);

        expect($html)
            ->toContain('fetchpriority="high"')
            ->toContain('loading="eager"')
            ->not->toContain('loading="lazy"')
            ->not->toContain('decoding="async"');
    });

    it('paints the blurred placeholder as the image background', function (): void {
        $this->blade('<x-ui.picture :picture="$picture" alt="Luna" />', ['picture' => samplePicture()])
            ->assertSee("style=\"background-image: url('data:image/svg+xml;base64,AAA')\"", escape: false)
            ->assertSee('bg-cover bg-center bg-no-repeat', escape: false);

        $this->blade('<x-ui.picture :picture="$picture" alt="Luna" />', ['picture' => samplePicture(placeholder: null)])
            ->assertDontSee('background-image', escape: false)
            ->assertSee('bg-surface', escape: false);
    });

    it('renders static URLs with declared dimensions and no srcset when there is none', function (): void {
        $html = (string) $this->blade('<x-ui.picture src="/img/luna.jpg" width="400" height="500" alt="Luna" />');

        expect($html)
            ->toContain('src="/img/luna.jpg"')
            ->toContain('width="400"')
            ->toContain('height="500"')
            ->not->toContain('srcset')
            ->not->toContain('sizes=')
            ->not->toContain('<source');
    });

    it('fills its container or keeps its own proportions', function (): void {
        $fill = (string) $this->blade('<x-ui.picture :picture="$picture" alt="Luna" fill :rounded="false" />', ['picture' => samplePicture()]);
        $fluid = (string) $this->blade('<x-ui.picture :picture="$picture" alt="Luna" />', ['picture' => samplePicture()]);

        expect($fill)->toMatch('/<img[^>]*class="[^"]*size-full/')->not->toContain('rounded-media')
            ->and($fluid)->toMatch('/<img[^>]*class="[^"]*h-auto w-full/');
    });

    it('renders an empty alt for a decorative image', function (): void {
        $this->blade('<x-ui.picture :picture="$picture" decorative />', ['picture' => samplePicture()])
            ->assertSee('alt=""', escape: false);
    });

    it('requires an alt text unless the image is decorative', function (): void {
        $this->blade('<x-ui.picture :picture="$picture" />', ['picture' => samplePicture()]);
    })->throws(ViewException::class, 'needs an alt text');

    it('shows a decorative paw with the conversion proportions when there is no media', function (): void {
        $html = (string) $this->blade('<x-ui.picture :media="null" conversion="hero" alt="Luna" tone="peach" />');

        expect($html)
            ->toContain('data-picture-fallback')
            ->toContain('style="aspect-ratio: 1536 / 1024"')
            ->toContain('bg-block-peach text-on-block-peach')
            ->toContain('data-icon="paw-print"')
            ->toContain('aria-hidden="true"')
            ->not->toContain('<img');
    });

    it('replaces the paw with the slot in the fallback', function (): void {
        $this->blade('<x-ui.picture :media="null" alt="Luna">Foto in arrivo</x-ui.picture>')
            ->assertSee('Foto in arrivo')
            ->assertSee('style="aspect-ratio: 800 / 1000"', escape: false)
            ->assertDontSee('data-icon="paw-print"', escape: false);
    });

    it('renders a medialibrary photo from its generated conversions', function (): void {
        Storage::fake('public');

        $media = Animal::factory()->create()
            ->addMedia(UploadedFile::fake()->image('luna.jpg', 1000, 1000))
            ->toMediaCollection('photos')
            ->refresh();

        $html = (string) $this->blade('<x-ui.picture :media="$media" conversion="card" alt="Luna" />', ['media' => $media]);

        expect($html)
            ->toContain('src="'.$media->getUrl('card').'"')
            ->toContain('width="800"')
            ->toContain('height="1000"')
            ->toMatch('/srcset="[^"]+ \d+w"/')
            ->toContain('background-image: url(\'data:image/svg+xml;base64,');
    });

    it('shows the thumb with the conversion proportions, never the original, while the conversions are queued', function (): void {
        Storage::fake('public');
        Queue::fake();

        $media = Animal::factory()->create()
            ->addMedia(UploadedFile::fake()->image('luna.jpg', 1000, 1000))
            ->toMediaCollection('photos')
            ->refresh();

        $html = (string) $this->blade('<x-ui.picture :media="$media" conversion="hero" alt="Luna" />', ['media' => $media]);

        expect($html)
            ->toContain('data-picture-preview')
            ->toContain('src="'.$media->getUrl('thumb').'"')
            ->not->toContain('src="'.$media->getUrl().'"')
            ->toContain('width="1536"')
            ->toContain('height="1024"')
            ->toContain('style="aspect-ratio: 1536 / 1024"')
            ->toMatch('/<img[^>]*class="[^"]*object-cover/')
            ->not->toContain('srcset');
    });

    it('shows the paw when not even the thumb exists', function (): void {
        Storage::fake('public');
        Queue::fake();

        $media = Animal::factory()->create()
            ->addMedia(UploadedFile::fake()->image('luna.jpg', 1000, 1000))
            ->toMediaCollection('photos')
            ->refresh();
        $media->markAsConversionNotGenerated('thumb');

        $this->blade('<x-ui.picture :media="$media" conversion="card" alt="Luna" />', ['media' => $media])
            ->assertSee('data-picture-fallback', escape: false)
            ->assertSee('style="aspect-ratio: 800 / 1000"', escape: false)
            ->assertDontSee('<img', escape: false);
    });

    it('rejects a placeholder that is not an inline image', function (): void {
        Picture::fromUrl('/img/luna.jpg', 10, 10, placeholder: 'https://example.com/x.svg');
    })->throws(InvalidArgumentException::class, 'data:image/');
});

describe('gallery', function (): void {
    it('renders an APG carousel region with labelled slides', function (): void {
        $html = (string) $this->blade('<x-ui.gallery id="luna" :images="$images" name="Luna" />', ['images' => sampleGallery()]);

        expect($html)
            ->toContain('id="luna"')
            ->toContain('aria-label="Foto di Luna"')
            ->toContain('aria-roledescription="carosello"')
            ->toContain('x-data="uiGallery(')
            ->toContain('role="group"')
            ->toContain('aria-roledescription="foto"')
            ->toContain('aria-label="1 di 3"')
            ->toContain('aria-label="3 di 3"')
            ->toMatch('/<ul role="list" data-gallery-container/');
    });

    it('keeps a distinct alt on every photo', function (): void {
        $html = (string) $this->blade('<x-ui.gallery :images="$images" name="Luna" />', ['images' => sampleGallery()]);

        expect($html)
            ->toContain('alt="Foto 1 di Luna, diversa"')
            ->toContain('alt="Foto 2 di Luna, diversa"')
            ->toContain('alt="Foto 3 di Luna, diversa"');
    });

    it('links each slide to its full photo for the lightbox, or to the slide itself', function (): void {
        $html = (string) $this->blade('<x-ui.gallery :images="$images" name="Luna" />', ['images' => sampleGallery()]);

        expect($html)
            ->toMatch('/href="\/img\/slide1-960.jpg"\s+data-gallery-zoom\s+data-pswp-width="960"\s+data-pswp-height="640"\s+data-pswp-srcset="\/img\/slide1-480.jpg 480w, \/img\/slide1-960.jpg 960w"/')
            ->toMatch('/href="\/img\/zoom2.webp"\s+data-gallery-zoom\s+data-pswp-width="1200"\s+data-pswp-height="800"/')
            ->toContain('x-on:keydown.space.prevent="open(1)"')
            ->toContain('(apri a schermo intero)');
    });

    it('marks only the first slide as LCP when asked', function (): void {
        $html = (string) $this->blade('<x-ui.gallery :images="$images" name="Luna" priority />', ['images' => sampleGallery()]);

        expect(substr_count($html, 'fetchpriority="high"'))->toBe(1)
            ->and($html)->toMatch('/src="\/img\/slide1-960.jpg"[^>]*fetchpriority="high"/');
    });

    it('renders hidden controls and thumbnail buttons, enabled by JavaScript', function (): void {
        $html = (string) $this->blade('<x-ui.gallery :images="$images" name="Luna" />', ['images' => sampleGallery()]);

        expect($html)
            ->toMatch('/data-gallery-controls hidden x-bind:hidden="!ready"/')
            ->toMatch('/data-gallery-thumbs hidden x-bind:hidden="!ready"/')
            ->toContain('aria-label="Foto precedente"')
            ->toContain('aria-label="Foto successiva"')
            ->toContain('x-bind:aria-disabled="atStart ? \'true\' : \'false\'"')
            ->toContain('aria-label="Scegli una foto"')
            ->toMatch('/<button\s+type="button"\s+data-gallery-thumb="0"\s+aria-label="Mostra la foto 1 di 3"\s+aria-current="true"/')
            ->toMatch('/data-gallery-thumb="1"\s+aria-label="Mostra la foto 2 di 3"\s+aria-current="false"/')
            ->toContain('aria-live="polite"')
            ->toContain('Foto 1 di 3')
            ->toContain('alt=""')
            ->toContain('sizes="4rem"');
    });

    it('has no carousel controls with a single photo', function (): void {
        $html = (string) $this->blade('<x-ui.gallery :images="$images" name="Luna" />', ['images' => sampleGallery(1)]);

        expect($html)
            ->toContain('data-gallery-zoom')
            ->not->toContain('data-gallery-controls')
            ->not->toContain('data-gallery-thumbs');
    });

    it('renders nothing without photos', function (): void {
        expect(trim((string) $this->blade('<x-ui.gallery :images="[]" name="Luna" />')))->toBe('');
    });

    it('builds the photos from medialibrary with fallback alts, from the thumb while queued', function (): void {
        Storage::fake('public');
        Queue::fake();

        $animal = Animal::factory()->create(['name' => 'Luna']);
        $first = $animal->addMedia(UploadedFile::fake()->image('a.jpg', 900, 600))->toMediaCollection('photos');
        $animal->addMedia(UploadedFile::fake()->image('b.jpg', 900, 600))->toMediaCollection('photos');

        $html = (string) $this->blade('<x-ui.gallery :media="$media" :name="$name" />', ['media' => $animal->getMedia('photos'), 'name' => $animal->name]);

        // Lightbox e link senza JS: la thumb (Fit::Max entro 400 px) con le sue dimensioni vere.
        expect($html)
            ->toContain('alt="Foto 1 di 2 di Luna"')
            ->toContain('alt="Foto 2 di 2 di Luna"')
            ->toMatch('/href="'.preg_quote($first->getUrl('thumb'), '/').'"\s+data-gallery-zoom\s+data-pswp-width="400"\s+data-pswp-height="267"/')
            ->toContain('style="aspect-ratio: 1536 / 1024"');
    });

    it('never links or shows the original file, queued or converted', function (bool $queued): void {
        Storage::fake('public');

        if ($queued) {
            Queue::fake();
        }

        $animal = Animal::factory()->create(['name' => 'Luna']);
        $media = $animal->addMedia(UploadedFile::fake()->image('luna.jpg', 1200, 900))->toMediaCollection('photos')->refresh();
        $animal->addMedia(UploadedFile::fake()->image('luna-2.jpg', 1200, 900))->toMediaCollection('photos');

        $html = (string) $this->blade('<x-ui.gallery :media="$media" name="Luna" /><x-ui.picture :media="$photo" alt="Luna" /><x-ui.picture :media="$photo" conversion="hero" alt="Luna" />', [
            'media' => $animal->getMedia('photos'),
            'photo' => $media,
        ]);

        expect($html)->not->toMatch('/["\s,]'.preg_quote($media->getUrl(), '/').'["\s]/')
            ->and($html)->toContain($media->getUrl($queued ? 'thumb' : 'card'));
    })->with(['queued' => true, 'converted' => false]);

    it('uses the light thumb, without a placeholder, for the thumbnail buttons', function (): void {
        Storage::fake('public');

        $animal = Animal::factory()->create(['name' => 'Luna']);
        $media = $animal->addMedia(UploadedFile::fake()->image('a.jpg', 900, 600))->toMediaCollection('photos')->refresh();
        $animal->addMedia(UploadedFile::fake()->image('b.jpg', 900, 600))->toMediaCollection('photos');

        $html = (string) $this->blade('<x-ui.gallery :media="$media" name="Luna" />', ['media' => $animal->getMedia('photos')]);
        preg_match('/<button[^>]*data-gallery-thumb="0".*?<\/button>/s', $html, $thumb);

        expect($thumb[0] ?? '')
            ->toContain('src="'.$media->getUrl('thumb').'"')
            ->not->toContain('background-image')
            ->not->toContain('srcset')
            ->not->toContain($media->getUrl('card'));
    });

    it('requires its own lightbox image for a preview slide', function (): void {
        new GalleryImage(
            slide: new Picture('/img/thumb.jpg', 1536, 1024, preview: true),
            thumb: samplePicture(),
            alt: 'Luna',
        );
    })->throws(InvalidArgumentException::class, 'lightbox image');

    it('is translated in English', function (): void {
        app()->setLocale('en');

        $this->blade('<x-ui.gallery :images="$images" name="Luna" />', ['images' => sampleGallery(2)])
            ->assertSee('aria-label="Photos of Luna"', escape: false)
            ->assertSee('aria-roledescription="carousel"', escape: false)
            ->assertSee('aria-label="1 of 2"', escape: false)
            ->assertSee('aria-label="Next photo"', escape: false)
            ->assertSee('Photos of Luna, full screen');
    });
});

describe('styleguide page', function (): void {
    it('documents picture, gallery and the conversions in the local environment', function (): void {
        app()->detectEnvironment(static fn (): string => 'local');

        $this->get(route('styleguide.group', 'media'))
            ->assertOk()
            ->assertSee('aria-current="page"', escape: false)
            ->assertSeeInOrder(['id="picture"', 'id="gallery"', 'id="conversions"'], escape: false)
            ->assertSee('images/styleguide/media/dog-hero-960.avif', escape: false)
            ->assertSee('data-conversion="og"', escape: false)
            ->assertSee('arkvel:media:regenerate --only-missing');
    });

    it('stays hidden outside the local environment', function (): void {
        app()->detectEnvironment(static fn (): string => 'production');

        $this->get('/_styleguide/media')->assertNotFound();
    });
});
