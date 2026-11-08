<?php

declare(strict_types=1);

use Tests\Support\Ui\BrowserScripts;

/*
 | Componenti x-ui del gruppo "media" nel browser: accessibilità (axe), carosello da
 | tastiera e con i bottoni, lightbox PhotoSwipe (apertura con Invio/Spazio, focus dentro il
 | dialog e restituito alla chiusura), nessun overflow a 320 px, librerie scaricate solo dove
 | serve. Pagina di prova: /_styleguide/media (solo local).
 | Gira SOLO nel servizio `browser-tests`: `docker compose --profile testing run --rm browser-tests`.
 */

beforeEach(function (): void {
    app()->detectEnvironment(static fn (): string => 'local');
});

/**
 * Promessa che si risolve a true appena `$condition` è vera (o a false dopo 5 s): la
 * galleria si inizializza dopo gli import dinamici, la lightbox dopo la sua animazione.
 * Polling con setTimeout: in una pagina headless senza focus requestAnimationFrame può
 * non arrivare mai, e page.evaluate resterebbe appesa senza timeout.
 */
function untilTrue(string $condition): string
{
    return <<<JS
        function () {
            return new Promise((resolve) => {
                const start = performance.now();
                const tick = () => {
                    if ({$condition}) { return resolve(true); }
                    if (performance.now() - start > 5000) { return resolve(false); }
                    setTimeout(tick, 50);
                };
                tick();
            });
        }
        JS;
}

const GALLERY = '[data-demo="gallery"]';

function galleryReady(): string
{
    return untilTrue('document.querySelector(\'[data-demo="gallery"]\')?.hasAttribute("data-ready")');
}

function slideLink(int $index): string
{
    return GALLERY.' [data-gallery-slide]:nth-child('.($index + 1).') [data-gallery-zoom]';
}

/** Indice della slide che contiene il focus, -1 se il focus è altrove. */
function focusedSlide(): string
{
    return '(() => { const slide = document.activeElement?.closest(\'[data-demo="gallery"] [data-gallery-slide]\'); return slide ? [...slide.parentElement.children].indexOf(slide) : -1; })()';
}

function inertSlides(): string
{
    return '[...document.querySelectorAll(\'[data-demo="gallery"] [data-gallery-slide]\')].map((slide) => slide.inert).join(",")';
}

/** Nomi dei file scaricati che corrispondono a `$pattern`. */
function loadedAssets(string $pattern): string
{
    return 'performance.getEntriesByType("resource").some((entry) => /'.$pattern.'/.test(entry.name))';
}

it('has no accessibility issues in light and dark mode', function (): void {
    visit(route('styleguide.group', 'media'))->withLocale('it-IT')
        ->assertScript(galleryReady(), true)
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();

    visit(route('styleguide.group', 'media'))->inDarkMode()->withLocale('it-IT')
        ->assertScript(galleryReady(), true)
        ->assertNoAccessibilityIssues();
});

it('keeps every gallery control at least 44 px and the page within 320 px', function (): void {
    $components = '[data-styleguide-component]';

    visit(route('styleguide.group', 'media'))->withLocale('it-IT')
        ->assertScript(galleryReady(), true)
        ->assertScript(BrowserScripts::undersizedTargets($components), []);

    visit(route('styleguide.group', 'media'))->withLocale('it-IT')->on()->mobile()
        ->assertScript(galleryReady(), true)
        ->assertScript(BrowserScripts::undersizedTargets($components), [])
        ->resize(320, 800)
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true);
});

it('downloads Embla and the PhotoSwipe lightbox only where a gallery is, and the PhotoSwipe core only on open', function (): void {
    visit(route('styleguide.group', 'media'))->withLocale('it-IT')
        ->assertScript(galleryReady(), true)
        ->assertScript(loadedAssets('embla-carousel'), true)
        ->assertScript(loadedAssets('photoswipe-lightbox'), true)
        ->assertScript(loadedAssets('photoswipe\\.esm'), false)
        ->click(slideLink(0))
        ->assertScript(untilTrue('document.querySelector(".pswp--open") !== null'), true)
        ->assertScript(loadedAssets('photoswipe\\.esm'), true);

    visit(route('styleguide.group', 'actions'))->withLocale('it-IT')
        ->assertScript('document.readyState === "complete"', true)
        ->assertScript(loadedAssets('embla|photoswipe'), false);
});

it('moves between photos with the buttons, keeping the hidden slides inert', function (): void {
    visit(route('styleguide.group', 'media'))->withLocale('it-IT')
        ->assertScript(galleryReady(), true)
        ->assertVisible(GALLERY.' [data-gallery-controls]')
        ->assertAttribute(GALLERY.' [data-gallery-previous]', 'aria-disabled', 'true')
        ->assertAttribute(GALLERY.' [data-gallery-next]', 'aria-disabled', 'false')
        ->assertScript(inertSlides(), 'false,true,true,true')
        ->click(GALLERY.' [data-gallery-next]')
        ->assertScript(inertSlides(), 'true,false,true,true')
        ->assertAttribute(GALLERY.' [data-gallery-thumb="1"]', 'aria-current', 'true')
        ->assertAttribute(GALLERY.' [data-gallery-thumb="0"]', 'aria-current', 'false')
        ->assertSeeIn(GALLERY.' [aria-live]', 'Foto 2 di 4')
        ->click(GALLERY.' [data-gallery-thumb="3"]')
        ->assertScript(inertSlides(), 'true,true,true,false')
        ->assertAttribute(GALLERY.' [data-gallery-next]', 'aria-disabled', 'true')
        // All'estremo il bottone resta focalizzabile (aria-disabled) e non fa nulla. Niente
        // click(): Playwright aspetterebbe all'infinito che un aria-disabled torni attivo.
        ->keys(GALLERY.' [data-gallery-next]', ['Enter'])
        ->assertScript(inertSlides(), 'true,true,true,false')
        ->assertScript('document.activeElement?.hasAttribute("data-gallery-next")', true);
});

