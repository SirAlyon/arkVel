{{--
 | Styleguide · gruppo "media": picture, gallery e le conversioni immagine (UI-4).
 | Testi in lang/{it,en}/styleguide_media.php; JS della galleria in resources/js/ui/media.js.
 | Nella styleguide non c'è DB: le foto sono illustrazioni statiche già convertite in
 | public/images/styleguide/media (AVIF, WebP, JPEG a 480/960 px, zoom WebP, miniatura
 | sfocata per il placeholder). Il codice degli esempi mostra l'API con i Media.
 | Gli attributi data-demo servono ai test browser (tests/Browser/Components/MediaComponentsTest.php).
--}}
@php
    use App\Enums\ImageConversion;
    use App\Enums\ImageFormat;
    use App\Support\Media\GalleryImage;
    use App\Support\Media\ImageConversions;
    use App\Support\Media\ImageFormatSupport;
    use App\Support\Media\Picture;
    use App\Support\Media\ZoomImage;

    $t = static fn (string $key, array $replace = []): string => __('styleguide_media.'.$key, $replace);

    $asset = static fn (string $file): string => asset('images/styleguide/media/'.$file);

    /*
     | Placeholder come quello di medialibrary (vista media-library::placeholderSvg): la
     | miniatura sfocata da 32 px in un SVG con le proporzioni della foto, in data URI.
     */
    $placeholder = static fn (string $file, int $width, int $height): string => 'data:image/svg+xml;base64,'.base64_encode(
        view('media-library::placeholderSvg', [
            'originalImageWidth' => $width,
            'originalImageHeight' => $height,
            'tinyImageBase64' => 'data:image/jpeg;base64,'.base64_encode((string) file_get_contents(public_path('images/styleguide/media/'.$file))),
        ])->render()
    );

    $srcset = static fn (string $base, string $extension, array $widths): string => implode(', ', array_map(
        static fn (int $width): string => $asset("{$base}-{$width}.{$extension}").' '.$width.'w',
        $widths,
    ));

    $picture = static fn (string $base, array $widths, int $ratioWidth, int $ratioHeight): Picture => Picture::fromUrl(
        src: $asset($base.'-'.max($widths).'.jpg'),
        width: max($widths),
        height: intdiv(max($widths) * $ratioHeight, $ratioWidth),
        srcset: $srcset($base, 'jpg', $widths),
        sources: [
            ['type' => ImageFormat::Avif->mimeType(), 'srcset' => $srcset($base, 'avif', $widths)],
            ['type' => ImageFormat::Webp->mimeType(), 'srcset' => $srcset($base, 'webp', $widths)],
        ],
        placeholder: $placeholder($base.'-tiny.jpg', max($widths), intdiv(max($widths) * $ratioHeight, $ratioWidth)),
    );

    $scenes = ['dog', 'cat', 'rabbit', 'bird'];
    $heroes = array_combine($scenes, array_map(static fn (string $scene): Picture => $picture($scene.'-hero', [480, 960], 3, 2), $scenes));
    $card = $picture('dog-card', [320, 640], 4, 5);
    // Anteprima come quella di MediaPictures::preview(): una foto 3:2 nelle proporzioni di card.
    $preview = new Picture($asset('dog-hero-480.jpg'), ImageConversion::Card->width(), ImageConversion::Card->height(), preview: true);

    $gallery = array_map(static fn (string $scene): GalleryImage => new GalleryImage(
        slide: $heroes[$scene],
        thumb: $heroes[$scene],
        alt: $t('alt.'.$scene),
        zoom: new ZoomImage($asset($scene.'-zoom.webp'), 1200, 800),
    ), $scenes);

    $conversions = resolve(ImageConversions::class);
    $support = resolve(ImageFormatSupport::class);
    $supported = static fn (ImageFormat $format): string => $t($support->supports($format) ? 'conversions.supported' : 'conversions.unsupported');
@endphp
<x-styleguide.layout :title="__('styleguide.page_title', ['group' => __('styleguide.groups.media.title'), 'styleguide' => __('styleguide.title')])" current="media">
    <div class="flex flex-col gap-3 pt-4 md:pt-8">
        <h1 class="text-h1">{{ __('styleguide.groups.media.title') }}</h1>
        <p class="max-w-2xl text-lead text-fg-muted">{{ __('styleguide.groups.media.intro') }}</p>
    </div>

    {{-- ── Picture ───────────────────────────────────────────────────────── --}}
    <x-styleguide.component id="picture" :title="$t('picture.title')" :description="$t('picture.description')" :a11y="[$t('picture.a11y_alt'), $t('picture.a11y_cls'), $t('picture.a11y_priority')]">
        <x-styleguide.example :title="$t('picture.priority')">
            <x-slot:code>@verbatim<x-ui.picture
    :media="$animal->getFirstMedia('photos')"
    conversion="hero"
    :alt="$alt"
    sizes="(min-width: 1024px) 60vw, 100vw"
    priority
