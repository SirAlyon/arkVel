<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

/*
 | Migrazione 2026_11_09_090000_normalize_user_emails: porta in minuscolo (e senza spazi)
 | le email salvate prima del mutator di User. Gli account che differiscono solo per
 | maiuscole o spazi non si fondono: restano com'erano e finiscono in un warning nel log.
 | Le righe si inseriscono col query builder, che salta il mutator, come i dati storici.
 */

function insertLegacyUser(string $email): int
{
    return DB::table('users')->insertGetId([
        'name' => 'Utente storico',
        'email' => $email,
        'password' => bcrypt('password'),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function normalizeUserEmailsMigration(): Migration
{
    return require database_path('migrations/2026_11_09_090000_normalize_user_emails.php');
}

it('lower-cases and trims the stored emails', function (): void {
    $mixed = insertLegacyUser('Mario.Rossi@Example.COM');
    $padded = insertLegacyUser(' anna@example.com ');
    $clean = insertLegacyUser('luca@example.com');

    normalizeUserEmailsMigration()->up();

    expect(DB::table('users')->where('id', $mixed)->value('email'))->toBe('mario.rossi@example.com')
        ->and(DB::table('users')->where('id', $padded)->value('email'))->toBe('anna@example.com')
        ->and(DB::table('users')->where('id', $clean)->value('email'))->toBe('luca@example.com');
});

it('leaves the accounts that differ only by letter case untouched and logs their ids', function (): void {
    Log::spy();

    $upper = insertLegacyUser('Sara@Example.com');
    $lower = insertLegacyUser('sara@example.com');
    $other = insertLegacyUser('Piero@Example.com');

    normalizeUserEmailsMigration()->up();

    expect(DB::table('users')->where('id', $upper)->value('email'))->toBe('Sara@Example.com')
        ->and(DB::table('users')->where('id', $lower)->value('email'))->toBe('sara@example.com')
        ->and(DB::table('users')->where('id', $other)->value('email'))->toBe('piero@example.com');

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => $context === ['user_ids' => [$upper, $lower]]
            && ! str_contains($message.json_encode($context), 'sara'));
});

it('can run again without changes and has a no-op down', function (): void {
    $id = insertLegacyUser('Teo@Example.com');

    normalizeUserEmailsMigration()->up();
    normalizeUserEmailsMigration()->up();
    normalizeUserEmailsMigration()->down();

    expect(DB::table('users')->where('id', $id)->value('email'))->toBe('teo@example.com');
});
