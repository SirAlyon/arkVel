<?php

declare(strict_types=1);

use App\Enums\MembershipRole;
use App\Models\Organization;
use App\Models\User;
use App\Support\Site\AccountLinks;
use App\Support\Site\NavItem;
use App\Support\Site\SiteNavigation;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;

uses(RefreshDatabase::class);

/*
 | Shell pubblica (UI-3): voci della navigazione principale e URL dell'account, in un solo
 | punto per header, menu mobile e footer.
 */

/** La navigazione vista da una richiesta sulla route indicata. */
function navigationOn(string $routeName): SiteNavigation
{
    $request = Request::create('/');
    $route = new Route('GET', '/', static fn (): string => '');
    $route->name($routeName);
    $request->setRouteResolver(static fn (): Route => $route);

    return new SiteNavigation($request);
}

/**
 * @return array<string, string|null>
 */
function currentStates(SiteNavigation $navigation): array
{
    return collect($navigation->primary())
        ->mapWithKeys(static fn (NavItem $item): array => [$item->key => $item->current])
        ->all();
}

describe('SiteNavigation', function (): void {
    it('lists the catalog and the organizations with translated labels and urls', function (): void {
        $items = navigationOn('home')->primary();

        expect($items)->toHaveCount(2)
            ->and($items[0])->key->toBe('catalog')->url->toBe(route('catalog'))->label->toBe('Catalogo')
            ->and($items[1])->key->toBe('organizations')->url->toBe(route('organizations.index'))->label->toBe('Enti');
    });

    it('marks the current page and the current section', function (string $route, array $states): void {
        expect(currentStates(navigationOn($route)))->toBe($states);
    })->with([
        'home' => ['home', ['catalog' => null, 'organizations' => null]],
        'catalog' => ['catalog', ['catalog' => 'page', 'organizations' => null]],
        'animal detail' => ['animals.show', ['catalog' => 'true', 'organizations' => null]],
        'organizations' => ['organizations.index', ['catalog' => null, 'organizations' => 'page']],
        'organization page' => ['organizations.show', ['catalog' => null, 'organizations' => 'true']],
        'donation thanks' => ['organizations.donate.thanks', ['catalog' => null, 'organizations' => 'true']],
    ]);

    it('exposes whether an item is active', function (): void {
        expect(new NavItem('catalog', '/animali', 'Catalogo', 'page'))->isActive()->toBeTrue()
            ->and(new NavItem('catalog', '/animali', 'Catalogo'))->isActive()->toBeFalse();
    });
});

describe('AccountLinks', function (): void {
    it('points to the adopter panel until the public auth pages exist (UI-5)', function (): void {
        $links = new AccountLinks;

        expect($links->login())->toBe(route('filament.adopter.auth.login'))
            ->and($links->register())->toBe(route('filament.adopter.auth.register'))
            ->and($links->account())->toBe(route('filament.adopter.home'))
            ->and($links->logout())->toBe(route('logout.public'))
            ->and($links->manage())->toBe(route('filament.shelter.tenant'));
    });

    it('lets only verified members of an operable organization manage it', function (): void {
        $this->seed(RolesAndPermissionsSeeder::class);
        $links = new AccountLinks;

        $adopter = User::factory()->create();
        $member = member(Organization::factory()->verified()->create(), MembershipRole::Staff);
        $pendingOrganizationMember = member(Organization::factory()->pending()->create(), MembershipRole::Owner);
        $suspendedOrganizationMember = member(Organization::factory()->suspended()->create(), MembershipRole::Owner);
        $unverifiedMember = User::factory()->create();
        $unverifiedMember->organizations()->attach(Organization::factory()->verified()->create(), [
            'role' => MembershipRole::Staff,
            'verified' => false,
        ]);

        expect($links->canManage(null))->toBeFalse()
            ->and($links->canManage($adopter))->toBeFalse()
            ->and($links->canManage($member))->toBeTrue()
            ->and($links->canManage($pendingOrganizationMember))->toBeTrue()
            ->and($links->canManage($suspendedOrganizationMember))->toBeFalse()
            ->and($links->canManage($unverifiedMember))->toBeFalse();
    });
});
