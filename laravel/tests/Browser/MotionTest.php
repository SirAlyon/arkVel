<?php

declare(strict_types=1);

use App\Enums\Species;
use App\Models\Animal;
use App\Models\Organization;
use App\Models\User;
use App\Support\Site\AnimalPhotoTransition;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // La styleguide risponde solo in ambiente local.
    app()->detectEnvironment(static fn (): string => 'local');
    // Il reveal, spento negli altri test browser (tests/Pest.php), qui è il soggetto.
    config(['arkvel.reveal' => true]);
});

/*
 | Motion (UI-7) nel browser: reveal allo scroll (mai sopra la piega, niente nascosto senza
 | JS o con reduced-motion, niente CLS), cuore dei preferiti animato con Motion solo con il
 | movimento consentito, foto che vola dalla card alla scheda (View Transition di
 | wire:navigate) e ritorno con Indietro, accessibilità della pagina «motion» della
 | styleguide. Rendering: tests/Feature/Ui/Components/MotionComponentsTest.php e
 | tests/Feature/Public/MotionPagesTest.php. Gira SOLO nel servizio `browser-tests`.
 */

/** Ripete la condizione JS (ogni 25 ms, al massimo $timeout ms) e restituisce l'ultimo esito. */
function motionEventually(string $condition, int $timeout = 4000): string
{
    return 'function () { return new Promise((resolve) => { const end = Date.now() + '.$timeout.'; const check = () => { let ok = false; try { ok = Boolean('.$condition.'); } catch (error) { ok = false; } if (ok || Date.now() > end) { resolve(ok); } else { setTimeout(check, 25); } }; check(); }); }';
}

/** Registra gli elementi animati via WAAPI (Motion usa element.animate()) e i fotogrammi delle icone. */
const MOTION_RECORD_ANIMATIONS = '(() => { window.__animated = []; window.__frames = []; const original = Element.prototype.animate; Element.prototype.animate = function (...args) { const tag = this.tagName.toLowerCase(); window.__animated.push(tag); if (tag === "svg") { window.__frames.push(JSON.stringify(args[0])); } return original.apply(this, args); }; return true; })()';

/** Registra le View Transitions e i gruppi animati (quelli con una foto in partenza e in arrivo). */
const MOTION_RECORD_TRANSITIONS = '(() => { window.__vt = []; const original = document.startViewTransition.bind(document); document.startViewTransition = (options) => { const transition = original(options); const record = { groups: [], error: null }; window.__vt.push(record); transition.ready.then(() => { record.groups = [...new Set(document.getAnimations().map((animation) => animation.effect?.pseudoElement ?? "").filter((name) => name.startsWith("::view-transition-group(animal-photo")))]; }).catch((error) => { record.error = String(error); }); return transition; }; return true; })()';

/** Avvia il conteggio dei layout shift senza input recente da qui in poi (window.__shift). */
const MOTION_WATCH_SHIFTS = '(() => { window.__shift = 0; new PerformanceObserver((list) => { for (const entry of list.getEntries()) { if (!entry.hadRecentInput) { window.__shift += entry.value; } } }).observe({ type: "layout-shift" }); return true; })()';

/** Layout shift accumulati da MOTION_WATCH_SHIFTS, arrotondati al millesimo. */
const MOTION_SHIFTS = 'Math.round(window.__shift * 1000) / 1000';

/** Elementi x-reveal in vista ma non del tutto visibili (deve restare 0). */
const MOTION_HIDDEN_IN_VIEW = '[...document.querySelectorAll("[x-reveal]")].filter((element) => { const box = element.getBoundingClientRect(); return box.top < innerHeight && box.bottom > 0 && element.getAttribute("data-reveal-state") === "pending"; }).length';

/** @return list<Animal> */
function seedMotionAnimals(): array
{
    $organization = Organization::factory()->verified()->create(['city' => 'Bologna', 'region' => 'Emilia-Romagna']);

    return array_map(
        static fn (array $spec): Animal => Animal::factory()->available()->forOrganization($organization)->create(['name' => $spec[0], 'species' => $spec[1], 'featured' => false]),
        [['Rex', Species::Dog], ['Argo', Species::Dog], ['Micia', Species::Cat], ['Fiocco', Species::Other]],
    );
}

