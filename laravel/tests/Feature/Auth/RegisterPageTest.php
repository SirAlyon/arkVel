<?php

declare(strict_types=1);

use App\Actions\Auth\RegisterAdopter;
use App\Enums\RoleName;
use App\Livewire\Auth\RegisterPage;
use App\Models\AdopterProfile;
use App\Models\User;
use App\Notifications\Auth\VerifyEmailAddress;
use App\Support\Auth\Honeypot;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rules\Password;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

/*
 | /registrati (route `register`, UI-5): registrazione pubblica dell'adottante che
 | sostituisce quella del pannello Filament. Logica in App\Actions\Auth\RegisterAdopter;
 | trappola anti-bot in App\Support\Auth\Honeypot.
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

/**
 * Il modulo compilato con dati validi, dopo il tempo minimo del honeypot.
 *
 * @param  array<string, mixed>  $overrides
 */
function filledRegistration(array $overrides = []): Testable
{
    $component = Livewire::test(RegisterPage::class);

    test()->travel(Honeypot::MIN_SECONDS + 1)->seconds();

    foreach ([
        'name' => 'Giulia Bianchi',
        'email' => 'giulia@example.com',
        'password' => 'una-password-lunga',
        'password_confirmation' => 'una-password-lunga',
        'privacy' => true,
        ...$overrides,
    ] as $property => $value) {
        $component->set($property, $value);
    }

    return $component;
}

it('renders the sign-up page with the right autocomplete hints and the hidden trap', function (): void {
    $this->get(route('register'))
        ->assertOk()
        ->assertSeeLivewire(RegisterPage::class)
        ->assertSee('<title>Crea il tuo account — ArkVel</title>', false)
        ->assertSee('<meta name="robots" content="noindex, follow">', false)
        ->assertSee('autocomplete="name"', false)
        ->assertSee('autocomplete="new-password"', false)
        ->assertSee('data-honeypot', false)
        ->assertSee('aria-hidden="true" inert', false);
});

it('registers an adopter exactly like the Filament panel did, plus the privacy notice acknowledgement', function (): void {
    Notification::fake();
    Event::fake([Registered::class]);

    filledRegistration()
        ->call('register')
        ->assertHasNoErrors()
        ->assertRedirect(route('verification.notice'));

    $user = User::query()->where('email', 'giulia@example.com')->sole();

    // Solo l'utente: nessun profilo adottante (resta il questionario di /account).
    expect($user->name)->toBe('Giulia Bianchi')
        ->and(password_verify('una-password-lunga', $user->password))->toBeTrue()
        ->and($user->hasVerifiedEmail())->toBeFalse()
        ->and(AdopterProfile::query()->count())->toBe(0);

    // Ruolo Adopter team-independent, come EnsureAdopterRole all'ingresso in /account.
    expect(DB::table('model_has_roles')
        ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
        ->where('model_has_roles.model_id', $user->getKey())
        ->where('roles.name', RoleName::Adopter->value)
        ->value('model_has_roles.team_id'))->toBe(RoleName::PLATFORM_TEAM_ID);

    // Presa visione dell'informativa nell'activitylog, con la versione in vigore e senza
    // altri dati personali.
    $notice = Activity::query()->where('description', RegisterAdopter::PRIVACY_NOTICE_LOG)->sole();
    expect($notice->subject?->is($user))->toBeTrue()
        ->and($notice->causer?->is($user))->toBeTrue()
        ->and($notice->properties->all())->toBe(['notice' => 'privacy_policy', 'policy_version' => '2026-11', 'locale' => 'it']);

    Event::assertDispatched(Registered::class, fn (Registered $event): bool => $event->user->is($user));
    $this->assertAuthenticatedAs($user);
});

it('sends the queued verification mail with the public link', function (): void {
    Notification::fake();

    filledRegistration()->call('register')->assertHasNoErrors();

    $user = User::query()->where('email', 'giulia@example.com')->sole();

    Notification::assertSentTo($user, VerifyEmailAddress::class, function (VerifyEmailAddress $notification) use ($user): bool {
        $url = $notification->toMail($user)->actionUrl;

        return str_starts_with($url, url('/verifica-email/'.$user->getKey().'/'))
            && str_contains($url, 'signature=')
            && $notification->locale === 'it';
    });
    Notification::assertSentToTimes($user, VerifyEmailAddress::class, 1);
    expect(new VerifyEmailAddress)->toBeInstanceOf(ShouldQueue::class);
});

it('validates every field with translated messages', function (): void {
    User::factory()->create(['email' => 'taken@example.com']);

    filledRegistration([
        'name' => '',
        'email' => 'taken@example.com',
        'password' => 'short',
        'password_confirmation' => 'different',
        'privacy' => false,
    ])
        ->call('register')
        ->assertHasErrors(['name', 'email', 'password', 'privacy'])
        ->assertSee('Scrivi il tuo nome.')
        ->assertSee('Esiste già un account con questo indirizzo email.')
        ->assertSee('La password deve avere almeno 8 caratteri.')
        ->assertSee('Per creare l’account conferma di aver preso visione di come trattiamo i tuoi dati.')
        ->assertSee('data-ui-error-summary', false)
        // I link del riepilogo puntano agli id veri dei controlli (x-ui.field / x-ui.checkbox).
        ->assertSee('href="#field-password"', false)
        ->assertSee('id="field-password"', false)
        ->assertSee('href="#field-privacy"', false)
        ->assertSee('id="field-privacy"', false);

    expect(User::query()->count())->toBe(1);
    $this->assertGuest();
});

it('requires the password confirmation to match', function (): void {
    filledRegistration(['password_confirmation' => 'altra-password-lunga'])
        ->call('register')
        ->assertHasErrors(['password' => 'confirmed'])
        ->assertSee('Le due password non coincidono.');
});

