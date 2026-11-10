<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Actions\Donations\CancelRecurringDonation;
use App\Enums\MembershipRole;
use App\Enums\RecurringDonationStatus;
use App\Exceptions\CannotAnonymizeSoleOwner;
use App\Models\Donation;
use App\Models\RecurringDonation;
use App\Models\User;
use App\Support\Billing\StripeConfig;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

/**
 * Anonimizza l'account di un adottante: diritto all'oblio (GDPR art. 17).
 *
 * SCELTA D'ARCHITETTURA — anonimizzazione IN-PLACE, non hard delete. Rimuoviamo
 * /oscuriamo la PII della persona ma CONSERVIAMO le righe operative (candidature,
 * affidi, appuntamenti, messaggi, donazioni) legate all'utente ora anonimo:
 * preserva l'integrità referenziale e la storia dell'ente in forma anonima.
 *
 * Single responsibility: è l'UNICO punto in cui un account viene anonimizzato.
 *
 * IDEMPOTENTE: un account già anonimizzato (`anonymized_at` non-null) è un no-op.
 * La riga utente è bloccata in transazione (`lockForUpdate`) per serializzare con
 * richieste concorrenti. La GUARDIA unico-owner è valutata PRIMA di qualunque
 * mutazione: se fallisce, la transazione si interrompe senza intaccare alcun dato.
 *
 * L'activity log registra solo l'avvenuta anonimizzazione (`user.anonymized`),
 * MAI la PII cancellata.
 *
 * PUNTI PII — rimossi/oscurati:
 *  - User: name → placeholder i18n; email → placeholder univoco non recapitabile
 *    (`anonimizzato-{id}@utente.invalid`); password → hash di una stringa random
 *    (login impossibile); email_verified_at → null; remember_token azzerato;
 *    tutti i ruoli spatie staccati (team-independent); `anonymized_at` = now().
 *  - AdopterProfile: ELIMINATO (questionario sensibile).
 *  - Favorites: detach di tutte le righe pivot.
 *  - Donation (donor_user_id = utente): donor_name → placeholder, donor_email →
 *    null; importo/record MANTENUTI (base giuridica: obblighi contabili).
 *  - RecurringDonation attive/non terminali: portate a Canceled (disdetta Stripe
 *    best-effort, vedi sotto).
 *  - Notifiche DB dell'utente: eliminate.
 *  - Membership d'ente: staccate (per i non-owner e per gli owner con co-owner;
 *    l'unico-owner è rifiutato dalla guardia).
 *  - Sessioni (driver database): eliminate le righe identificabili dell'utente.
 *
 * PUNTI MANTENUTI (identità oscurata, non redatti):
 *  - Record operativi (candidature, affidi, appuntamenti) e CORPI DEI MESSAGGI:
 *    restano legati all'utente ora anonimo. Per il minimo difendibile di un pilota
 *    NON redigiamo il testo libero dei messaggi: prevale l'interesse legittimo
 *    dell'ente a conservare la conversazione; l'identità è comunque oscurata dal
 *    nome/email anonimizzati dell'utente mittente.
 *
 * STRIPE — best-effort: la disdetta su Stripe delle ricorrenti è tentata solo se
 * la piattaforma è configurata ({@see StripeConfig::configured()}) e la ricorrente
 * ha un `stripe_subscription_id`; delega a {@see CancelRecurringDonation}. Un
 * fallimento/che Stripe sia giù NON fa fallire l'anonimizzazione: la riga locale è
 * comunque portata a Canceled. L'anonimizzazione NON dipende da Stripe attivo.
 */
final class AnonymizeUserAccount
{
    public function __construct(
        private readonly CancelRecurringDonation $cancelRecurringDonation,
        private readonly StripeConfig $stripeConfig,
        private readonly PermissionRegistrar $permissionRegistrar,
    ) {}

    /**
     * @return bool true se l'account è stato anonimizzato ora; false se era già
     *              anonimizzato (no-op idempotente) o inesistente.
     *
     * @throws CannotAnonymizeSoleOwner se l'utente è l'unico titolare di un ente.
     */
    public function handle(User $user): bool
    {
        return DB::transaction(function () use ($user): bool {
            $fresh = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->first();

            if ($fresh === null || $fresh->anonymized_at !== null) {
                return false;
            }

            // Guardia PRIMA di mutare: non orfanare un ente.
            $this->guardNotSoleOwner($fresh);

            $id = (int) $fresh->getKey();

            $this->cancelActiveRecurringDonations($fresh);
            $this->anonymizeDonations($id);

            $fresh->adopterProfile()->delete();
            $fresh->favorites()->detach();
            $fresh->notifications()->delete();
            $fresh->organizations()->detach();

            $this->removeAllRoles($fresh);
            $this->purgeSessions($id);

            $fresh->forceFill([
                'name' => __('account.delete.name_placeholder'),
                'email' => sprintf('anonimizzato-%d@utente.invalid', $id),
                'password' => Str::random(64),
                'email_verified_at' => null,
                'remember_token' => null,
                'anonymized_at' => now(),
            ])->save();

            activity()
                ->performedOn($fresh)
                ->log('user.anonymized');

            return true;
        });
    }

