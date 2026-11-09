<?php

declare(strict_types=1);

use App\Enums\AppointmentType;
use App\Models\Animal;
use App\Models\Appointment;
use App\Models\MessageThread;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\Ui\BrowserScripts;

/*
 | Scheda animale nel browser (UI-6c): le CTA vivono in componenti Livewire figli
 | (candidatura, affido, prenotazione, contatto) e devono funzionare montate nella pagina,
 | ognuna con il proprio stato. Pannello azioni sticky su desktop e bottom sheet su mobile,
 | prenotazione e contatto in drawer, date picker da tastiera, galleria, accessibilità (axe)
 | in chiaro e in scuro, target ≥ 44 px, nessun overflow a 320 px.
 | Gira SOLO nel servizio `browser-tests`: `docker compose --profile testing run --rm browser-tests`.
 */

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function detailAnimal(): Animal
{
    $organization = Organization::factory()->verified()->create(['city' => 'Bologna']);

    return Animal::factory()->available()->forOrganization($organization)->create(['name' => 'Argo']);
}

/** Ripete la condizione JS (ogni 25 ms, al massimo $timeout ms) e restituisce l'ultimo esito. */
function detailEventually(string $condition, int $timeout = 4000): string
{
    return 'function () { return new Promise((resolve) => { const end = Date.now() + '.$timeout.'; const check = () => { let ok = false; try { ok = Boolean('.$condition.'); } catch (error) { ok = false; } if (ok || Date.now() > end) { resolve(ok); } else { setTimeout(check, 25); } }; check(); }); }';
}

/** Il drawer con quel nome è aperto e ha finito la transizione d'entrata. */
function detailDrawerSettled(string $name): string
{
    return "(() => { const dialog = document.querySelector('[data-drawer=\"{$name}\"]'); return dialog.open && dialog.getAnimations().length === 0; })()";
}

/** L'elemento con il focus ha l'attributo (gli attributi booleani dei componenti Blade hanno come valore il nome). */
function detailFocusHas(string $attribute): string
{
    return 'document.activeElement?.hasAttribute('.json_encode($attribute, JSON_THROW_ON_ERROR).') ?? false';
}

/** L'elemento con il focus è tutto nel viewport (lo scroll verso il focus può essere animato). */
function detailFocusInView(): string
{
    // Arrotondato al pixel: lo scroll verso il focus allinea il bordo con decimali.
    return '(() => { const box = document.activeElement.getBoundingClientRect(); return box.height > 0 && Math.round(box.top) >= 0 && Math.round(box.bottom) <= window.innerHeight; })()';
}

/** L'elemento con il focus, anche dentro lo shadow DOM di Cally. */
function detailDeepActive(): string
{
    return '(() => { let element = document.activeElement; while (element?.shadowRoot?.activeElement) { element = element.shadowRoot.activeElement; } return element; })()';
}

it('sends a guest who wants to adopt to the login page', function (): void {
    visit(route('animals.show', detailAnimal()))->withLocale('it-IT')
        ->assertNoJavaScriptErrors()
        ->click('[data-animal-apply]')
        ->assertPathIs('/accedi');
});

it('keeps the action panel in view while scrolling on desktop', function (): void {
    visit(route('animals.show', detailAnimal()))->withLocale('it-IT')->resize(1280, 800)
        ->assertVisible('[data-animal-actions-dock] [data-animal-actions]')
        ->assertMissing('[data-animal-actions-bar]')
        ->assertScript('getComputedStyle(document.querySelector("[data-animal-actions-dock]")).position', 'sticky')
        ->assertScript('(() => { window.scrollTo(0, 600); return true; })()', true)
        ->assertScript(detailEventually('document.querySelector("[data-animal-actions]").getBoundingClientRect().top >= 0 && document.querySelector("[data-animal-actions]").getBoundingClientRect().top < 200'), true);
});