it('rejects a bot that fills the hidden trap field', function (): void {
    filledRegistration(['website' => 'https://spam.example'])
        ->call('register')
        ->assertHasErrors(['form'])
        ->assertSee('Non siamo riusciti a completare la registrazione.');

    expect(User::query()->count())->toBe(0);
});

it('rejects a submission faster than a person could type', function (): void {
    $component = Livewire::test(RegisterPage::class)
        ->set('name', 'Bot')
        ->set('email', 'bot@example.com')
        ->set('password', 'una-password-lunga')
        ->set('password_confirmation', 'una-password-lunga')
        ->set('privacy', true);

    $component->call('register')->assertHasErrors(['form']);

    expect(User::query()->count())->toBe(0);
});

it('does not let the client change the time the form was shown', function (): void {
    Livewire::test(RegisterPage::class)->set('renderedAt', 0);
})->throws(CannotUpdateLockedPropertyException::class);

it('limits the number of sign-ups from the same IP', function (): void {
    Notification::fake();

    foreach (range(1, RegisterAdopter::MAX_REGISTRATIONS) as $index) {
        filledRegistration(['email' => "persona{$index}@example.com"])->call('register')->assertHasNoErrors();
        auth()->logout();
        // Oltre la finestra degli invii: qui conta solo il limite delle registrazioni.
        $this->travel(RegisterAdopter::SUBMISSIONS_DECAY_SECONDS)->seconds();
    }

    filledRegistration(['email' => 'una-di-troppo@example.com'])
        ->call('register')
        ->assertHasErrors(['form'])
        ->assertSee('Troppi tentativi. Riprova tra');

    expect(User::query()->where('email', 'una-di-troppo@example.com')->exists())->toBeFalse();
});

it('limits every submission from the same IP before validating, so taken emails cannot be enumerated', function (): void {
    User::factory()->create(['email' => 'presa@example.com']);

    foreach (range(1, RegisterAdopter::MAX_SUBMISSIONS) as $attempt) {
        filledRegistration(['email' => 'presa@example.com'])
            ->call('register')
            ->assertHasErrors(['email'])
            ->assertSee('Esiste già un account con questo indirizzo email.');
    }

    // Al limite la risposta non dice più nulla sull'email: niente validazione.
    filledRegistration(['email' => 'presa@example.com'])
        ->call('register')
        ->assertHasErrors(['form'])
        ->assertHasNoErrors(['email'])
        ->assertSee('Troppi tentativi')
        ->assertDontSee('Esiste già un account con questo indirizzo email.');
});

it('counts the bot rejections in the submission limit', function (): void {
    foreach (range(1, RegisterAdopter::MAX_SUBMISSIONS) as $attempt) {
        filledRegistration(['website' => 'https://spam.example'])->call('register')->assertHasErrors(['form']);
    }

    filledRegistration()
        ->call('register')
        ->assertHasErrors(['form'])
        ->assertSee('Troppi tentativi');

    expect(User::query()->count())->toBe(0);
});

it('clears the passwords after a failed submission', function (): void {
    filledRegistration(['name' => ''])
        ->call('register')
        ->assertHasErrors(['name'])
        ->assertSet('password', '')
        ->assertSet('password_confirmation', '')
        ->assertSet('email', 'giulia@example.com');
});

it('stores the email trimmed and in lower case', function (): void {
    Notification::fake();

    filledRegistration(['email' => '  Giulia.Bianchi@Example.COM '])->call('register')->assertHasNoErrors();

    expect(User::query()->sole()->email)->toBe('giulia.bianchi@example.com');
});

it('treats an email differing only in case as already registered', function (): void {
    User::factory()->create(['email' => 'giulia@example.com']);

    filledRegistration(['email' => 'GIULIA@example.com'])
        ->call('register')
        ->assertHasErrors(['email' => 'unique']);

    expect(User::query()->count())->toBe(1);
});

it('does not register anybody when the session signed in meanwhile', function (): void {
    $member = User::factory()->create();
    $component = filledRegistration(['email' => 'nuova@example.com']);

    // Accesso fatto in un'altra scheda: il modulo aperto qui non deve creare un account.
    $this->actingAs($member);

    $component->call('register')->assertRedirect(route('filament.adopter.home'));

    expect(User::query()->where('email', 'nuova@example.com')->exists())->toBeFalse();
});

it('asks to acknowledge the privacy notice without links while the legal page does not exist', function (): void {
    $this->get(route('register'))
        ->assertSee('Ho preso visione di come vengono trattati i miei dati per gestire l’account.')
        ->assertSee('Puoi chiedere in ogni momento di cancellare l’account.')
        ->assertDontSee('Obbligatorio.')
        ->assertDontSee('data-auth-privacy-link', false);
});

it('links the privacy notice once the legal page exists', function (): void {
    Route::get('/privacy', fn (): string => 'privacy')->name('legal.privacy');
    app('router')->getRoutes()->refreshNameLookups();

    $this->get(route('register'))
        ->assertSee('data-auth-privacy-link', false)
        ->assertSee('href="'.url('/privacy').'"', false)
        ->assertSee('Leggi l’informativa sulla privacy');
});

it('shows the real minimum password length in the help text', function (): void {
    Password::defaults(fn (): Password => Password::min(12));

    try {
        $this->get(route('register'))->assertSee('Almeno 12 caratteri.');
    } finally {
        Password::$defaultCallback = null;
    }
});

it('sends signed-in visitors to their area', function (): void {
    $this->actingAs(User::factory()->create())
        ->get(route('register'))
        ->assertRedirect(route('filament.adopter.home'));
});
