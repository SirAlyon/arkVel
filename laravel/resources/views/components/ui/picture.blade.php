{{--
 | Immagine responsive: <picture> con un <source> per formato moderno (AVIF, WebP) e l'<img>
 | JPEG di fallback, srcset dalle responsive images di medialibrary, `width`/`height`
 | dichiarati (proporzioni stabili, nessun CLS) e placeholder sfocato come sfondo dell'<img>
 | finché la foto carica (nessun JS). Dati: App\Support\Media\{MediaPictures, Picture}.
 |
 | Sorgente, in alternativa:
 | - `media` (Media|null) + `conversion` (card · hero · og, default card): legge le
 |   conversioni generate (App\Enums\ImageConversion); finché quelle in coda non sono pronte
 |   mostra l'anteprima `thumb` con le proporzioni della conversione (aspect-ratio
 |   esplicito + object-cover, nessun CLS). Mai l'originale (pesante, e con i metadati
 |   della foto). `media` null, o senza neanche la thumb → fallback;
 | - `picture`: un App\Support\Media\Picture già pronto (lo usa x-ui.gallery);
 | - `src` + `width` + `height` (+ `srcset`, `sources` [['type' => 'image/avif',
 |   'srcset' => '…'], …], `placeholder`): URL statici.
 |
 | - `alt` obbligatorio (descrive la foto, tradotto); `decorative` per alt="" quando il
 |   testo vicino dice già tutto. Senza nessuno dei due: eccezione;
 | - `sizes` (default 100vw): la larghezza mostrata, per far scegliere al browser il file
 |   giusto dello srcset (es. "(min-width: 1024px) 25vw, (min-width: 640px) 50vw, 100vw");
 | - `priority`: per l'immagine LCP (la prima foto visibile): fetchpriority="high" e
 |   caricamento immediato. Le altre sono lazy (`loading`, default lazy) e decodificate in
 |   modo asincrono. Al massimo una per pagina;
 | - `fill`: l'immagine riempie il contenitore (size-full + object-cover), che ne decide le
 |   proporzioni (es. aspect-4/5 nella card). Senza, è larga quanto il contenitore e alta
 |   secondo width/height;
 | - `rounded` (default true): angoli `rounded-media`;
 | - `tone` (sun · peach · mint) e slot: il fallback senza immagine. Di default una zampa
 |   decorativa su un blocco colorato, con le proporzioni della conversione.
 |
 | <x-ui.picture :media="$animal->getFirstMedia('photos')" conversion="hero"
 |     :alt="__('…', ['name' => $animal->name])" sizes="(min-width: 1024px) 60vw, 100vw" priority />
 | <x-ui.picture :media="$photo" :alt="$alt" fill class="aspect-4/5"
 |     sizes="(min-width: 1024px) 25vw, (min-width: 640px) 50vw, 100vw" />
--}}
@props([
    'media' => null,
    'conversion' => 'card',
    'picture' => null,
    'src' => null,
    'srcset' => null,
    'sources' => [],
    'placeholder' => null,
    'width' => null,
    'height' => null,
    'alt' => null,
    'decorative' => false,
    'sizes' => '100vw',
    'priority' => false,
    'loading' => 'lazy',
    'fill' => false,
    'rounded' => true,
    'tone' => 'sun',
])

@php
    use App\Enums\ImageConversion;
    use App\Support\Media\MediaPictures;
    use App\Support\Media\Picture;
    use Spatie\MediaLibrary\MediaCollections\Models\Media;

    if (! $decorative && blank($alt)) {
        throw new InvalidArgumentException('<x-ui.picture> needs an alt text, or `decorative` when the image adds nothing to the nearby text.');
    }

    $preset = ImageConversion::from($conversion);

    $resolved = match (true) {
        $picture instanceof Picture => $picture,
        $media instanceof Media => app(MediaPictures::class)->picture($media, $preset),
        filled($src) => Picture::fromUrl(
            src: $src,
            width: (int) $width,
            height: (int) $height,
            srcset: $srcset,
            sources: $sources,
            placeholder: $placeholder,
        ),
        default => null,
    };

    $tones = [
        // Come nella card animale: in dark block-sun coincide con surface.
        'sun' => 'bg-block-sun text-on-block-sun dark:bg-surface-raised',
        'peach' => 'bg-block-peach text-on-block-peach',
        'mint' => 'bg-block-mint text-on-block-mint',
    ];
    $toneClasses = $tones[$tone] ?? $tones['sun'];
@endphp

@if ($resolved !== null)
    <picture
        data-ui-picture
        @if ($resolved->preview) data-picture-preview @endif
        {{ $attributes->class([
            'block overflow-hidden',
            'size-full' => $fill,
            'rounded-media' => $rounded,
        ]) }}
    >
        @foreach ($resolved->sources as $source)
            <source type="{{ $source->type }}" srcset="{{ $source->srcset }}" sizes="{{ $sizes }}">
        @endforeach
        <img
            src="{{ $resolved->src }}"
            @if ($resolved->srcset) srcset="{{ $resolved->srcset }}" sizes="{{ $sizes }}" @endif
            alt="{{ $decorative ? '' : $alt }}"
            width="{{ $resolved->width }}"
            height="{{ $resolved->height }}"
            @if ($priority)
                fetchpriority="high"
                loading="eager"
            @else
                loading="{{ $loading }}"
                decoding="async"
            @endif
            {{-- L'anteprima (thumb) ha proporzioni sue: quelle dichiarate si impongono via CSS.
                 Il placeholder è un data URI (Picture lo verifica). --}}
            @if ($resolved->preview && $resolved->placeholder)
                style="aspect-ratio: {{ $resolved->width }} / {{ $resolved->height }}; background-image: url('{{ $resolved->placeholder }}')"
            @elseif ($resolved->preview)
                style="aspect-ratio: {{ $resolved->width }} / {{ $resolved->height }}"
            @elseif ($resolved->placeholder)
                style="background-image: url('{{ $resolved->placeholder }}')"
            @endif
            @class([
                'block object-cover bg-cover bg-center bg-no-repeat',
                'size-full' => $fill,
                'h-auto w-full' => ! $fill,
                'bg-surface' => $resolved->placeholder === null,
            ])
        >
    </picture>
@else
    {{-- Nessuna immagine: un blocco con le stesse proporzioni, così il layout non cambia
         quando la foto arriva. La zampa è decorativa: il soggetto lo nomina il contesto. --}}
    <div
        data-ui-picture
        data-picture-fallback
        @unless ($fill) style="aspect-ratio: {{ $width ?? $preset->width() }} / {{ $height ?? $preset->height() }}" @endunless
        {{ $attributes->class([
            'flex items-center justify-center overflow-hidden',
            'size-full' => $fill,
            'w-full' => ! $fill,
            'rounded-media' => $rounded,
            $toneClasses,
        ]) }}
    >
        @if ($slot->isNotEmpty())
            {{ $slot }}
        @else
            {{ svg('lucide-paw-print', 'h-auto w-1/4 min-w-6 max-w-16 shrink-0', ['aria-hidden' => 'true', 'focusable' => 'false', 'data-icon' => 'paw-print']) }}
        @endif
    </div>
@endif
