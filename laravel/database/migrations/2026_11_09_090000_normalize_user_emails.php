<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Porta in forma canonica (senza spazi ai lati, in minuscolo) le email degli utenti
 * salvate prima del mutator di App\Models\User::email(): gli account creati dai pannelli
 * Filament (es. registrazione /manage) le tenevano come scritte, mentre /accedi cerca
 * l'indirizzo in minuscolo e su PostgreSQL non li trovava.
 *
 * Conflitti: se due o più account hanno la stessa email a meno di maiuscole o spazi
 * (`Mario@Example.com` e `mario@example.com`) NON si fondono e nessuno viene toccato: si
 * scrive un warning nel log con gli id coinvolti (non gli indirizzi, dati personali) e si
 * va avanti. Vanno risolti a mano (quale account tenere, dove spostare candidature,
 * membership e preferiti); dopo, una nuova esecuzione di questa logica è innocua.
 *
 * La forma canonica è scritta qui per esteso (e non presa da App\Support\Auth\EmailAddress)
 * perché una migrazione deve restare la stessa anche se il codice applicativo cambia.
 * Gli aggiornamenti passano dal query builder: niente eventi del modello né timestamp.
 */
return new class extends Migration
{
    public function up(): void
    {
        /** @var array<string, list<array{id: int, email: string}>> $groups */
        $groups = [];

        foreach (DB::table('users')->select(['id', 'email'])->lazyById(500) as $user) {
            $email = (string) $user->email;
            $groups[mb_strtolower(trim($email))][] = ['id' => (int) $user->id, 'email' => $email];
        }

        foreach ($groups as $normalized => $users) {
            if (count($users) > 1) {
                Log::warning('Email normalization skipped: accounts differ only by letter case or spaces.', [
                    'user_ids' => array_column($users, 'id'),
                ]);

                continue;
            }

            $user = $users[0];

            if ($user['email'] !== $normalized) {
                DB::table('users')->where('id', $user['id'])->update(['email' => (string) $normalized]);
            }
        }
    }

    /**
     * Irreversibile: la grafia originale degli indirizzi non è stata conservata (e una
     * email in minuscolo resta valida per lo stesso account), quindi non c'è nulla da
     * ripristinare.
     */
    public function down(): void {}
};
