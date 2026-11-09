<?php

declare(strict_types=1);

use App\Livewire\Auth\LoginPage;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Auth\Pages\Register;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/*
 | Email in forma canonica (senza spazi ai lati, in minuscolo) su App\Models\User, da
 | qualunque parte arrivi l'account: /accedi cerca l'indirizzo in minuscolo, quindi un
 | account creato con le maiuscole (es. dalla registrazione del pannello /manage) deve
 | poter entrare.
 */

afterEach(function (): void {
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

it('stores the email trimmed and in lower case', function (): void {
    $user = User::factory()->create(['email' => '  Mario.Rossi@Example.COM ']);

    expect($user->email)->toBe('mario.rossi@example.com')
        ->and(DB::table('users')->where('id', $user->id)->value('email'))->toBe('mario.rossi@example.com');

    $user->update(['email' => 'ÈLISA@Example.com']);

    expect($user->refresh()->email)->toBe('èlisa@example.com');
});

it('normalizes the email of an account registered from the shelter panel', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('shelter'));

    Livewire::test(Register::class)
        ->fillForm([
            'name' => 'Operatrice Maiuscola',
            'email' => 'Operatrice@Rifugio.Example',
            'password' => 'password-123',
            'passwordConfirmation' => 'password-123',
        ])
        ->call('register')
        ->assertHasNoFormErrors();

    expect(DB::table('users')->where('name', 'Operatrice Maiuscola')->value('email'))->toBe('operatrice@rifugio.example');
});

it('signs in from /accedi an account created with capital letters, however the address is typed', function (string $typed): void {
    $user = User::factory()->create(['email' => 'Giulia.Bianchi@Example.com', 'password' => 'secret-password']);

    Livewire::test(LoginPage::class)
        ->set('email', $typed)
        ->set('password', 'secret-password')
        ->call('authenticate')
        ->assertHasNoErrors();

    $this->assertAuthenticatedAs($user);
})->with([
    'as created' => 'Giulia.Bianchi@Example.com',
    'lower case' => 'giulia.bianchi@example.com',
    'upper case with spaces' => '  GIULIA.BIANCHI@EXAMPLE.COM ',
]);
