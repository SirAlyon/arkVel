<?php

declare(strict_types=1);

use App\Actions\Animals\FindFavoritedAnimalIds;
use App\Livewire\AnimalDetail;
use App\Livewire\FavoriteButton;
use App\Models\Animal;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Animale pubblicamente visibile (Available + ente Verified): l'unico contesto
 * in cui il toggle è offerto (la card compare solo per animali visibili).
 */
function visibleAnimal(): Animal
{
    $org = Organization::factory()->verified()->create();

    return Animal::factory()->available()->forOrganization($org)->create();
}

it('redirects a guest to the adopter login and creates no favorite', function () {
    $animal = visibleAnimal();

    Livewire::test(FavoriteButton::class, ['animal' => $animal])
        ->assertSet('favorited', false)
        ->call('toggle')
        ->assertRedirect(route('login'));

    expect($animal->favoritedBy()->count())->toBe(0);
});

it('lets an authenticated user favorite then unfavorite an animal', function () {
    $user = User::factory()->create();
    $animal = visibleAnimal();

    $component = Livewire::actingAs($user)
        ->test(FavoriteButton::class, ['animal' => $animal])
        ->assertSet('favorited', false);

    // Add
    $component->call('toggle')->assertSet('favorited', true);
    expect($user->favorites()->whereKey($animal->getKey())->exists())->toBeTrue();

    // Remove
    $component->call('toggle')->assertSet('favorited', false);
    expect($user->favorites()->whereKey($animal->getKey())->exists())->toBeFalse();
});

it('reflects the initial favorited state', function () {
    $user = User::factory()->create();
    $animal = visibleAnimal();
    $user->favorites()->attach($animal->getKey());

    Livewire::actingAs($user)
        ->test(FavoriteButton::class, ['animal' => $animal])
        ->assertSet('favorited', true);
});

it('respects the unique constraint: toggling on twice keeps a single pivot row', function () {
    $user = User::factory()->create();
    $animal = visibleAnimal();

    // First toggle attaches; toggle() flips the flag, so a real second "add"
    // must go through a fresh component whose initial state is already true.
    Livewire::actingAs($user)
        ->test(FavoriteButton::class, ['animal' => $animal])
        ->call('toggle')
        ->assertSet('favorited', true);

    // Guard: a direct duplicate attach must not create a second row (DB unique).
    expect(
        fn () => $user->favorites()->attach($animal->getKey())
    )->toThrow(QueryException::class);

    expect($user->favorites()->count())->toBe(1);
});

it('only affects the current users pivot rows when toggling', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $animal = visibleAnimal();

    // The other user already favorited the same animal.
    $other->favorites()->attach($animal->getKey());

    // The owner toggles: their own row is created, the other user is untouched.
    Livewire::actingAs($owner)
        ->test(FavoriteButton::class, ['animal' => $animal])
        ->call('toggle')
        ->assertSet('favorited', true);

    expect($owner->favorites()->whereKey($animal->getKey())->exists())->toBeTrue()
        ->and($other->favorites()->whereKey($animal->getKey())->exists())->toBeTrue();

    // The owner toggles off: only their row is detached.
    Livewire::actingAs($owner)
        ->test(FavoriteButton::class, ['animal' => $animal])
        ->call('toggle')
        ->assertSet('favorited', false);

    expect($owner->favorites()->whereKey($animal->getKey())->exists())->toBeFalse()
        ->and($other->favorites()->whereKey($animal->getKey())->exists())->toBeTrue();
});

it('renders the favorite toggle on a publicly visible animal detail page', function () {
    $animal = visibleAnimal();

    Livewire::test(AnimalDetail::class, ['animal' => $animal])
        ->assertSeeLivewire(FavoriteButton::class);
});

