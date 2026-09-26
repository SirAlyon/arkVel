<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\MedicalRecord;
use App\Models\Organization;
use App\Models\User;

/**
 * Policy per MedicalRecord (cartella sanitaria).
 *
 * La cartella è animal-scoped: non porta un proprio organization_id, quindi
 * l'ownership per tenant si verifica risalendo all'animale
 * ($record->animal->organization). Doppia difesa: oltre allo scoping delle
 * query (il RelationManager vive sotto un Animal già tenant-scoped), le azioni
 * puntuali verificano che l'utente sia membro VERIFICATO dell'ente
 * proprietario dell'animale, escludendo fughe cross-tenant.
 *
 * I permessi sono team-scoped (spatie/permission in modalità teams): il team id
 * (ente corrente) è impostato dal contesto tenancy o esplicitamente nei test.
 * Il Super Admin bypassa tutto via Gate::before (AppServiceProvider).
 */
class MedicalRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::MedicalRecordView->value);
    }

    public function view(User $user, MedicalRecord $medicalRecord): bool
    {
        return $user->can(PermissionName::MedicalRecordView->value)
            && $this->ownsRecordOrganization($user, $medicalRecord);
    }

    public function create(User $user): bool
    {
        // Il legame all'animale (e quindi al tenant) è garantito dal contesto
        // del RelationManager tenant-scoped; qui basta il permesso.
        return $user->can(PermissionName::MedicalRecordCreate->value);
    }

    public function update(User $user, MedicalRecord $medicalRecord): bool
    {
        return $user->can(PermissionName::MedicalRecordUpdate->value)
            && $this->ownsRecordOrganization($user, $medicalRecord);
    }

    public function delete(User $user, MedicalRecord $medicalRecord): bool
    {
        return $user->can(PermissionName::MedicalRecordDelete->value)
            && $this->ownsRecordOrganization($user, $medicalRecord);
    }

    /**
     * Verifica che l'utente sia membro verificato dell'ente proprietario della
     * cartella, risalendo all'animale. Se animale o ente non sono risolvibili
     * (es. cross-tenant: l'animale di un altro ente non è visibile nel tenant
     * corrente), nega in modo pulito invece di sollevare un errore.
     */
    private function ownsRecordOrganization(User $user, MedicalRecord $medicalRecord): bool
    {
        $organization = $medicalRecord->animal?->organization;

        if (! $organization instanceof Organization) {
            return false;
        }

        return $user->isVerifiedMemberOf($organization);
    }
}