it('shows the booking validation errors inside the booking drawer only, with the focus on the summary', function (string $viewport): void {
    $this->actingAs(User::factory()->create());

    $page = visit(route('animals.show', detailAnimal()))->withLocale('it-IT');

    if ($viewport === 'mobile') {
        $page->resize(375, 800)
            ->click('[data-animal-actions-open]')
            ->assertScript(detailEventually(detailDrawerSettled('animal-actions')), true);
    } else {
        $page->resize(1280, 900);
    }

    $page->assertNoJavaScriptErrors()
        ->click('[data-animal-open="booking"]')
        ->assertScript(detailEventually(detailDrawerSettled('animal-booking')), true)
        ->click('[data-animal-book-submit]')
        // WCAG 2.4.3 / 3.3.1: il focus non finisce sul body ma sul riepilogo degli errori.
        ->assertScript(detailEventually('document.activeElement?.id === "booking-error-summary"'), true)
        ->assertSeeIn('#booking-error-summary', __('appointments.booking.validation.type_required'))
        ->assertSeeIn('#booking-error-summary', __('appointments.booking.validation.scheduled_at_required'))
        ->assertDontSee(__('messaging.contact.empty'))
        ->assertAttribute('#appointment-date', 'aria-invalid', 'true')
        ->assertAttribute('#appointment-time', 'aria-invalid', 'true')
        // Ogni tentativo fallito ricrea il riepilogo, che riprende il focus.
        ->click('[data-animal-book-submit]')
        ->assertScript(detailEventually('document.activeElement?.id === "booking-error-summary"'), true)
        // Dal riepilogo al campo del giorno.
        ->click('#booking-error-summary a[href="#appointment-date"]')
        ->assertScript(BrowserScripts::activeElement(), 'appointment-date');
})->with(['desktop', 'mobile']);

it('books a visit choosing the day from the keyboard and typing a free time', function (string $viewport, string $time): void {
    $user = User::factory()->create();
    $this->actingAs($user);
    $animal = detailAnimal();
    $tomorrow = today()->addDay();
    $day = '[data-ui-date-picker] calendar-month button[tabindex="0"]';

    $page = visit(route('animals.show', $animal))->withLocale('it-IT');

    if ($viewport === 'mobile') {
        $page->resize(375, 800)
            ->click('[data-animal-actions-open]')
            ->assertScript(detailEventually(detailDrawerSettled('animal-actions')), true);
    } else {
        $page->resize(1280, 900);
    }

    $page->click('[data-animal-open="booking"]')
        ->assertScript(detailEventually(detailDrawerSettled('animal-booking')), true)
        ->assertAttribute('#appointment-time', 'type', 'time')
        ->click('input[name="appointmentType"][value="visit"]')
        ->keys('[data-drawer="animal-booking"] [data-date-picker-trigger]', ['Enter'])
        ->assertScript(detailEventually(detailDeepActive().'?.getAttribute("aria-current") === "date"'), true)
        ->keys($day, ['ArrowRight'])
        ->assertScript(detailEventually(detailDeepActive().'?.getAttribute("aria-label") === '.json_encode($tomorrow->locale('it')->isoFormat('D MMMM'))), true)
        ->keys($day, ['Enter'])
        ->assertValue('#appointment-date', $tomorrow->locale('it')->isoFormat('LL'))
        ->assertScript(BrowserScripts::activeElement('data-date-picker-trigger'), '')
        // Orario libero (input type=time), come il datetime-local di prima: nessuna fascia
        // imposta dalla vista, anche fuori dalle mezz'ore e la sera.
        ->type('#appointment-time', $time)
        ->click('[data-animal-book-submit]')
        // Il focus va al messaggio di esito (WCAG 2.4.3), che è in vista.
        ->assertScript(detailEventually('document.activeElement?.dataset.animalFeedback === "success"'), true)
        ->assertSeeIn('[data-animal-cta="booking"] [data-animal-feedback="success"]', __('appointments.booking.flash_success'))
        ->assertScript(detailEventually(detailFocusInView()), true)
        // Dopo l'invio il server azzera la data: anche i due controlli tornano vuoti.
        ->assertScript(detailEventually('document.querySelector("#appointment-time").value === "" && document.querySelector("#appointment-date").value === ""'), true)
        ->assertNoJavaScriptErrors();

    $appointment = Appointment::query()->sole();

    expect($appointment->user_id)->toBe($user->getKey())
        ->and($appointment->type)->toBe(AppointmentType::Visit)
        ->and($appointment->scheduled_at->format('Y-m-d H:i'))->toBe($tomorrow->format('Y-m-d').' '.$time);
})->with([
    'desktop, 10:47' => ['desktop', '10:47'],
    'mobile, 20:15' => ['mobile', '20:15'],
]);

