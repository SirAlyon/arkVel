<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\Auth\Honeypot;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Ui\BrowserScripts;

uses(RefreshDatabase::class);

/*
 | Pagine di autenticazione pubbliche (UI-5) nel browser: accesso e registrazione da
 | tastiera, riepilogo errori che prende il focus, accessibilità (axe) in chiaro e in
 | scuro, target ≥ 44 px, nessun overflow a 320 px.
 | Gira SOLO nel servizio `browser-tests`: `docker compose --profile testing run --rm browser-tests`.
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

/** Ripete la condizione JS (ogni 25 ms, al massimo $timeout ms) e restituisce l'ultimo esito. */
function authEventually(string $condition, int $timeout = 5000): string
{
    return 'function () { return new Promise((resolve) => { const end = Date.now() + '.$timeout.'; const check = () => { let ok = false; try { ok = Boolean('.$condition.'); } catch (error) { ok = false; } if (ok || Date.now() > end) { resolve(ok); } else { setTimeout(check, 25); } }; check(); }); }';
}

it('signs in from the keyboard', function (): void {
    User::factory()->create(['email' => 'giulia@example.com', 'password' => 'una-password-lunga']);

    $page = visit(route('login'))->withLocale('it-IT');

    $page->assertNoJavaScriptErrors()
        ->type('#field-email', 'giulia@example.com')
        ->keys('#field-email', ['Tab'])
        ->assertScript(BrowserScripts::activeElement(), 'field-password')
        ->type('#field-password', 'una-password-lunga')
        ->keys('#field-password', ['Enter'])
        ->assertScript(authEventually('location.pathname.startsWith("/account/")'), true);
});

it('moves the focus to the error summary and from there to the field', function (): void {
    $page = visit(route('login'))->withLocale('it-IT');

    $page->type('#field-email', 'nessuno@example.com')
        ->type('#field-password', 'sbagliata')
        ->keys('[data-auth-submit]', ['Enter'])
        ->assertScript(authEventually('document.activeElement?.id === "error-summary"'), true)
        ->assertSeeIn('[data-ui-error-summary]', 'Email o password non corretti.')
        ->assertAttribute('#field-email', 'aria-invalid', 'true')
        ->keys('[data-ui-error-summary] a', ['Enter'])
        ->assertScript(BrowserScripts::activeElement(), 'field-email')
        ->assertNoAccessibilityIssues();
});

it('signs up from the keyboard and lands on the verification notice', function (): void {
    $page = visit(route('register'))->withLocale('it-IT');

    $page->assertNoJavaScriptErrors()
        ->type('#field-name', 'Giulia Bianchi')
        ->type('#field-email', 'giulia@example.com')
        ->type('#field-password', 'una-password-lunga')
        ->type('#field-password-confirmation', 'una-password-lunga')
        ->keys('#field-privacy', ['Space'])
        ->assertChecked('#field-privacy')
        // Il honeypot respinge gli invii più rapidi di Honeypot::MIN_SECONDS.
        ->wait(Honeypot::MIN_SECONDS + 1)
        ->keys('[data-auth-submit]', ['Enter'])
        ->assertScript(authEventually('location.pathname === "/verifica-email"'), true)
        ->assertSee('giulia@example.com')
        ->assertNoAccessibilityIssues();

    expect(User::query()->where('email', 'giulia@example.com')->exists())->toBeTrue();
});

it('has no accessibility issues on the auth pages, in light and dark mode', function (string $route, array $parameters): void {
    visit(route($route, $parameters))->withLocale('it-IT')
        ->assertNoJavaScriptErrors()
        ->assertScript('document.documentElement.dataset.themeForced ?? null', null)
        ->assertNoAccessibilityIssues();

    visit(route($route, $parameters))->inDarkMode()->withLocale('it-IT')
        ->assertScript('document.documentElement.classList.contains("dark")', true)
        ->assertNoAccessibilityIssues();
})->with([
    'login' => ['login', []],
    'register' => ['register', []],
    'forgot password' => ['password.request', []],
    'reset password' => ['password.reset', ['token' => 'token-di-prova', 'email' => 'giulia@example.com']],
]);

it('keeps the controls at least 44 px and has no overflow at 320 px', function (string $route): void {
    visit(route($route))->withLocale('it-IT')->resize(320, 800)
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertScript(BrowserScripts::undersizedTargets('[data-auth-card]'), []);
})->with(['login', 'register', 'password.request']);

it('shows the same confirmation for any email on the forgotten password page and moves the focus there', function (): void {
    visit(route('password.request'))->withLocale('it-IT')
        ->type('#field-email', 'nessuno@example.com')
        ->keys('[data-auth-submit]', ['Enter'])
        ->assertScript(authEventually('document.querySelector("[data-auth-sent]") !== null'), true)
        // Il focus non resta sul body: va sul blocco di conferma, letto per intero.
        ->assertScript(authEventually('document.activeElement?.id === "forgot-sent"'), true)
        ->assertAttribute('#forgot-sent', 'aria-describedby', 'forgot-sent-description')
        ->assertSee('Se esiste un account con questo indirizzo')
        ->assertNoAccessibilityIssues();
});

it('wraps a long email on the verification notice at 320 px', function (): void {
    User::factory()->unverified()->create([
        'email' => 'nome.cognome.molto.lungo.per.provare.il.capo@dominio-di-posta-esempio.example',
        'password' => 'una-password-lunga',
    ]);

    // Accesso dal modulo: l'utente da verificare arriva su /verifica-email.
    visit(route('login'))->withLocale('it-IT')->resize(320, 800)
        ->type('#field-email', 'nome.cognome.molto.lungo.per.provare.il.capo@dominio-di-posta-esempio.example')
        ->type('#field-password', 'una-password-lunga')
        ->keys('[data-auth-submit]', ['Enter'])
        ->assertScript(authEventually('location.pathname === "/verifica-email"'), true)
        ->assertSee('dominio-di-posta-esempio')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoAccessibilityIssues();
});

it('aligns the two password fields at every width', function (string $route, array $parameters, int $width): void {
    // Affiancati, i due campi partono alla stessa altezza; altrimenti uno sotto l'altro.
    $aligned = '(() => { const a = document.querySelector("#field-password").getBoundingClientRect(); const b = document.querySelector("#field-password-confirmation").getBoundingClientRect(); return Math.abs(a.top - b.top) < 1 || (Math.abs(a.left - b.left) < 1 && b.top > a.bottom); })()';

    visit(route($route, $parameters))->withLocale('it-IT')->resize($width, 900)
        ->assertScript($aligned, true);
})->with([
    'sign up' => ['register', []],
    'reset' => ['password.reset', ['token' => 'token-di-prova', 'email' => 'giulia@example.com']],
])->with([768, 1024, 1280]);

it('keeps the reset submit button on one line at 320 px', function (): void {
    visit(route('password.reset', ['token' => 'token-di-prova', 'email' => 'giulia@example.com']))->withLocale('it-IT')->resize(320, 800)
        ->assertScript('(() => { const b = document.querySelector("[data-auth-submit]"); return b.getBoundingClientRect().height <= 60; })()', true)
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true);
});
