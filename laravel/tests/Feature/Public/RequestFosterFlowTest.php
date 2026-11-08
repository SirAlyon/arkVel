<?php

declare(strict_types=1);

use App\Actions\Organizations\AddOrganizationMember;
use App\Enums\FosterApplicationStatus;
use App\Enums\MembershipRole;
use App\Livewire\AnimalDetail;
use App\Models\AdopterProfile;
use App\Models\Animal;
use App\Models\FosterApplication;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\NewFosterApplicationReceived;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

// Specchio di tests/Feature/Public/ApplyFlowTest.php per il ramo affido: stessa
// macchina di rami (ospite / profilo incompleto / richiedibile / già richiesto),
// stessa guardia di screening, delega a SubmitFosterApplication.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Notification::fake();
});

afterEach(function () {
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

/**
 * Un ente verificato con almeno un membro verificato (destinatario delle notifiche)
 * e un animale pubblicamente visibile.
 *
 * @return array{0: Animal, 1: User}
 */
function verifiedOrgAnimalWithFosterStaff(): array
{
    $org = Organization::factory()->verified()->create();

    $staff = User::factory()->create();
    app(AddOrganizationMember::class)->handle(
        organization: $org,
        user: $staff,
        role: MembershipRole::Staff,
        verified: true,
    );

    $animal = Animal::factory()->available()->forOrganization($org)->create(['name' => 'Argo']);

    return [$animal, $staff];
}

it('redirects a guest to the adopter login and creates no foster application', function () {
    [$animal] = verifiedOrgAnimalWithFosterStaff();

    Livewire::test(AnimalDetail::class, ['animal' => $animal])
        ->call('requestFoster')
        ->assertRedirect(route('login'));

    expect(FosterApplication::query()->count())->toBe(0)
        ->and(session('url.intended'))->toBe(route('animals.show', $animal));

    Notification::assertNothingSent();
});

it('redirects an authenticated user without a complete profile to the profile page and creates no foster application', function () {
    [$animal] = verifiedOrgAnimalWithFosterStaff();

    // Profilo esistente ma incompleto (nessun consenso GDPR).
    $user = User::factory()->create();
    AdopterProfile::factory()->forUser($user)->withoutConsent()->create();

    Livewire::actingAs($user)
        ->test(AnimalDetail::class, ['animal' => $animal])
        ->call('requestFoster')
        ->assertRedirect(route('filament.adopter.pages.manage-adopter-profile'));

    expect(FosterApplication::query()->count())->toBe(0);

    Notification::assertNothingSent();
});

it('redirects an authenticated user with no profile at all to the profile page (foster branch)', function () {
    [$animal] = verifiedOrgAnimalWithFosterStaff();

    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(AnimalDetail::class, ['animal' => $animal])
        ->call('requestFoster')
        ->assertRedirect(route('filament.adopter.pages.manage-adopter-profile'));

    expect(FosterApplication::query()->count())->toBe(0);

    Notification::assertNothingSent();
});

it('submits the foster request and notifies the org staff for a user with a complete profile', function () {
    [$animal, $staff] = verifiedOrgAnimalWithFosterStaff();

    $user = User::factory()->create();
    AdopterProfile::factory()->forUser($user)->complete()->create();

    Livewire::actingAs($user)
        ->test(AnimalDetail::class, ['animal' => $animal])
        ->call('requestFoster')
        ->assertSet('hasRequestedFoster', true)
        ->assertSet('fosterMessageIsError', false)
        ->assertSet('fosterMessage', __('fostering.request.flash_success'));

    $requests = FosterApplication::query()->get();

    expect($requests)->toHaveCount(1);

    $request = $requests->first();

    expect($request->statusEnum())->toBe(FosterApplicationStatus::Submitted)
        ->and($request->animal_id)->toBe($animal->getKey())
        ->and($request->user_id)->toBe($user->getKey())
        ->and($request->organization_id)->toBe($animal->organization_id);

    Notification::assertSentTo($staff, NewFosterApplicationReceived::class);
});

it('surfaces a friendly duplicate message on a second foster request and keeps a single request', function () {
    [$animal] = verifiedOrgAnimalWithFosterStaff();

    $user = User::factory()->create();
    AdopterProfile::factory()->forUser($user)->complete()->create();

    // Prima richiesta di affido già inviata.
    FosterApplication::factory()->submitted()->forAnimal($animal)->forAdopter($user)->create();

    Livewire::actingAs($user)
        ->test(AnimalDetail::class, ['animal' => $animal])
        ->call('requestFoster')
        ->assertSet('hasRequestedFoster', true)
        ->assertSet('fosterMessageIsError', false)
        ->assertSet('fosterMessage', __('fostering.request.flash_duplicate'));

    expect(FosterApplication::query()->count())->toBe(1);
});

it('shows the foster CTA on the public animal page', function () {
    [$animal] = verifiedOrgAnimalWithFosterStaff();

    $this->get(route('animals.show', $animal))
        ->assertOk()
        ->assertSee(__('fostering.request.cta_button'));
});
