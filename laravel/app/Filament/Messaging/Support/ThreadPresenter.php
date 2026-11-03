<?php

declare(strict_types=1);

namespace App\Filament\Messaging\Support;

use App\Filament\Adopter\Pages\MyMessages;
use App\Filament\Shelter\Pages\Conversations;
use App\Models\Animal;
use App\Models\Message;
use App\Models\MessageThread;
use Illuminate\Support\Str;

/**
 * Presentazione di sola lettura di un thread per le tabelle di conversazione,
 * CONDIVISA dal pannello adottante ({@see MyMessages})
 * e dal pannello ente ({@see Conversations}).
 *
 * Isolare qui la derivazione di oggetto/estratto evita di duplicare la stessa
 * logica (e la stessa gestione dei contesti nulli) fra i due pannelli. Nessuna
 * business logic: solo formattazione di campi del model.
 */
final class ThreadPresenter
{
    private const int EXCERPT_LENGTH = 60;

    /**
     * Etichetta sintetica del thread: l'oggetto esplicito se presente, altrimenti il
     * nome dell'animale di contesto, altrimenti un segnaposto "senza oggetto".
     */
    public static function subject(MessageThread $thread): string
    {
        if ($thread->subject !== null && $thread->subject !== '') {
            return $thread->subject;
        }

        $animal = $thread->animal;

        if ($animal instanceof Animal) {
            return $animal->name;
        }

        return __('messaging.thread.no_subject');
    }

    /**
     * Estratto dell'ultimo messaggio del thread (o un trattino se vuoto). Usa la
     * relazione già caricata (`messages`) per evitare N+1.
     */
    public static function excerpt(MessageThread $thread): string
    {
        $last = $thread->messages->last();

        if (! $last instanceof Message) {
            return '—';
        }

        return Str::limit($last->body, self::EXCERPT_LENGTH);
    }
}