it('names the toggle after the animal so buttons in a grid are distinct', function () {
    $user = User::factory()->create();
    $animal = visibleAnimal();
    $animal->forceFill(['name' => 'Fido'])->save();

    Livewire::actingAs($user)
        ->test(FavoriteButton::class, ['animal' => $animal])
        ->assertSet('animalName', 'Fido')
        ->assertSeeHtml('aria-label="Salva Fido nei preferiti"')
        ->assertSeeHtml('aria-pressed="false"')
        ->call('toggle')
        // Toggle APG: il nome resta fisso, lo stato lo dice aria-pressed.
        ->assertSeeHtml('aria-label="Salva Fido nei preferiti"')
        ->assertSeeHtml('aria-pressed="true"');
});

it('translates the accessible name of the toggle', function () {
    app()->setLocale('en');
    $animal = visibleAnimal();
    $animal->forceFill(['name' => 'Luna'])->save();

    Livewire::test(FavoriteButton::class, ['animal' => $animal])
        ->assertSeeHtml('aria-label="Save Luna to favorites"');
});

it('does not let the client tamper with the animal name', function () {
    Livewire::test(FavoriteButton::class, ['animal' => visibleAnimal()])
        ->set('animalName', 'Altro');
})->throws(CannotUpdateLockedPropertyException::class);

describe('initial state computed by the page', function () {
    it('takes the favorited state from the page without querying the database', function (bool $known) {
        $user = User::factory()->create();
        $animal = visibleAnimal();

        DB::enableQueryLog();

        Livewire::actingAs($user)
            ->test(FavoriteButton::class, ['animal' => $animal, 'favorited' => $known])
            ->assertSet('favorited', $known);

        $favoriteQueries = array_filter(DB::getQueryLog(), static fn (array $query): bool => str_contains($query['query'], 'favorites'));

        expect($favoriteQueries)->toBe([]);
    })->with([true, false]);

    it('still asks the database when the page passes nothing', function () {
        $user = User::factory()->create();
        $animal = visibleAnimal();
        $user->favorites()->attach($animal->getKey());

        Livewire::actingAs($user)
            ->test(FavoriteButton::class, ['animal' => $animal, 'favorited' => null])
            ->assertSet('favorited', true);
    });

    it('never shows a guest a favorite, whatever the page passes', function () {
        Livewire::test(FavoriteButton::class, ['animal' => visibleAnimal(), 'favorited' => true])
            ->assertSet('favorited', false)
            ->call('toggle')
            ->assertRedirect(route('login'));
    });

    it('reads the real pivot after a toggle, not the state passed by the page', function () {
        $user = User::factory()->create();
        $animal = visibleAnimal();

        // La pagina dice «preferito» ma la pivot è vuota: il toggle aggiunge e rilegge.
        Livewire::actingAs($user)
            ->test(FavoriteButton::class, ['animal' => $animal, 'favorited' => true])
            ->call('toggle')
            ->assertSet('favorited', true);

        expect($user->favorites()->whereKey($animal->getKey())->exists())->toBeTrue();
    });
});

describe('FindFavoritedAnimalIds', function () {
    it('returns the ids of the given animals in the users wishlist, in one query', function () {
        $user = User::factory()->create();
        $other = User::factory()->create();
        [$first, $second, $third] = [visibleAnimal(), visibleAnimal(), visibleAnimal()];
        $user->favorites()->attach([$third->getKey(), $first->getKey()]);
        $other->favorites()->attach($second->getKey());

        DB::enableQueryLog();

        $ids = app(FindFavoritedAnimalIds::class)->handle($user, [$first, $second, $third->getKey()]);

        expect($ids)->toBe([$first->getKey(), $third->getKey()])
            ->and(DB::getQueryLog())->toHaveCount(1);
    });

    it('does not query for a guest or an empty page', function () {
        DB::enableQueryLog();

        expect(app(FindFavoritedAnimalIds::class)->handle(null, [visibleAnimal()]))->toBe([]);

        DB::flushQueryLog();

        expect(app(FindFavoritedAnimalIds::class)->handle(User::factory()->create(), []))->toBe([]);
        expect(array_filter(DB::getQueryLog(), static fn (array $query): bool => str_contains($query['query'], 'favorites')))->toBe([]);
    });
});