/>@endverbatim</x-slot:code>
            <div class="w-full max-w-3xl" data-demo="picture-priority">
                <x-ui.picture :picture="$heroes['dog']" :alt="$t('alt.dog')" sizes="(min-width: 1024px) 48rem, 100vw" priority />
            </div>
        </x-styleguide.example>

        <x-styleguide.example :title="$t('picture.formats')">
            <x-slot:code>@verbatim{{-- Con un Media: sorgenti, srcset, dimensioni e placeholder dalle conversioni generate. --}}
<x-ui.picture :media="$photo" conversion="card" :alt="$alt"
    sizes="(min-width: 1024px) 25vw, (min-width: 640px) 50vw, 100vw" />

{{-- Con URL statici. --}}
<x-ui.picture src="/dog-card-640.jpg" width="640" height="800" :alt="$alt"
    srcset="/dog-card-320.jpg 320w, /dog-card-640.jpg 640w"
    :sources="[['type' => 'image/avif', 'srcset' => '…'], ['type' => 'image/webp', 'srcset' => '…']]"
    :placeholder="$placeholderSvg" sizes="20rem" />@endverbatim</x-slot:code>
            <p class="w-full text-small text-fg-muted">{{ $t('picture.formats_hint') }}</p>
            <div class="w-full max-w-xs" data-demo="picture-formats">
                <x-ui.picture
                    :src="$card->src"
                    :width="$card->width"
                    :height="$card->height"
                    :srcset="$card->srcset"
                    :sources="$card->sources"
                    :placeholder="$card->placeholder"
                    :alt="$t('alt.dog')"
                    sizes="20rem"
                />
            </div>
        </x-styleguide.example>

        <x-styleguide.example :title="$t('picture.fill')" background="surface">
            <x-slot:code>@verbatim<div class="aspect-4/5 overflow-hidden rounded-media">
    <x-ui.picture :media="$photo" :alt="$alt" fill sizes="12rem" />
</div>@endverbatim</x-slot:code>
            <p class="w-full text-small text-fg-muted">{{ $t('picture.fill_hint') }}</p>
            <div class="grid w-full grid-cols-2 gap-4 sm:grid-cols-4" data-demo="picture-fill">
                @foreach ($scenes as $scene)
                    <div @class(['overflow-hidden rounded-media', $loop->even ? 'aspect-square' : 'aspect-4/5'])>
                        <x-ui.picture :picture="$heroes[$scene]" :alt="$t('alt.'.$scene)" fill sizes="(min-width: 640px) 12rem, 50vw" />
                    </div>
                @endforeach
            </div>
        </x-styleguide.example>

        <x-styleguide.example :title="$t('picture.preview')" background="surface">
            <x-slot:code>@verbatim{{-- Con un Media le cui conversioni sono ancora in coda: --}}
<x-ui.picture :media="$photo" conversion="card" :alt="$alt" />@endverbatim</x-slot:code>
            <p class="w-full text-small text-fg-muted">{{ $t('picture.preview_hint') }}</p>
            <div class="w-full max-w-xs" data-demo="picture-preview">
                <x-ui.picture :picture="$preview" :alt="$t('alt.dog')" sizes="20rem" />
            </div>
        </x-styleguide.example>

        <x-styleguide.example :title="$t('picture.fallback')">
            <x-slot:code>@verbatim<x-ui.picture :media="$animal->getFirstMedia('photos')" conversion="card" :alt="$alt" tone="peach" />

<x-ui.picture :media="null" conversion="hero" :alt="$alt">
    <x-ui.badge>{{ __('…') }}</x-ui.badge>
</x-ui.picture>@endverbatim</x-slot:code>
            <p class="w-full text-small text-fg-muted">{{ $t('picture.fallback_hint') }}</p>
            <div class="grid w-full grid-cols-2 gap-4 sm:grid-cols-4" data-demo="picture-fallback">
                @foreach (['sun', 'peach', 'mint'] as $tone)
                    <x-ui.picture :media="null" conversion="card" :alt="$t('alt.dog')" :tone="$tone" />
                @endforeach
                <x-ui.picture :media="null" conversion="card" :alt="$t('alt.dog')" tone="mint">
                    <x-ui.badge>{{ $t('picture.fallback_slot') }}</x-ui.badge>
                </x-ui.picture>
            </div>
        </x-styleguide.example>
    </x-styleguide.component>

    {{-- ── Gallery ───────────────────────────────────────────────────────── --}}
    <x-styleguide.component id="gallery" :title="$t('gallery.title')" :description="$t('gallery.description')" :a11y="[$t('gallery.a11y_region'), $t('gallery.a11y_keyboard'), $t('gallery.a11y_lightbox'), $t('gallery.a11y_alt'), $t('gallery.a11y_motion')]">
        <x-styleguide.example :title="$t('gallery.demo')">
            <x-slot:code>@verbatim<x-ui.gallery
    :media="$animal->getMedia('photos')"
    :name="$animal->name"
    sizes="(min-width: 1024px) 60vw, 100vw"
