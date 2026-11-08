<?php

declare(strict_types=1);

use App\Actions\Organizations\AddOrganizationMember;
use App\Enums\ApplicationStatus;
use App\Enums\MembershipRole;
use App\Livewire\AnimalDetail;
use App\Models\AdopterProfile;
use App\Models\AdoptionApplication;
use App\Models\Animal;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\NewApplicationReceived;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

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
function verifiedOrgAnimalWithStaff(): array
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

it('redirects a guest to the adopter login and creates no application', function () {
    [$animal] = verifiedOrgAnimalWithStaff();

    Livewire::test(AnimalDetail::class, ['animal' => $animal])
        ->call('apply')
        ->assertRedirect(route('login'));

    expect(AdoptionApplication::query()->count())->toBe(0)
        ->and(session('url.intended'))->toBe(route('animals.show', $animal));

    Notification::assertNothingSent();
});

it('redirects an authenticated user without a complete profile to the profile page and creates no application', function () {
    [$animal] = verifiedOrgAnimalWithStaff();

    // Profilo esistente ma incompleto (nessun consenso GDPR).
    $user = User::factory()->create();
    AdopterProfile::factory()->forUser($user)->withoutConsent()->create();

    Livewire::actingAs($user)
        ->test(AnimalDetail::class, ['animal' => $animal])
        ->call('apply')
        ->assertRedirect(route('filament.adopter.pages.manage-adopter-profile'));

    expect(AdoptionApplication::query()->count())->toBe(0);

    Notification::assertNothingSent();
});

it('redirects an authenticated user with no profile at all to the profile page', function () {
    [$animal] = verifiedOrgAnimalWithStaff();

    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(AnimalDetail::class, ['animal' => $animal])
        ->call('apply')
        ->assertRedirect(route('filament.adopter.pages.manage-adopter-profile'));

    expect(AdoptionApplication::query()->count())->toBe(0);

    Notification::assertNothingSent();
});

it('submits the application and notifies the org staff for a user with a complete profile', function () {
    [$animal, $staff] = verifiedOrgAnimalWithStaff();

    $user = User::factory()->create();
    AdopterProfile::factory()->forUser($user)->complete()->create();

    Livewire::actingAs($user)
        ->test(AnimalDetail::class, ['animal' => $animal])
        ->call('apply')
        ->assertSet('hasApplied', true)
        ->assertSet('applyMessageIsError', false)
        ->assertSet('applyMessage', __('applications.apply.flash_success'));

    $applications = AdoptionApplication::query()->get();

    expect($applications)->toHaveCount(1);

    $application = $applications->first();

    expect($application->statusEnum())->toBe(ApplicationStatus::Submitted)
        ->and($application->animal_id)->toBe($animal->getKey())
        ->and($application->user_id)->toBe($user->getKey())
        ->and($application->organization_id)->toBe($animal->organization_id);

    Notification::assertSentTo($staff, NewApplicationReceived::class);
});

it('surfaces a friendly duplicate message on a second apply and keeps a single application', function () {
    [$animal] = verifiedOrgAnimalWithStaff();

    $user = User::factory()->create();
    AdopterProfile::factory()->forUser($user)->complete()->create();

    // Prima candidatura già inviata.
    AdoptionApplication::factory()->submitted()->forAnimal($animal)->forAdopter($user)->create();

    Livewire::actingAs($user)
        ->test(AnimalDetail::class, ['animal' => $animal])
        ->call('apply')
        ->assertSet('hasApplied', true)
        ->assertSet('applyMessageIsError', false)
        ->assertSet('applyMessage', __('applications.apply.flash_duplicate'));

    expect(AdoptionApplication::query()->count())->toBe(1);
});
