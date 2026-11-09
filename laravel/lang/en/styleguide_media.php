<?php

declare(strict_types=1);

/*
 | Styleguide · "media" group (resources/views/styleguide/groups/media.blade.php).
 | The sample photos are static illustrations in public/images/styleguide/media.
 */

return [
    'alt' => [
        'dog' => 'Illustration: the head of a stylized dog on a yellow background, with a pale sun and a hill',
        'cat' => 'Illustration: the head of a stylized cat on a peach background, with a pale sun and a hill',
        'rabbit' => 'Illustration: a stylized rabbit on a mint green background, with a pale sun and a hill',
        'bird' => 'Illustration: a stylized little bird on a lavender background, with a pale sun and a hill',
    ],
    'demo_name' => 'Luna',

    'conversions' => [
        'title' => 'Conversions',
        'description' => 'The medialibrary conversions of public photos (App\Enums\ImageConversion), all queued. They sit next to the 400 px thumb, which stays for Filament and the current pages.',
        'caption' => 'Conversions registered on Animal (photos, avatar)',
        'name' => 'Name',
        'size' => 'Size',
        'use' => 'Used for',
        'formats' => 'Formats in this environment',
        'responsive' => 'Responsive images',
        'yes' => 'Yes',
        'no' => 'No',
        'max' => 'within :size px, uncropped',
        'use_card' => 'Catalog cards, gallery thumbnails',
        'use_hero' => 'Main photo and slides of the animal page',
        'use_og' => 'Open Graph preview (social)',
        'use_zoom' => 'Lightbox, full photo',
        'environment' => 'Image driver: :driver. AVIF: :avif. WebP: :webp. An unsupported format is not registered: the page falls back to WebP or JPEG on its own.',
        'supported' => 'supported',
        'unsupported' => 'not supported',
        'regenerate' => 'For existing media (filters: --model, --collection, --conversion, --dry-run):',
    ],

    'picture' => [
        'title' => 'Responsive image',
        'description' => 'A <picture> with an AVIF and a WebP <source> and a JPEG fallback <img>: the browser downloads the first format it can read, at the width sizes asks for. Declared width and height keep the proportions steady; the blurred placeholder is the background until the photo arrives, with no JavaScript.',
        'a11y_alt' => 'alt is required and translated: it describes the photo, not “photo of”. Use decorative (empty alt) only when the nearby text already says it all. With neither, the component throws.',
        'a11y_cls' => 'width and height are always declared, fallback included: no layout shift (CLS) while photos load.',
        'a11y_priority' => 'priority only on the LCP image (the first visible photo): high fetch priority and no lazy loading. Every other image is lazy.',
        'formats' => 'Formats and srcset',
        'formats_hint' => 'A 4:5 card in three formats and two widths. With a Media, media and conversion are enough: sources, srcset, size and placeholder come from the generated conversions.',
        'priority' => 'LCP image (priority)',
        'fill' => 'Fills its container (fill)',
        'fill_hint' => 'The container sets the proportions (here aspect-4/5 and 1:1); the image covers it with object-cover.',
        'preview' => 'Queued conversions (preview)',
        'preview_hint' => 'Until the queued conversions are ready, the synchronous thumb shows (here a 3:2 photo) in the conversion’s proportions (4:5), enforced with aspect-ratio and object-cover: no jump when the real photo arrives. Never the original, which weighs megabytes and may carry the GPS location.',
        'fallback' => 'No image',
        'fallback_hint' => 'media null: a colored block with the paw, in the conversion’s proportions. Three tones, like the cards; the slot replaces the paw.',
        'fallback_slot' => 'Photo coming soon',
    ],

    'gallery' => [
        'title' => 'Gallery',
        'description' => 'An Embla carousel with thumbnails and a PhotoSwipe lightbox (both MIT), downloaded only when a gallery initializes. Without JavaScript it stays a scrolling list of photos, each one a link to its largest version.',
        'a11y_region' => 'A region with aria-roledescription “carousel” and the name “Photos of <name>”; each slide is a group “n of N”. Slides out of view are inert: Tab only reaches the photo on show.',
        'a11y_keyboard' => 'Left and right arrows anywhere in the gallery; 44 px previous and next buttons (aria-disabled at the ends, so focus is never lost); thumbnails are buttons with aria-current; the counter is announced.',
        'a11y_lightbox' => 'Enter or Space on the photo opens the lightbox (a named modal dialog, focus trapped, Esc closes); on close, focus returns to the photo on show.',
        'a11y_alt' => 'One alt per photo: the Media’s alt custom property (per language too), otherwise “Photo n of N of <name>”. Never the same alt for all.',
        'a11y_motion' => 'With reduced motion, moving between photos and opening the lightbox are instant. Swiping always has the buttons as an alternative (WCAG 2.5.7).',
        'demo' => 'Animal page',
        'demo_hint' => 'Four static photos. On a real page, pass the Media of the photos collection.',
        'single' => 'A single photo',
        'single_hint' => 'No carousel or thumbnails: just the photo and its lightbox.',
    ],
];
