<?php

declare(strict_types=1);

use App\Actions\Applications\SubmitApplication;
use App\Actions\Appointments\RequestAppointment;
use App\Actions\Donations\StartDonationCheckout;
use App\Actions\Donations\StartRecurringDonationCheckout;
use App\Actions\Fostering\SubmitFosterApplication;
use App\Actions\Messaging\ContactOrganizationAboutAnimal;
use App\Actions\Messaging\StartMessageThread;
use App\Actions\Organizations\SetOrganizationDemoMode;
use App\Enums\AppointmentType;
use App\Events\MessageSent;
use App\Exceptions\OrganizationIsDemo;
use App\Models\AdopterProfile;
use App\Models\AdoptionApplication;
use App\Models\Animal;
use App\Models\Appointment;
use App\Models\Donation;
use App\Models\FosterApplication;
use App\Models\Message;
use App\Models\MessageThread;
use App\Models\Organization;
use App\Models\RecurringDonation;
use App\Models\User;
use App\Support\Billing\CreatesDonationCheckout;
use App\Support\Billing\ManagesRecurringDonation;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\FakeDonationCheckout;
use Tests\Support\FakeRecurringDonationCheckout;

// Ente dimostrativo (is_demo): ogni Action che invia dati personali o denaro a un ente
// rifiuta con OrganizationIsDemo PRIMA di scrivere (nessuna riga, nessuna sessione
// Stripe), e continua a funzionare per un ente normale (regressione).

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    Event::fake([MessageSent::class]);
    config()->set('cashier.key', 'pk_test_fake');
    config()->set('cashier.secret', 'sk_test_fake');
});

function demoTestAnimal(bool $demo): Animal
{
    $factory = Organization::factory()->verified();
    $organization = ($demo ? $factory->demo() : $factory)->create();

    return Animal::factory()->available()->forOrganization($organization)->create();
}

function demoTestAdopter(): User
{
    $adopter = User::factory()->create();
    AdopterProfile::factory()->forUser($adopter)->complete()->create();

    return $adopter->load('adopterProfile');
}

it('refuses an adoption application for a demo organization and persists nothing', function () {
    expect(fn () => app(SubmitApplication::class)->handle(demoTestAdopter(), demoTestAnimal(demo: true)))
        ->toThrow(OrganizationIsDemo::class, __('demo.refused'));

    expect(AdoptionApplication::query()->count())->toBe(0);
    Notification::assertNothingSent();
});

it('still accepts an adoption application for a normal organization', function () {
    app(SubmitApplication::class)->handle(demoTestAdopter(), demoTestAnimal(demo: false));

    expect(AdoptionApplication::query()->count())->toBe(1);
});

it('refuses a foster application for a demo organization and persists nothing', function () {
    expect(fn () => app(SubmitFosterApplication::class)->handle(demoTestAdopter(), demoTestAnimal(demo: true)))
        ->toThrow(OrganizationIsDemo::class);

    expect(FosterApplication::query()->count())->toBe(0);
});

it('still accepts a foster application for a normal organization', function () {
    app(SubmitFosterApplication::class)->handle(demoTestAdopter(), demoTestAnimal(demo: false));

    expect(FosterApplication::query()->count())->toBe(1);
});

it('refuses a visit booking for a demo organization and persists nothing', function () {
    expect(fn () => app(RequestAppointment::class)->handle(
        User::factory()->create(),
        demoTestAnimal(demo: true),
        AppointmentType::Visit,
        now()->addDays(3)->setTime(10, 0),
    ))->toThrow(OrganizationIsDemo::class);

    expect(Appointment::query()->count())->toBe(0);
});

it('still accepts a visit booking for a normal organization', function () {
    app(RequestAppointment::class)->handle(
        User::factory()->create(),
        demoTestAnimal(demo: false),
        AppointmentType::Visit,
        now()->addDays(3)->setTime(10, 0),
    );

    expect(Appointment::query()->count())->toBe(1);
});

it('refuses to contact a demo organization and persists nothing', function () {
    expect(fn () => app(ContactOrganizationAboutAnimal::class)->handle(User::factory()->create(), demoTestAnimal(demo: true), 'Ciao!'))
        ->toThrow(OrganizationIsDemo::class);

    expect(MessageThread::query()->count())->toBe(0)
        ->and(Message::query()->count())->toBe(0);
});

it('refuses to append to an existing thread once the organization is a demo', function () {
    $adopter = User::factory()->create();
    $animal = demoTestAnimal(demo: false);
    app(ContactOrganizationAboutAnimal::class)->handle($adopter, $animal, 'Primo messaggio');

    app(SetOrganizationDemoMode::class)->handle($animal->organization, true);

    expect(fn () => app(ContactOrganizationAboutAnimal::class)->handle($adopter, $animal->fresh(), 'Secondo messaggio'))
        ->toThrow(OrganizationIsDemo::class);

    expect(Message::query()->count())->toBe(1);
});