    /**
     * Rifiuta se l'utente è l'UNICO titolare verificato di un ente: anonimizzarlo lo
     * orfanerebbe. Un owner con almeno un co-owner verificato è invece anonimizzabile
     * (la membership verrà staccata).
     *
     * @throws CannotAnonymizeSoleOwner
     */
    private function guardNotSoleOwner(User $user): void
    {
        /** @var list<int> $ownedOrganizationIds */
        $ownedOrganizationIds = DB::table('organization_user')
            ->where('user_id', $user->getKey())
            ->where('role', MembershipRole::Owner->value)
            ->where('verified', true)
            ->pluck('organization_id')
            ->map(static fn (mixed $value): int => (int) $value)
            ->all();

        foreach ($ownedOrganizationIds as $organizationId) {
            $hasOtherOwner = DB::table('organization_user')
                ->where('organization_id', $organizationId)
                ->where('role', MembershipRole::Owner->value)
                ->where('verified', true)
                ->where('user_id', '!=', $user->getKey())
                ->exists();

            if (! $hasOtherOwner) {
                throw CannotAnonymizeSoleOwner::for($user, $organizationId);
            }
        }
    }

    /**
     * Porta a Canceled le ricorrenti attive/non terminali dell'utente. Se Stripe è
     * configurato e la ricorrente ha un abbonamento, delega a CancelRecurringDonation
     * (che disdice su Stripe + transizione) in BEST-EFFORT: un errore di Stripe non
     * interrompe l'anonimizzazione, la riga locale è comunque portata a Canceled.
     */
    private function cancelActiveRecurringDonations(User $user): void
    {
        $recurrings = RecurringDonation::query()
            ->where('user_id', $user->getKey())
            ->whereIn('status', [
                RecurringDonationStatus::Pending->value,
                RecurringDonationStatus::Active->value,
                RecurringDonationStatus::PastDue->value,
            ])
            ->get();

        $stripeConfigured = $this->stripeConfig->configured();

        foreach ($recurrings as $recurring) {
            if ($stripeConfigured && filled($recurring->stripe_subscription_id)) {
                try {
                    $this->cancelRecurringDonation->handle($recurring, $user);

                    continue;
                } catch (Throwable) {
                    // Best-effort: Stripe giù/errore non deve bloccare l'oblio.
                    // Si prosegue con la sola disdetta locale qui sotto.
                }
            }

            if ($recurring->status->canTransitionTo(RecurringDonationStatus::Canceled)) {
                $recurring->forceFill([
                    'status' => RecurringDonationStatus::Canceled,
                    'canceled_at' => now(),
                ])->save();
            }
        }
    }

    /**
     * Oscura l'identità del donatore sulle donazioni dell'utente, MANTENENDO
     * importo e record (obblighi contabili): donor_name → placeholder, donor_email
     * → null. `donor_user_id` può restare (l'utente è ormai anonimo).
     */
    private function anonymizeDonations(int $userId): void
    {
        Donation::query()
            ->where('donor_user_id', $userId)
            ->update([
                'donor_name' => __('account.delete.donor_placeholder'),
                'donor_email' => null,
            ]);
    }

    /**
     * Stacca TUTTI i ruoli spatie dell'utente in modo TEAM-INDEPENDENT (query
     * diretta sul pivot, come {@see User::isSuperAdmin()}): la scope team-scoped di
     * spatie maschererebbe le assegnazioni fatte con un team id diverso da quello
     * corrente. Invalida poi la cache dei permessi.
     */
    private function removeAllRoles(User $user): void
    {
        /** @var array<string, string> $tables */
        $tables = config('permission.table_names');
        /** @var array<string, string> $columns */
        $columns = config('permission.column_names');

        DB::table($tables['model_has_roles'])
            ->where($columns['model_morph_key'], $user->getKey())
            ->where('model_type', $user->getMorphClass())
            ->delete();

        $this->permissionRegistrar->forgetCachedPermissions();
    }

    /**
     * Elimina le sessioni identificabili dell'utente (solo col driver `database`):
     * l'account non deve più poter accedere. Con altri driver non c'è una tabella
     * da ripulire e la garanzia è data da password random + email non verificata.
     */
    private function purgeSessions(int $userId): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        /** @var string $table */
        $table = config('session.table', 'sessions');

        DB::table($table)
            ->where('user_id', $userId)
            ->delete();
    }
}
