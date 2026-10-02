<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MessageThreadFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Thread di conversazione fra un ente (organization) e un adottante (user).
 *
 * I due estremi della conversazione sono l'ENTE (lato staff: tutti i membri
 * verificati) e il singolo ADOTTANTE (la colonna user_id). Il contesto opzionale
 * (animale, candidatura) aggancia la conversazione a una pratica.
 *
 * Model magro: relazioni, cast, scope e HELPER DI LETTURA del non-letto. Nessuna
 * business logic: la creazione del thread, l'invio dei messaggi, l'aggiornamento
 * dei marcatori di lettura e le notifiche vivono nelle Action in
 * App\Actions\Messaging.
 *
 * Non-letto (design): ogni lato ha il proprio marcatore temporale
 * (`adopter_last_read_at`, `org_last_read_at`). Il non-letto di un lato sono i
 * messaggi creati DOPO il proprio marcatore e inviati DALL'ALTRO lato (i propri
 * messaggi sono sempre "letti"). Il confine fra i lati è deterministico: un
 * messaggio è "dell'adottante" se il suo mittente è `user_id`, altrimenti è
 * "dell'ente". Nessuna tabella di stato per-utente: due colonne bastano e sono
 * banali da testare.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $user_id
 * @property int|null $animal_id
 * @property int|null $adoption_application_id
 * @property string|null $subject
 * @property Carbon|null $last_message_at
 * @property Carbon|null $adopter_last_read_at
 * @property Carbon|null $org_last_read_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization $organization
 * @property-read User $user
 * @property-read Animal|null $animal
 * @property-read AdoptionApplication|null $adoptionApplication
 * @property-read Collection<int, Message> $messages
 */
class MessageThread extends Model
{
    /** @use HasFactory<MessageThreadFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'user_id',
        'animal_id',
        'adoption_application_id',
        'subject',
        'last_message_at',
        'adopter_last_read_at',
        'org_last_read_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'adopter_last_read_at' => 'datetime',
            'org_last_read_at' => 'datetime',
        ];
    }

    /**
     * Ente partecipante (lato staff).
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Adottante partecipante.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Animale di contesto (opzionale).
     *
     * @return BelongsTo<Animal, $this>
     */
    public function animal(): BelongsTo
    {
        return $this->belongsTo(Animal::class);
    }

    /**
     * Candidatura di adozione di contesto (opzionale).
     *
     * @return BelongsTo<AdoptionApplication, $this>
     */
    public function adoptionApplication(): BelongsTo
    {
        return $this->belongsTo(AdoptionApplication::class);
    }

    /**
     * Messaggi del thread in ordine cronologico (per id, monotono crescente).
     *
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('id');
    }

    /**
     * Conversazioni di un adottante (lato account): tutte quelle in cui l'utente
     * è il partecipante adottante.
     *
     * @param  Builder<MessageThread>  $query
     */
    #[Scope]
    protected function forAdopter(Builder $query, User $user): void
    {
        $query->where('user_id', $user->getKey());
    }

    /**
     * Conversazioni di un ente (lato shelter/inbox): tutte quelle del tenant.
     *
     * @param  Builder<MessageThread>  $query
     */
    #[Scope]
    protected function forOrganization(Builder $query, Organization $organization): void
    {
        $query->where('organization_id', $organization->getKey());
    }

    /**
     * Messaggi non letti per l'ADOTTANTE: inviati dall'ente (mittente diverso
     * dall'adottante) dopo l'ultima lettura dell'adottante. Semplice helper di
     * lettura, nessuna business logic.
     */
    public function unreadCountForAdopter(): int
    {
        return $this->unreadCountSince(
            $this->adopter_last_read_at,
            fromAdopter: false,
        );
    }

    /**
     * Messaggi non letti per l'ENTE: inviati dall'adottante dopo l'ultima lettura
     * dell'ente.
     */
    public function unreadCountForOrg(): int
    {
        return $this->unreadCountSince(
            $this->org_last_read_at,
            fromAdopter: true,
        );
    }

    /**
     * Conta i messaggi del thread provenienti da un lato, creati dopo un
     * marcatore di lettura. Un marcatore nullo significa "mai letto": tutti i
     * messaggi di quel lato contano come non letti.
     *
     * @param  bool  $fromAdopter  true per contare i messaggi dell'adottante,
     *                             false per quelli dell'ente
     */
    private function unreadCountSince(?Carbon $readAt, bool $fromAdopter): int
    {
        return $this->messages()
            ->when(
                $fromAdopter,
                fn (Builder $query): Builder => $query->where('user_id', $this->user_id),
                fn (Builder $query): Builder => $query->where('user_id', '!=', $this->user_id),
            )
            ->when(
                $readAt !== null,
                fn (Builder $query): Builder => $query->where('created_at', '>', $readAt),
            )
            ->count();
    }
}