it('reveals the blocks below the fold on scroll and never hides what is already in view', function (string $device): void {
    $page = visit(route('styleguide.group', 'motion'))->withLocale('it-IT');
    $page = $device === 'mobile' ? $page->on()->mobile() : $page->resize(1280, 700);

    $page->assertScript('window.matchMedia("(prefers-reduced-motion: reduce)").matches', false)
        ->assertScript(motionEventually('document.querySelector("[data-demo-reveal=eight]").getAttribute("data-reveal-state") === "pending"'), true)
        ->assertScript(MOTION_HIDDEN_IN_VIEW, 0)
        ->assertScript('getComputedStyle(document.querySelector("[data-demo-reveal=eight]")).opacity', '0')
        ->assertScript(MOTION_WATCH_SHIFTS, true)
        ->assertScript('(() => { document.querySelector("[data-demo=reveal-grid]").scrollIntoView({ block: "center" }); return true; })()', true)
        // Entrano, poi lo stato sparisce e restano solo gli stili del componente.
        ->assertScript(motionEventually('[...document.querySelectorAll("[data-demo-reveal]")].filter((element) => { const box = element.getBoundingClientRect(); return box.top < innerHeight && box.bottom > 0; }).every((element) => !element.hasAttribute("data-reveal-state") && getComputedStyle(element).opacity === "1")', 3000), true)
        ->assertScript(MOTION_HIDDEN_IN_VIEW, 0)
        ->wait(0.3)
        ->assertScript(MOTION_SHIFTS, 0)
        ->assertNoJavaScriptErrors();
})->with(['desktop', 'mobile']);

it('shows a hidden block at once when it receives the keyboard focus', function (): void {
    visit(route('styleguide.group', 'motion'))->withLocale('it-IT')->resize(1280, 700)
        ->assertScript(motionEventually('document.querySelector("[data-demo-reveal=eight]").getAttribute("data-reveal-state") === "pending"'), true)
        ->assertScript('(() => { const block = document.querySelector("[data-demo-reveal=eight]"); block.setAttribute("tabindex", "-1"); block.focus({ preventScroll: true }); return getComputedStyle(block).opacity; })()', '1');
});

it('hides and animates nothing with reduced motion', function (): void {
    visit(route('styleguide.group', 'motion'), ['reducedMotion' => 'reduce'])->withLocale('it-IT')->resize(1280, 700)
        ->assertScript('window.matchMedia("(prefers-reduced-motion: reduce)").matches', true)
        ->wait(0.5)
        ->assertScript('document.querySelectorAll("[data-reveal-state]").length', 0)
        ->assertScript('getComputedStyle(document.querySelector("[data-demo-reveal=eight]")).opacity', '1')
        ->assertScript(MOTION_RECORD_ANIMATIONS, true)
        ->click('[data-demo="favorite"]')
        ->assertAttribute('[data-demo="favorite"]', 'aria-pressed', 'true')
        ->wait(0.4)
        ->assertScript('window.__animated.length', 0)
        ->assertNoJavaScriptErrors();
});

it('hides nothing when the configuration turns the reveal off, even in an automated browser', function (): void {
    config(['arkvel.reveal' => false]);

    visit(route('styleguide.group', 'motion'))->withLocale('it-IT')->resize(1280, 700)
        ->assertScript('document.documentElement.dataset.reveal', 'off')
        ->wait(0.5)
        ->assertScript('document.querySelectorAll("[data-reveal-state]").length', 0)
        ->assertScript('getComputedStyle(document.querySelector("[data-demo-reveal=eight]")).opacity', '1');
});

it('reveals in an automated browser too: production code does not detect it', function (): void {
    visit(route('styleguide.group', 'motion'))->withLocale('it-IT')->resize(1280, 700)
        ->assertScript('navigator.webdriver', true)
        ->assertScript('document.documentElement.hasAttribute("data-reveal")', false)
        ->assertScript(motionEventually('document.querySelector("[data-demo-reveal=eight]").getAttribute("data-reveal-state") === "pending"'), true);
});