it('still contacts a normal organization', function () {
    $result = app(ContactOrganizationAboutAnimal::class)->handle(User::factory()->create(), demoTestAnimal(demo: false), 'Ciao!');

    expect($result->reusedExistingThread)->toBeFalse()
        ->and(MessageThread::query()->count())->toBe(1);
});

it('refuses a thread started by an adopter towards a demo organization', function () {
    $adopter = User::factory()->create();
    $organization = Organization::factory()->verified()->demo()->create();

    expect(fn () => app(StartMessageThread::class)->handle($adopter, $organization, $adopter, 'Ciao!'))
        ->toThrow(OrganizationIsDemo::class);

    expect(MessageThread::query()->count())->toBe(0);
});

it('lets the staff of a demo organization start a thread (no data is sent to the organization)', function () {
    $staff = User::factory()->create();
    $adopter = User::factory()->create();
    $organization = Organization::factory()->verified()->demo()->create();

    app(StartMessageThread::class)->handle($staff, $organization, $adopter, 'Ciao!');

    expect(MessageThread::query()->count())->toBe(1);
});

it('refuses a one-off donation to a demo organization without creating a stripe session', function () {
    $fake = new FakeDonationCheckout;
    app()->instance(CreatesDonationCheckout::class, $fake);

    expect(fn () => app(StartDonationCheckout::class)->handle(
        organization: Organization::factory()->verified()->demo()->create(),
        amountCents: 1_000,
        currency: 'eur',
        donor: User::factory()->create(),
    ))->toThrow(OrganizationIsDemo::class);

    expect(Donation::query()->count())->toBe(0)
        ->and($fake->lastDonation)->toBeNull();
});

it('still starts a one-off donation for a normal organization', function () {
    $fake = new FakeDonationCheckout;
    app()->instance(CreatesDonationCheckout::class, $fake);

    $url = app(StartDonationCheckout::class)->handle(
        organization: Organization::factory()->verified()->create(),
        amountCents: 1_000,
        currency: 'eur',
        donor: User::factory()->create(),
    );

    expect($url)->toBe($fake->url)
        ->and(Donation::query()->count())->toBe(1);
});

it('refuses a recurring donation to a demo organization without creating a stripe session', function () {
    $fake = new FakeRecurringDonationCheckout;
    app()->instance(ManagesRecurringDonation::class, $fake);

    expect(fn () => app(StartRecurringDonationCheckout::class)->handle(
        organization: Organization::factory()->verified()->demo()->create(),
        amountCents: 1_000,
        currency: 'eur',
        donor: User::factory()->create(),
    ))->toThrow(OrganizationIsDemo::class);

    expect(RecurringDonation::query()->count())->toBe(0)
        ->and($fake->lastRecurring)->toBeNull();
});

it('still starts a recurring donation for a normal organization', function () {
    $fake = new FakeRecurringDonationCheckout;
    app()->instance(ManagesRecurringDonation::class, $fake);

    $url = app(StartRecurringDonationCheckout::class)->handle(
        organization: Organization::factory()->verified()->create(),
        amountCents: 1_000,
        currency: 'eur',
        donor: User::factory()->create(),
    );

    expect($url)->toBe($fake->url)
        ->and(RecurringDonation::query()->count())->toBe(1);
});

it('renders an uncaught refusal as a 403, never a 500', function () {
    $organization = Organization::factory()->demo()->make();

    $response = app(ExceptionHandler::class)->render(Request::create('/'), OrganizationIsDemo::for($organization));

    expect($response->getStatusCode())->toBe(403);
});

it('toggles the demo flag and logs the change once', function () {
    $organization = Organization::factory()->verified()->create();
    $admin = User::factory()->create();

    app(SetOrganizationDemoMode::class)->handle($organization, true, $admin);
    app(SetOrganizationDemoMode::class)->handle($organization, true, $admin);

    expect($organization->fresh()->is_demo)->toBeTrue()
        ->and(Activity::query()->where('description', 'organization.demo_enabled')->count())->toBe(1);

    app(SetOrganizationDemoMode::class)->handle($organization, false, $admin);

    expect($organization->fresh()->is_demo)->toBeFalse()
        ->and(Activity::query()->where('description', 'organization.demo_disabled')->sole()->causer_id)->toBe($admin->getKey());
});

it('defaults new organizations to non-demo', function () {
    expect(Organization::factory()->create()->fresh()->is_demo)->toBeFalse();
});
