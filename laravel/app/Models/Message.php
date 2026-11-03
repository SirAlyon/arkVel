<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Singolo messaggio di un thread di conversazione ente↔adottante.
 *
 * Model magro: solo relazioni. L'immutabilità (nessun edit/delete) e
 * l'orchestrazione (aggiornamento del thread, notifica) vivono nelle Action in
 * App\Actions\Messaging.
 *
 * @property int $id
 * @property int $message_thread_id
 * @property int $user_id
 * @property string $body
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read MessageThread $thread
 * @property-read User $user
 */
class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'message_thread_id',
        'user_id',
        'body',
    ];

    /**
     * Thread di appartenenza.
     *
     * @return BelongsTo<MessageThread, $this>
     */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(MessageThread::class, 'message_thread_id');
    }

    /**
     * Mittente del messaggio (adottante oppure operatore dell'ente).
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