/>

{{-- Alt di una foto: custom property del Media, una stringa o una mappa per lingua. --}}
$media->setCustomProperty('alt', ['it' => '…', 'en' => '…'])->save();@endverbatim</x-slot:code>
            <p class="w-full text-small text-fg-muted">{{ $t('gallery.demo_hint') }}</p>
            <x-ui.gallery id="demo-gallery" :images="$gallery" :name="$t('demo_name')" sizes="(min-width: 1024px) 48rem, 100vw" class="w-full max-w-3xl" data-demo="gallery" />
        </x-styleguide.example>

        <x-styleguide.example :title="$t('gallery.single')">
            <x-slot:code>@verbatim<x-ui.gallery :media="$animal->getMedia('photos')" :name="$animal->name" />@endverbatim</x-slot:code>
            <p class="w-full text-small text-fg-muted">{{ $t('gallery.single_hint') }}</p>
            <x-ui.gallery id="demo-gallery-single" :images="[$gallery[1]]" :name="$t('demo_name')" sizes="(min-width: 640px) 24rem, 100vw" class="w-full max-w-sm" data-demo="gallery-single" />
        </x-styleguide.example>
    </x-styleguide.component>

    {{-- ── Conversioni ───────────────────────────────────────────────────── --}}
    <section id="conversions" aria-labelledby="conversions-title" class="flex scroll-mt-6 flex-col gap-6">
        <div class="flex flex-col gap-3">
            <h2 id="conversions-title" class="text-h2">{{ $t('conversions.title') }}</h2>
            <p class="max-w-3xl text-fg-muted">{{ $t('conversions.description') }}</p>
        </div>

        <div class="overflow-x-auto rounded-card bg-surface" role="region" aria-label="{{ $t('conversions.caption') }}" tabindex="0" data-demo="conversions">
            <table class="w-full min-w-xl text-left text-small">
                <caption class="sr-only">{{ $t('conversions.caption') }}</caption>
                <thead>
                    <tr class="border-b-2 border-line">
                        <th scope="col" class="px-5 py-3 font-semibold">{{ $t('conversions.name') }}</th>
                        <th scope="col" class="px-5 py-3 font-semibold">{{ $t('conversions.size') }}</th>
                        <th scope="col" class="px-5 py-3 font-semibold">{{ $t('conversions.use') }}</th>
                        <th scope="col" class="px-5 py-3 font-semibold">{{ $t('conversions.formats') }}</th>
                        <th scope="col" class="px-5 py-3 font-semibold">{{ $t('conversions.responsive') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach (ImageConversion::cases() as $conversion)
                        <tr class="border-b border-line last:border-b-0" data-conversion="{{ $conversion->value }}">
                            <th scope="row" class="px-5 py-3 font-semibold"><code>{{ $conversion->value }}</code></th>
                            <td class="px-5 py-3">
                                {{ $conversion->hasFixedDimensions() ? $conversion->width().' × '.$conversion->height() : $t('conversions.max', ['size' => $conversion->width()]) }}
                            </td>
                            <td class="px-5 py-3">{{ $t('conversions.use_'.$conversion->value) }}</td>
                            <td class="px-5 py-3">
                                {{ implode(', ', array_map(static fn (ImageFormat $format): string => $conversion->conversionName($format), $conversions->formatsFor($conversion))) }}
                            </td>
                            <td class="px-5 py-3">{{ $t($conversion->hasResponsiveImages() ? 'conversions.yes' : 'conversions.no') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <ul class="flex max-w-3xl list-disc flex-col gap-1 ps-5 text-small" data-demo="conversions-environment">
            <li>{{ $t('conversions.environment', ['driver' => config('media-library.image_driver'), 'avif' => $supported(ImageFormat::Avif), 'webp' => $supported(ImageFormat::Webp)]) }}</li>
            <li>{{ $t('conversions.regenerate') }} <code>php artisan arkvel:media:regenerate --only-missing</code></li>
        </ul>
    </section>
</x-styleguide.layout>
