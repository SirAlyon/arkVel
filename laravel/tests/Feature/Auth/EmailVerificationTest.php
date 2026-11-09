<?php

declare(strict_types=1);

use App\Actions\Auth\SendEmailVerification;
use App\Enums\MembershipRole;
use App\Livewire\Auth\VerifyEmailPage;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\Auth\VerifyEmailAddress;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
 | Verifica dell'email sul sito pubblico (UI-5): avviso /verifica-email
 | (`verification.notice`), link firmato (`verification.verify`) e reinvio
 | (`verification.send`). Sostituiscono la prompt del pannello Filament adottante.
 */

function verificationUrl(User $user, ?string $email = null): string
{
    return URL::temporarySignedRoute('verification.verify', now()->addHour(), [
        'id' => $user->getKey(),
        'hash' => sha1($email ?? $user->getEmailForVerification()),
    ]);
}

it('shows the notice to an unverified user, with noindex', function (): void {
    $user = User::factory()->unverified()->create(['email' => 'giulia@example.com']);

    $this->actingAs($user)
        ->get(route('verification.notice'))
        ->assertOk()
        ->assertSeeLivewire(VerifyEmailPage::class)
        ->assertSee('<title>Conferma la tua email — ArkVel</title>', false)
        ->assertSee('<meta name="robots" content="noindex, follow">', false)
        ->assertSee('giulia@example.com')
        ->assertSee('action="'.route('verification.send').'"', false)
        ->assertSee('action="'.route('logout').'"', false);
});

it('sends a guest to sign in first, keeping the notice as intended page', function (): void {
    $this->get(route('verification.notice'))
        ->assertRedirect(route('login'));

    expect(session('url.intended'))->toBe(route('verification.notice'));
});

it('moves an already verified user on to the adopter area', function (): void {
    Livewire::actingAs(User::factory()->create())
        ->test(VerifyEmailPage::class)
        ->assertRedirect(route('filament.adopter.home'));
});

it('moves an already verified member of an organization on to /manage', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $member = member(Organization::factory()->verified()->create(), MembershipRole::Volunteer);

    Livewire::actingAs($member)
        ->test(VerifyEmailPage::class)
        ->assertRedirect(route('filament.shelter.tenant'));

    $this->actingAs($member)
        ->post(route('verification.send'))
        ->assertRedirect(route('filament.shelter.tenant'));
});

it('lets a long email wrap in the introduction', function (): void {
    $this->actingAs(User::factory()->unverified()->create())
        ->get(route('verification.notice'))
        ->assertSeeHtml('[overflow-wrap:anywhere]" data-auth-intro');
});

/** Titoli delle notifiche Filament messe in sessione per la prossima pagina dei pannelli. */
function filamentNotificationTitles(): array
{
    return array_column((array) session('filament.notifications', []), 'title');
}

it('verifies the email from the signed link and continues to the intended page', function (): void {
    Event::fake([Verified::class]);
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->withSession(['url.intended' => route('filament.adopter.pages.favorites')])
        ->get(verificationUrl($user))
        ->assertRedirect(route('filament.adopter.pages.favorites'))
        ->assertSessionMissing('toast');

    // La destinazione è un pannello Filament, che non ha il toaster del sito: la conferma
    // passa dalle notifiche del pannello.
    expect(filamentNotificationTitles())->toBe(['Email confermata. Benvenuto!'])
        ->and($user->fresh()->hasVerifiedEmail())->toBeTrue();
    Event::assertDispatched(Verified::class);
});

it('shows the confirmation in the adopter panel it lands on', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(verificationUrl($user))
        ->assertRedirect(route('filament.adopter.home'));

    // L'utente in memoria del guard è quello di prima della verifica.
    $this->actingAs($user->fresh())
        ->followingRedirects()
        ->get(route('filament.adopter.home'))
        ->assertOk()
        ->assertSee('Email confermata. Benvenuto!');
});

it('confirms with a toast when the intended page is on the public site', function (): void {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->withSession(['url.intended' => url('/animali')])
        ->get(verificationUrl($user))
        ->assertRedirect(url('/animali'))
        ->assertSessionHas('toast', ['type' => 'success', 'title' => 'Email confermata. Benvenuto!']);

    expect(filamentNotificationTitles())->toBe([]);
});

it('sends a member of an organization to /manage after verifying', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $member = member(Organization::factory()->verified()->create(), MembershipRole::Volunteer);
    $member->forceFill(['email_verified_at' => null])->save();

    $this->actingAs($member)
        ->get(verificationUrl($member))
        ->assertRedirect(route('filament.shelter.tenant'));

    expect(filamentNotificationTitles())->toBe(['Email confermata. Benvenuto!']);
});

it('rejects a link with a bad signature or for another user', function (): void {
    $user = User::factory()->unverified()->create();
    $other = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(verificationUrl($user).'tampered')
        ->assertForbidden();

    $this->actingAs($user)
        ->get(verificationUrl($other))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(verificationUrl($user, 'altra@example.com'))
        ->assertForbidden();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('asks a guest following the link to sign in and comes back to it', function (): void {
    $user = User::factory()->unverified()->create();
    $url = verificationUrl($user);

    $this->get($url)->assertRedirect(route('login'));

    expect(session('url.intended'))->toBe($url);
});

it('resends the verification mail', function (): void {
    Notification::fake();
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->from(route('verification.notice'))
        ->post(route('verification.send'))
        ->assertRedirect(route('verification.notice'))
        ->assertSessionHas('toast', ['type' => 'success', 'title' => 'Ti abbiamo mandato una nuova mail di conferma.']);

    Notification::assertSentToTimes($user, VerifyEmailAddress::class, 1);
});

it('limits the resends and says how long to wait', function (): void {
    Notification::fake();
    $user = User::factory()->unverified()->create();

    foreach (range(1, SendEmailVerification::MAX_ATTEMPTS) as $attempt) {
        $this->actingAs($user)->post(route('verification.send'));
    }

    $this->actingAs($user)->post(route('verification.send'))
        ->assertRedirect(route('verification.notice'));

    expect(session('toast.type'))->toBe('danger')
        ->and(session('toast.title'))->toStartWith('Troppi tentativi');
    Notification::assertSentToTimes($user, VerifyEmailAddress::class, SendEmailVerification::MAX_ATTEMPTS);
});

it('does not resend to a verified user', function (): void {
    Notification::fake();

    $this->actingAs(User::factory()->create())
        ->post(route('verification.send'))
        ->assertRedirect(route('filament.adopter.home'));

    Notification::assertNothingSent();
});

it('protects the resend with CSRF and the throttle middleware', function (): void {
    $route = app('router')->getRoutes()->getByName('verification.send');
    $verify = app('router')->getRoutes()->getByName('verification.verify');

    expect($route->gatherMiddleware())->toContain('auth', 'throttle:6,1', 'web')
        ->and($verify->gatherMiddleware())->toContain('auth', 'signed', 'throttle:6,1');
});