it('hides nothing without JavaScript', function (): void {
    visit(route('styleguide.group', 'motion'), ['javaScriptEnabled' => false])->withLocale('it-IT')->resize(1280, 700)
        ->assertScript('document.querySelectorAll("[data-reveal-state]").length', 0)
        ->assertScript('getComputedStyle(document.querySelector("[data-demo-reveal=eight]")).opacity', '1');
});

it('pops the heart of the styleguide with Motion while the state stays in aria-pressed', function (): void {
    visit(route('styleguide.group', 'motion'))->withLocale('it-IT')
        ->assertScript(MOTION_RECORD_ANIMATIONS, true)
        ->hover('[data-demo="favorite"]')
        ->click('[data-demo="favorite"]')
        ->assertAttribute('[data-demo="favorite"]', 'aria-pressed', 'true')
        ->assertScript(motionEventually('window.__animated.includes("svg")', 3000), true)
        ->click('[data-demo="favorite"]')
        ->assertAttribute('[data-demo="favorite"]', 'aria-pressed', 'false')
        ->assertScript(motionEventually('window.__animated.filter((tag) => tag === "svg").length === 2', 3000), true)
        // Regressione: con x-on:click di Alpine lo stato cambiava prima della lettura e
        // l'animazione andava al contrario. Aggiunto: pop da 0.6; tolto: rimbalzo da 0.85.
        ->assertScript('window.__frames[0].includes("0.6") && !window.__frames[0].includes("0.85")', true)
        ->assertScript('window.__frames[1].includes("0.85")', true)
        ->assertNoJavaScriptErrors();
});

it('pops the heart of a card when a signed-in adopter saves the animal', function (): void {
    seedMotionAnimals();
    $this->actingAs(User::factory()->create());

    $favorite = '[data-home-grid] li:first-child [data-favorite]';

    visit(route('home'))->withLocale('it-IT')
        ->assertScript(MOTION_RECORD_ANIMATIONS, true)
        ->assertScript('(() => { document.querySelector("[data-home-grid]").scrollIntoView({ block: "center" }); return true; })()', true)
        ->click($favorite)
        ->assertScript(motionEventually('window.__animated.includes("svg")', 3000), true)
        ->assertScript(motionEventually('document.querySelector("'.$favorite.'").getAttribute("aria-pressed") === "true"', 5000), true)
        ->assertNoJavaScriptErrors();
});

it('does not pop the heart of a guest, who is sent to the login', function (): void {
    seedMotionAnimals();

    $favorite = '[data-home-grid] li:first-child [data-favorite]';

    visit(route('home'))->withLocale('it-IT')
        ->assertScript(MOTION_RECORD_ANIMATIONS, true)
        ->assertScript('(() => { document.querySelector("[data-home-grid]").scrollIntoView({ block: "center" }); return true; })()', true)
        ->assertPresent($favorite.'[data-favorite-guest]')
        ->hover($favorite)
        ->click($favorite)
        ->assertScript(motionEventually('location.pathname === '.json_encode(parse_url(route('login'), PHP_URL_PATH)), 6000), true)
        ->wait(0.4)
        ->assertScript('window.__animated.filter((tag) => tag === "svg").length', 0)
        ->assertNoJavaScriptErrors();
});