it('moves with the arrow keys and keeps the focus on the photo or thumbnail on show', function (): void {
    visit(route('styleguide.group', 'media'))->withLocale('it-IT')
        ->assertScript(galleryReady(), true)
        ->keys(slideLink(0), ['ArrowRight'])
        ->assertScript(focusedSlide(), 1)
        ->assertScript(inertSlides(), 'true,false,true,true')
        ->keys(slideLink(1), ['ArrowRight'])
        ->assertScript(focusedSlide(), 2)
        ->keys(slideLink(2), ['ArrowLeft'])
        ->assertScript(focusedSlide(), 1)
        ->keys(GALLERY.' [data-gallery-thumb="1"]', ['ArrowRight'])
        ->assertScript(BrowserScripts::activeElement('data-gallery-thumb'), '2')
        ->assertAttribute(GALLERY.' [data-gallery-thumb="2"]', 'aria-current', 'true')
        ->assertScript(inertSlides(), 'true,true,false,true');
});

it('opens the lightbox with Enter or Space and gives the focus back to the photo on show', function (string $key): void {
    visit(route('styleguide.group', 'media'))->withLocale('it-IT')
        ->assertScript(galleryReady(), true)
        ->keys(slideLink(0), [$key])
        ->assertScript(untilTrue('document.querySelector(".pswp--open") !== null && document.querySelector(".pswp").contains(document.activeElement)'), true)
        ->assertAttribute('.pswp', 'role', 'dialog')
        ->assertAttribute('.pswp', 'aria-modal', 'true')
        ->assertAttribute('.pswp', 'aria-label', 'Foto di Luna a schermo intero')
        ->assertAttribute('.pswp__button--close', 'aria-label', 'Chiudi')
        // Il primo .pswp__img è il segnaposto di PhotoSwipe (alt vuoto, role presentation).
        ->assertScript(untilTrue('[...document.querySelectorAll(".pswp__img")].some((image) => image.alt.startsWith("Illustrazione: la testa di un cane"))'), true)
        ->keys('.pswp', ['ArrowRight'])
        ->assertScript(untilTrue('window.pswp?.currIndex === 1'), true)
        ->keys('.pswp', ['Escape'])
        ->assertScript(untilTrue('document.querySelector(".pswp") === null'), true)
        ->assertScript(untilTrue(focusedSlide().' === 1'), true)
        ->assertScript(inertSlides(), 'true,false,true,true')
        ->assertAttribute(GALLERY.' [data-gallery-thumb="1"]', 'aria-current', 'true');
})->with(['Enter', 'Space']);

it('opens a single photo in the lightbox without carousel controls', function (): void {
    visit(route('styleguide.group', 'media'))->withLocale('it-IT')
        ->assertScript(galleryReady(), true)
        ->assertMissing('[data-demo="gallery-single"] [data-gallery-controls]')
        ->keys('[data-demo="gallery-single"] [data-gallery-zoom]', ['Enter'])
        ->assertScript(untilTrue('document.querySelector(".pswp--open") !== null && document.querySelector(".pswp").contains(document.activeElement)'), true)
        ->keys('.pswp', ['Escape'])
        ->assertScript(untilTrue('document.activeElement?.closest(\'[data-demo="gallery-single"]\') !== null'), true);
});

it('shows the placeholder and declared size before the photo loads', function (): void {
    visit(route('styleguide.group', 'media'))->withLocale('it-IT')
        ->assertScript('getComputedStyle(document.querySelector(\'[data-demo="picture-formats"] img\')).backgroundImage.startsWith("url(\\"data:image/svg+xml")', true)
        ->assertScript('document.querySelector(\'[data-demo="picture-priority"] img\').fetchPriority === "high"', true)
        ->assertScript('Math.round(document.querySelector(\'[data-demo="picture-formats"] img\').getBoundingClientRect().height * 640) === Math.round(document.querySelector(\'[data-demo="picture-formats"] img\').getBoundingClientRect().width * 800)', true);
});

it('keeps the declared proportions of a preview photo once it has loaded, on mobile and desktop', function (bool $mobile): void {
    $image = 'document.querySelector(\'[data-demo="picture-preview"] img\')';
    $page = visit(route('styleguide.group', 'media'))->withLocale('it-IT');

    if ($mobile) {
        $page = $page->on()->mobile();
    }

    // L'anteprima è una foto 3:2 dichiarata 4:5 (800×1000): l'aspect-ratio imposto e
    // object-cover la tengono 4:5 anche dopo il caricamento, quindi nessun salto.
    $page
        ->assertScript(untilTrue("({$image}.scrollIntoView({ block: 'center' }), {$image}.complete && {$image}.naturalWidth === 480)"), true)
        ->assertScript("Math.abs({$image}.getBoundingClientRect().height * 800 - {$image}.getBoundingClientRect().width * 1000) < 1000", true)
        ->assertScript("getComputedStyle({$image}).objectFit", 'cover');
})->with(['mobile' => true, 'desktop' => false]);