it('moves the focus to the slot error when the server rejects the time', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);
    $animal = detailAnimal();
    $day = '[data-ui-date-picker] calendar-month button[tabindex="0"]';

    // Oggi a mezzanotte e un minuto è sempre nel passato: la guardia è solo del server.
    visit(route('animals.show', $animal))->withLocale('it-IT')->resize(1280, 900)
        ->click('[data-animal-open="booking"]')
        ->assertScript(detailEventually(detailDrawerSettled('animal-booking')), true)
        ->click('input[name="appointmentType"][value="visit"]')
        ->keys('[data-drawer="animal-booking"] [data-date-picker-trigger]', ['Enter'])
        ->assertScript(detailEventually(detailDeepActive().'?.getAttribute("aria-current") === "date"'), true)
        ->keys($day, ['Enter'])
        ->type('#appointment-time', '00:01')
        ->click('[data-animal-book-submit]')
        ->assertScript(detailEventually('document.activeElement?.dataset.animalFeedback === "error"'), true)
        ->assertSeeIn('[data-animal-feedback="error"]', __('appointments.booking.slot_in_past'))
        ->assertNoJavaScriptErrors();

    expect(Appointment::query()->count())->toBe(0);
});

it('contacts the organization from its drawer, moving the focus to the errors and then to the outcome', function (string $viewport): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $page = visit(route('animals.show', detailAnimal()))->withLocale('it-IT');

    if ($viewport === 'mobile') {
        $page->resize(375, 800)
            ->click('[data-animal-actions-open]')
            ->assertScript(detailEventually(detailDrawerSettled('animal-actions')), true);
    } else {
        $page->resize(1280, 900);
    }

    $page->click('[data-animal-open="contact"]')
        ->assertScript(detailEventually(detailDrawerSettled('animal-contact')), true)
        ->assertMissing('[data-drawer="animal-contact"] [data-animal-guest-note]')
        ->click('[data-animal-contact-submit]')
        ->assertScript(detailEventually('document.activeElement?.id === "contact-error-summary"'), true)
        ->assertSeeIn('#contact-error-summary', __('messaging.contact.empty'))
        ->assertAttribute('#contact-body', 'aria-invalid', 'true')
        ->type('#contact-body', 'Vorrei saperne di più su Argo.')
        ->click('[data-animal-contact-submit]')
        ->assertScript(detailEventually('document.activeElement?.dataset.animalFeedback === "success"'), true)
        ->assertSeeIn('[data-animal-cta="contact"] [data-animal-feedback="success"]', __('messaging.contact.flash_success'))
        ->assertMissing('#contact-error-summary')
        ->assertScript(detailEventually(detailFocusInView()), true)
        // Un secondo messaggio: l'esito è ricreato e riprende il focus.
        ->type('#contact-body', 'Ha già fatto i vaccini?')
        ->click('[data-animal-contact-submit]')
        ->assertScript(detailEventually('document.activeElement?.dataset.animalFeedback === "success" && document.activeElement.textContent.includes('.json_encode(__('messaging.contact.flash_appended'), JSON_THROW_ON_ERROR).')'), true)
        ->assertNoJavaScriptErrors();

    expect(MessageThread::query()->where('user_id', $user->getKey())->count())->toBe(1);
})->with(['desktop', 'mobile']);

it('tells a guest to sign in before booking or writing, and brings them back to the animal after signing in', function (): void {
    User::factory()->create(['email' => 'giulia@example.com', 'password' => 'una-password-lunga']);
    $animal = detailAnimal();

    $page = visit(route('animals.show', $animal))->withLocale('it-IT')->resize(1280, 900);

    $page->click('[data-animal-open="contact"]')
        ->assertScript(detailEventually(detailDrawerSettled('animal-contact')), true)
        ->assertSeeIn('[data-drawer="animal-contact"] [data-animal-guest-note]', __('page_animal.contact.guest_note'))
        ->keys('[data-drawer="animal-contact"] [data-dialog-close]', ['Escape'])
        ->assertScript(detailEventually('!document.querySelector(\'[data-drawer="animal-contact"]\').open'), true)
        ->click('[data-animal-open="booking"]')
        ->assertScript(detailEventually(detailDrawerSettled('animal-booking')), true)
        ->assertSeeIn('[data-drawer="animal-booking"] [data-animal-guest-note]', __('page_animal.booking.guest_note'))
        ->assertAttribute('[data-drawer="animal-booking"] [data-animal-guest-login]', 'href', route('login'))
        ->assertNoAccessibilityIssues()
        ->click('[data-drawer="animal-booking"] [data-animal-guest-login]')
        ->assertPathIs('/accedi')
        ->type('#field-email', 'giulia@example.com')
        ->type('#field-password', 'una-password-lunga')
        ->click('[data-auth-submit]')
        ->assertScript(detailEventually('location.pathname === '.json_encode(parse_url(route('animals.show', $animal), PHP_URL_PATH), JSON_THROW_ON_ERROR), 8000), true);
});

