<?php

declare(strict_types=1);

use App\Actions\Account\AnonymizeUserAccount;
use App\Actions\Organizations\AddOrganizationMember;
use App\Enums\MembershipRole;
use App\Enums\RecurringDonationStatus;
use App\Exceptions\CannotAnonymizeSoleOwner;
use App\Models\AdopterProfile;
use App\Models\Animal;
use App\Models\Donation;
use App\Models\Organization;
use App\Models\RecurringDonation;
use App\Models\User;
use App\Support\Billing\DonationCheckoutSession;
use App\Support\Billing\ManagesRecurringDonation;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\FakeRecurringDonationCheckout;

// Anonimizzazione account (GDPR art. 17): in-place, idempotente, guardia unico-owner,
// best-effort Stripe col seam FAKE (zero rete).

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

afterEach(function () {
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

function bindFakeManager(): FakeRecurringDonationCheckout
{
    $fake = new FakeRecurringDonationCheckout;
    app()->instance(ManagesRecurringDonation::class, $fake);

    return $fake;
}

function makeOwner(Organization $organization, ?User $user = null): User
{
    $user ??= User::factory()->create();

    app(AddOrganizationMember::class)->handle(
        organization: $organization,
        user: $user,
        role: MembershipRole::Owner,
        verified: true,
    );

    app(PermissionRegistrar::class)->setPermissionsTeamId(null);

    return $user;
}

it('anonymizes core PII: name, email, password, verification and sets anonymized_at', function () {
    bindFakeManager();
    $user = User::factory()->create([
        'name' => 'Mario Rossi',
        'email' => 'mario.rossi@example.com',
    ]);

    $applied = app(AnonymizeUserAccount::class)->handle($user);

    expect($applied)->toBeTrue();

    $user->refresh();
    expect($user->name)->toBe(__('account.delete.name_placeholder'))
        ->and($user->email)->toBe(sprintf('anonimizzato-%d@utente.invalid', $user->getKey()))
        ->and($user->email_verified_at)->toBeNull()
        ->and($user->remember_token)->toBeNull()
        ->and($user->anonymized_at)->not->toBeNull()
        ->and($user->isAnonymized())->toBeTrue()
        ->and(password_verify('password', $user->password))->toBeFalse();
});

it('deletes the adopter profile', function () {
    bindFakeManager();
    $user = User::factory()->create();
    $user->adopterProfile()->save(AdopterProfile::factory()->complete()->make());

    expect($user->adopterProfile()->exists())->toBeTrue();

    app(AnonymizeUserAccount::class)->handle($user);

    expect($user->fresh()->adopterProfile()->exists())->toBeFalse();
});

it('detaches all favorites', function () {
    bindFakeManager();
    $user = User::factory()->create();
    $animals = Animal::factory()->count(2)->create();
    $user->favorites()->attach($animals->pluck('id'));

    expect($user->favorites()->count())->toBe(2);

    app(AnonymizeUserAccount::class)->handle($user);

    expect($user->fresh()->favorites()->count())->toBe(0);
});

it('anonymizes donations but keeps the amount and record', function () {
    bindFakeManager();
    $user = User::factory()->create();
    $donation = Donation::factory()->forDonor($user)->completed()->create([
        'donor_name' => 'Mario Rossi',
        'donor_email' => 'mario@example.com',
        'amount' => 4_200,
    ]);

    app(AnonymizeUserAccount::class)->handle($user);

    $donation->refresh();
    expect($donation->exists)->toBeTrue()
        ->and($donation->donor_name)->toBe(__('account.delete.donor_placeholder'))
        ->and($donation->donor_email)->toBeNull()
        ->and($donation->amount)->toBe(4_200);
});

it('cancels active recurring donations locally when stripe is not configured (no network)', function () {
    $fake = bindFakeManager();
    config(['cashier.key' => null, 'cashier.secret' => null]);

    $user = User::factory()->create();
    $recurring = RecurringDonation::factory()
        ->forDonor($user)
        ->active()
        ->withSubscriptionId('sub_X')
        ->create();

    app(AnonymizeUserAccount::class)->handle($user);

    expect($recurring->fresh()->status)->toBe(RecurringDonationStatus::Canceled)
        ->and($fake->cancelCalls)->toBe(0);
});

it('delegates stripe cancellation best-effort when configured (fake seam, no network)', function () {
    $fake = bindFakeManager();
    config(['cashier.key' => 'pk_test', 'cashier.secret' => 'sk_test']);

    $user = User::factory()->create();
    $recurring = RecurringDonation::factory()
        ->forDonor($user)
        ->active()
        ->withSubscriptionId('sub_CFG')
        ->create();

    app(AnonymizeUserAccount::class)->handle($user);

    expect($recurring->fresh()->status)->toBe(RecurringDonationStatus::Canceled)
        ->and($fake->canceledSubscriptionId)->toBe('sub_CFG')
        ->and($fake->cancelCalls)->toBe(1);
});

it('still anonymizes when stripe cancellation throws (best-effort)', function () {
    config(['cashier.key' => 'pk_test', 'cashier.secret' => 'sk_test']);

    // Seam che esplode: Stripe giù non deve bloccare l'oblio.
    app()->instance(ManagesRecurringDonation::class, new class implements ManagesRecurringDonation
    {
        public function createCheckout(RecurringDonation $recurring, string $successUrl, string $cancelUrl): DonationCheckoutSession
        {
            throw new RuntimeException('unused');
        }

        public function cancel(string $stripeSubscriptionId): void
        {
            throw new RuntimeException('stripe down');
        }
    });

    $user = User::factory()->create();
    $recurring = RecurringDonation::factory()
        ->forDonor($user)
        ->active()
        ->withSubscriptionId('sub_BOOM')
        ->create();

    $applied = app(AnonymizeUserAccount::class)->handle($user);

    expect($applied)->toBeTrue()
        ->and($recurring->fresh()->status)->toBe(RecurringDonationStatus::Canceled)
        ->and($user->fresh()->isAnonymized())->toBeTrue();
});

it('deletes database notifications', function () {
    bindFakeManager();
    $user = User::factory()->create();
    DB::table('notifications')->insert([
        'id' => Str::uuid()->toString(),
        'type' => 'App\\Notifications\\Test',
        'notifiable_type' => $user->getMorphClass(),
        'notifiable_id' => $user->getKey(),
        'data' => json_encode(['foo' => 'bar']),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect($user->notifications()->count())->toBe(1);

    app(AnonymizeUserAccount::class)->handle($user);

    expect($user->fresh()->notifications()->count())->toBe(0);
});

it('removes all spatie role assignments', function () {
    bindFakeManager();
    $org = Organization::factory()->verified()->create();
    $coOwner = makeOwner($org);
    $user = makeOwner($org); // due owner: anonimizzabile

    expect(DB::table('model_has_roles')->where('model_id', $user->getKey())->count())->toBeGreaterThan(0);

    app(AnonymizeUserAccount::class)->handle($user);

    expect(DB::table('model_has_roles')->where('model_id', $user->getKey())->count())->toBe(0)
        ->and($user->fresh()->organizations()->count())->toBe(0)
        ->and($coOwner->fresh()->isAnonymized())->toBeFalse();
});

it('detaches non-owner memberships and succeeds', function () {
    bindFakeManager();
    $org = Organization::factory()->verified()->create();
    $user = User::factory()->create();
    app(AddOrganizationMember::class)->handle($org, $user, MembershipRole::Volunteer, verified: true);
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);

    $applied = app(AnonymizeUserAccount::class)->handle($user);

    expect($applied)->toBeTrue()
        ->and($user->fresh()->organizations()->count())->toBe(0);
});

it('refuses to anonymize the sole verified owner of an organization', function () {
    bindFakeManager();
    $org = Organization::factory()->verified()->create();
    $user = makeOwner($org);

    expect(fn () => app(AnonymizeUserAccount::class)->handle($user))
        ->toThrow(CannotAnonymizeSoleOwner::class);

    // Nessuna mutazione: la guardia precede ogni scrittura.
    expect($user->fresh()->isAnonymized())->toBeFalse()
        ->and($user->fresh()->name)->not->toBe(__('account.delete.name_placeholder'));
});

it('is idempotent: a second call is a safe no-op', function () {
    bindFakeManager();
    $user = User::factory()->create();

    $first = app(AnonymizeUserAccount::class)->handle($user);
    $anonymizedEmail = $user->fresh()->email;

    $second = app(AnonymizeUserAccount::class)->handle($user->fresh());

    expect($first)->toBeTrue()
        ->and($second)->toBeFalse()
        ->and($user->fresh()->email)->toBe($anonymizedEmail);
});

it('makes the account impossible to authenticate afterwards', function () {
    bindFakeManager();
    $user = User::factory()->create(['email' => 'login@example.com']);

    expect(Auth::attempt(['email' => 'login@example.com', 'password' => 'password']))->toBeTrue();
    Auth::logout();

    app(AnonymizeUserAccount::class)->handle($user);

    expect(Auth::attempt(['email' => 'login@example.com', 'password' => 'password']))->toBeFalse()
        ->and(Auth::attempt([
            'email' => sprintf('anonimizzato-%d@utente.invalid', $user->getKey()),
            'password' => 'password',
        ]))->toBeFalse();
});