it('moves the photo from the card to the profile and back, only for the animal opened', function (string $device): void {
    $animals = seedMotionAnimals();

    $page = visit(route('home'))->withLocale('it-IT');
    $page = $device === 'mobile' ? $page->on()->mobile() : $page->resize(1280, 900);

    $link = '[data-home-grid] li:first-child [data-card-link]';

    $page = $page->assertScript('typeof document.startViewTransition', 'function')
        ->assertScript(MOTION_RECORD_TRANSITIONS, true)
        ->assertScript('(() => { document.querySelector("[data-home-grid]").scrollIntoView({ block: "center" }); return true; })()', true)
        ->wait(0.6)
        ->click($link)
        ->assertScript(motionEventually('/^\/animali\/\d+$/.test(location.pathname) && document.querySelector("[data-animal-detail]") !== null', 6000), true)
        ->assertScript(motionEventually('window.__vt.length === 1 && (window.__vt[0].groups.length > 0 || window.__vt[0].error !== null)', 3000), true);

    $opened = (int) $page->script('Number(location.pathname.split("/").pop())');
    $name = AnimalPhotoTransition::name($opened);

    expect(collect($animals)->pluck('id')->all())->toContain($opened);

    $page->assertScript('window.__vt[0].error', null)
        ->assertScript('window.__vt[0].groups', ['::view-transition-group('.$name.')'])
        // Nella scheda la foto dell'animale è il soggetto (la galleria o la foto di riserva).
        ->assertScript('document.querySelector("[data-animal-gallery] [data-photo-transition]").getAttribute("wire:transition.navigate")', $name)
        ->wait(0.6)
        ->back()
        ->assertScript(motionEventuallyPath('/'), true)
        ->assertScript(motionEventually('window.__vt.length === 2 && (window.__vt[1].groups.length > 0 || window.__vt[1].error !== null)', 3000), true)
        ->assertScript('window.__vt[1].error', null)
        ->assertScript('window.__vt[1].groups', ['::view-transition-group('.$name.')'])
        ->assertNoJavaScriptErrors();
})->with(['desktop', 'mobile']);

/** Attende che la pagina corrente sia `$path` dopo uno scambio di wire:navigate. */
function motionEventuallyPath(string $path): string
{
    return motionEventually('location.pathname === '.json_encode($path).' && document.querySelector("[data-home]") !== null', 6000);
}

it('introduces no layout shift while the home reveals its sections', function (): void {
    seedMotionAnimals();

    visit(route('home'))->withLocale('it-IT')->on()->mobile()
        ->assertScript(motionEventually('document.querySelectorAll("[data-reveal-state=pending]").length > 0'), true)
        ->assertScript(MOTION_HIDDEN_IN_VIEW, 0)
        ->assertScript(MOTION_WATCH_SHIFTS, true)
        ->assertScript('(async () => { for (let y = 0; y < document.body.scrollHeight; y += 300) { window.scrollTo(0, y); await new Promise((resolve) => setTimeout(resolve, 60)); } return true; })()', true)
        ->wait(1)
        ->assertScript(MOTION_HIDDEN_IN_VIEW, 0)
        ->assertScript(MOTION_SHIFTS, 0)
        ->assertNoJavaScriptErrors();
});

it('has no accessibility issues on the motion styleguide page in light and dark mode', function (): void {
    visit(route('styleguide.group', 'motion'))->withLocale('it-IT')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();

    visit(route('styleguide.group', 'motion'))->inDarkMode()->withLocale('it-IT')
        ->assertScript('document.documentElement.classList.contains("dark")', true)
        ->assertNoAccessibilityIssues();
});

it('leaves nothing hidden on a real page scrolled to the bottom, with the reveal on', function (string $page): void {
    $animals = seedMotionAnimals();
    $url = $page === 'home' ? route('home') : route('animals.show', $animals[0]);

    visit($url)->withLocale('it-IT')->resize(1280, 700)
        ->assertScript(motionEventually('document.querySelectorAll("[data-reveal-state=pending]").length > 0'), true)
        ->assertScript(MOTION_HIDDEN_IN_VIEW, 0)
        ->assertScript('(async () => { for (let y = 0; y <= document.documentElement.scrollHeight; y += 250) { window.scrollTo(0, y); await new Promise((resolve) => setTimeout(resolve, 80)); } window.scrollTo(0, document.documentElement.scrollHeight); return true; })()', true)
        ->wait(1)
        ->assertScript('document.querySelectorAll("[data-reveal-state=pending]").length', 0)
        ->assertScript('[...document.querySelectorAll("[x-reveal]")].filter((element) => { const box = element.getBoundingClientRect(); return box.top < innerHeight && box.bottom > 0 && Number(getComputedStyle(element).opacity) < 1; }).length', 0)
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
})->with(['home', 'animal']);