it('opens the action panel in a bottom sheet on mobile and drives it from the keyboard', function (): void {
    $page = visit(route('animals.show', detailAnimal()))->withLocale('it-IT')->resize(375, 800);

    $page->assertVisible('[data-animal-actions-bar]')
        ->assertScript(detailEventually('document.querySelector("[data-animal-actions-sheet] [data-animal-actions]") !== null'), true)
        ->keys('[data-animal-actions-open]', ['Enter'])
        ->assertScript(detailEventually(detailDrawerSettled('animal-actions')), true)
        ->assertScript('(() => { const box = document.querySelector(\'[data-drawer="animal-actions"]\').getBoundingClientRect(); return Math.round(box.bottom) === window.innerHeight; })()', true)
        ->assertScript(detailFocusHas('data-dialog-close'), true)
        ->keys('[data-drawer="animal-actions"] [data-dialog-close]', ['Tab'])
        ->assertScript(detailFocusHas('data-animal-apply'), true)
        ->assertScript(BrowserScripts::undersizedTargets('[data-drawer="animal-actions"]'), [])
        ->assertNoAccessibilityIssues()
        ->keys('[data-animal-apply]', ['Escape'])
        ->assertScript(detailEventually('!document.querySelector(\'[data-drawer="animal-actions"]\').open'), true)
        ->assertScript(detailFocusHas('data-animal-actions-open'), true);
});

it('opens the booking sheet over the action sheet on mobile', function (): void {
    visit(route('animals.show', detailAnimal()))->withLocale('it-IT')->resize(375, 800)
        ->click('[data-animal-actions-open]')
        ->assertScript(detailEventually(detailDrawerSettled('animal-actions')), true)
        ->click('[data-animal-open="booking"]')
        ->assertScript(detailEventually(detailDrawerSettled('animal-booking')), true)
        ->keys('[data-drawer="animal-booking"] [data-dialog-close]', ['Escape'])
        ->assertScript(detailEventually('!document.querySelector(\'[data-drawer="animal-booking"]\').open'), true)
        // Torna allo sheet delle azioni, con il focus sul bottone che ha aperto la prenotazione.
        ->assertScript('document.querySelector(\'[data-drawer="animal-actions"]\').open', true)
        ->assertScript(BrowserScripts::activeElement('data-animal-open'), 'booking');
});

it('moves the panel back to the side column when the window grows', function (): void {
    visit(route('animals.show', detailAnimal()))->withLocale('it-IT')->resize(375, 800)
        ->assertScript(detailEventually('document.querySelector("[data-animal-actions-sheet] [data-animal-actions]") !== null'), true)
        ->resize(1280, 800)
        ->assertScript(detailEventually('document.querySelector("[data-animal-actions-dock] [data-animal-actions]") !== null'), true)
        ->assertVisible('[data-animal-apply]');
});

it('browses the gallery with the buttons', function (): void {
    Storage::fake('public');
    Queue::fake();

    $animal = detailAnimal();
    $animal->addMedia(UploadedFile::fake()->image('a.jpg', 900, 600))->toMediaCollection('photos');
    $animal->addMedia(UploadedFile::fake()->image('b.jpg', 900, 600))->toMediaCollection('photos');

    visit(route('animals.show', $animal))->withLocale('it-IT')->resize(1280, 900)
        ->assertScript(detailEventually('!document.querySelector("[data-gallery-controls]").classList.contains("invisible")'), true)
        ->assertAttribute('[data-gallery-thumb="0"]', 'aria-current', 'true')
        ->click('[data-gallery-next]')
        ->assertScript(detailEventually('document.querySelector(\'[data-gallery-thumb="1"]\').getAttribute("aria-current") === "true"'), true)
        ->assertScript('document.querySelectorAll("img[fetchpriority=high]").length', 1);
});

