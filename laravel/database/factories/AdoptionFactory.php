<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\FollowUpStatus;
use App\Models\Adoption;
use App\Models\AdoptionApplication;
use App\Models\Animal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Adoption>
 */
class AdoptionFactory extends Factory
{
    protected $model = Adoption::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Una candidatura fresca (in AdoptionPending, con animale/ente coerenti):
        // le chiavi dell'adozione DEVONO combaciare con quelle della candidatura,
        // quindi le deriviamo dalla stessa riga invece di creare grafi scollegati.
        $application = AdoptionApplication::factory()->adoptionPending();

        return [
            'application_id' => $application,
            'animal_id' => fn (array $attributes): int => AdoptionApplication::query()
                ->findOrFail($attributes['application_id'])
                ->animal_id,
            'user_id' => fn (array $attributes): int => AdoptionApplication::query()
                ->findOrFail($attributes['application_id'])
                ->user_id,
            'organization_id' => fn (array $attributes): int => AdoptionApplication::query()
                ->findOrFail($attributes['application_id'])
                ->organization_id,
            'adopted_at' => now(),
            'fee' => fake()->optional()->randomFloat(2, 0, 250),
            'currency' => 'EUR',
            'contract_path' => null,
            'follow_up_status' => FollowUpStatus::Pending,
        ];
    }

    /**
     * Lega l'adozione a una candidatura specifica, mantenendo coerenti
     * animale/adottante/ente con quelli della candidatura.
     */
    public function forApplication(AdoptionApplication $application): static
    {
        return $this->state(fn (array $attributes): array => [
            'application_id' => $application->getKey(),
            'animal_id' => $application->animal_id,
            'user_id' => $application->user_id,
            'organization_id' => $application->organization_id,
        ]);
    }

    /**
     * Lega l'adozione a un animale specifico (e al suo ente).
     */
    public function forAnimal(Animal $animal): static
    {
        return $this->state(fn (array $attributes): array => [
            'animal_id' => $animal->getKey(),
            'organization_id' => $animal->organization_id,
        ]);
    }

    /**
     * Lega l'adozione a un adottante specifico.
     */
    public function forAdopter(User $adopter): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => $adopter->getKey(),
        ]);
    }
}
