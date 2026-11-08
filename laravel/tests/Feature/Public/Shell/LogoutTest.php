<?php

declare(strict_types=1);

use App\Enums\MembershipRole;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/*
 | Uscita dalla shell pubblica (POST /esci, logout): non passa dal pannello adottante
 | (il cui authMiddleware darebbe il ruolo Adopter a chiunque) e torna alla home.
 */

it('logs out, invalidates the session and goes back home', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['secret' => 'x'])
        ->post(route('logout'))
        ->assertRedirect(route('home'))
        ->assertSessionMissing('secret');

    $this->assertGuest();
});

it('does not give the Adopter role to the staff of an organization', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $staff = member(Organization::factory()->verified()->create(), MembershipRole::Volunteer);
    $roles = static fn (): array => DB::table(config('permission.table_names.model_has_roles'))
        ->where('model_id', $staff->getKey())
        ->orderBy('role_id')
        ->get(['role_id', 'team_id'])
        ->map(static fn (object $row): array => (array) $row)
        ->all();
    $before = $roles();

    $this->actingAs($staff)->post(route('logout'))->assertRedirect(route('home'));

    $this->assertGuest();
    expect($roles())->toBe($before);
});

it('is served from the header and the mobile menu of the public shell', function (): void {
    $html = (string) $this->actingAs(User::factory()->create())->get(route('home'))->getContent();

    expect(substr_count($html, 'action="'.route('logout').'"'))->toBe(2)
        ->and($html)->not->toContain(route('filament.adopter.auth.logout'));
});

it('just goes home for a guest', function (): void {
    $this->post(route('logout'))->assertRedirect(route('home'));

    $this->assertGuest();
});