it('does not push the content down when the gallery controls get ready on mobile', function (): void {
    Storage::fake('public');
    Queue::fake();

    $animal = detailAnimal();
    $animal->addMedia(UploadedFile::fake()->image('a.jpg', 900, 600))->toMediaCollection('photos');
    $animal->addMedia(UploadedFile::fake()->image('b.jpg', 900, 600))->toMediaCollection('photos');
    $animal->addMedia(UploadedFile::fake()->image('c.jpg', 900, 600))->toMediaCollection('photos');

    // Somma degli spostamenti (CLS) registrati dal caricamento, dopo che la galleria è pronta.
    $layoutShift = 'new Promise((resolve) => { let total = 0; new PerformanceObserver((list) => { for (const entry of list.getEntries()) { if (!entry.hadRecentInput) { total += entry.value; } } }).observe({ type: "layout-shift", buffered: true }); setTimeout(() => resolve(Math.round(total * 1000) / 1000), 500); })';

    // Viewport mobile fin dal caricamento: un resize dopo il primo paint sposterebbe il layout.
    visit(route('animals.show', $animal))->withLocale('it-IT')->on()->mobile()
        // Galleria pronta: bottoni e miniature visibili (prima erano invisible, con lo spazio già riservato).
        ->assertScript(detailEventually('document.querySelector("[data-ui-gallery]").dataset.ready !== undefined && !document.querySelector("[data-gallery-thumbs]").classList.contains("invisible")'), true)
        ->assertScript('document.querySelector("[data-gallery-thumbs]").offsetHeight > 0', true)
        // Altezza stabile: si riporta la galleria allo stato «non pronta» (come prima che Embla
        // si carichi, cosa che in locale accade prima del primo paint e quindi non si misura
        // come CLS) e l'intestazione sotto la galleria non si sposta. Con `hidden` saliva di
        // ~130 px (CLS 0,077 misurato a 375 px con CPU rallentata).
        ->assertScript('(async () => { const data = window.Alpine.$data(document.querySelector("[data-ui-gallery]")); const top = () => document.querySelector("[data-animal-header]").getBoundingClientRect().top + window.scrollY; const ready = top(); data.ready = false; await window.Alpine.nextTick(); const loading = top(); const hiddenControls = document.querySelector("[data-gallery-controls]").classList.contains("invisible"); data.ready = true; await window.Alpine.nextTick(); return hiddenControls && Math.abs(ready - loading) < 1; })()', true)
        // Budget CLS del piano (§4): nessuno spostamento registrato dal caricamento.
        ->assertScript('(async () => (await '.$layoutShift.') < 0.02)()', true);
});

it('shows the similar animals with the site card and toggles their favorite', function (int $width): void {
    $animal = detailAnimal();
    $fido = Animal::factory()->available()->forOrganization($animal->organization)->create(['name' => 'Fido', 'species' => $animal->species]);
    $user = User::factory()->create();
    $user->favorites()->attach($fido->getKey());
    $this->actingAs($user);

    $favorite = '[data-animal-similar] [data-favorite]';

    visit(route('animals.show', $animal))->withLocale('it-IT')->resize($width, 900)
        ->assertNoJavaScriptErrors()
        ->assertScript('document.querySelectorAll("[data-animal-similar] [data-animal-card]").length', 1)
        ->assertScript('document.querySelector("[data-animal-similar] a[href=\''.route('animals.show', $fido).'\']") !== null', true)
        ->assertAttribute($favorite, 'aria-pressed', 'true')
        ->click($favorite)
        ->assertScript(detailEventually('document.querySelector("'.$favorite.'").getAttribute("aria-pressed") === "false"'), true)
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true);

    expect($user->favorites()->whereKey($fido->getKey())->exists())->toBeFalse();
})->with(['mobile' => 375, 'desktop' => 1280]);

it('has no accessibility issues, in light and dark mode', function (): void {
    $animal = detailAnimal();
    Animal::factory()->available()->forOrganization($animal->organization)->create(['name' => 'Fido']);

    visit(route('animals.show', $animal))->withLocale('it-IT')
        ->assertNoJavaScriptErrors()
        ->assertScript('document.documentElement.dataset.themeForced ?? null', null)
        ->assertNoAccessibilityIssues();

    visit(route('animals.show', $animal))->inDarkMode()->withLocale('it-IT')
        ->assertScript('document.documentElement.classList.contains("dark")', true)
        ->assertNoAccessibilityIssues()
        ->click('[data-animal-open="booking"]')
        ->assertScript(detailEventually(detailDrawerSettled('animal-booking')), true)
        ->assertNoAccessibilityIssues();
});

it('keeps the controls at least 44 px and has no overflow at 320 px', function (): void {
    visit(route('animals.show', detailAnimal()))->withLocale('it-IT')->resize(320, 800)
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertScript(BrowserScripts::undersizedTargets('[data-animal-actions-bar], [data-animal-facts] [role=tablist]'), [])
        ->click('[data-animal-actions-open]')
        ->assertScript(detailEventually(detailDrawerSettled('animal-actions')), true)
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true);
});
