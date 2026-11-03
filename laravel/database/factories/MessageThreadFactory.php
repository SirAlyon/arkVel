<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AdoptionApplication;
use App\Models\Animal;
use App\Models\MessageThread;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MessageThread>
 */
class MessageThreadFactory extends Factory
{
    protected $model = MessageThread::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'user_id' => User::factory(),
            // Contesti opzionali: nulli per default, impostati dagli helper.
            'animal_id' => null,
            'adoption_application_id' => null,
            'subject' => fake()->optional()->sentence(4),
            'last_message_at' => now(),
            'adopter_last_read_at' => null,
            'org_last_read_at' => null,
        ];
    }

    public function forOrganization(Organization $organization): static
    {
        return $this->state(fn (array $attributes): array => [
            'organization_id' => $organization->getKey(),
        ]);
    }

    public function forAdopter(User $adopter): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => $adopter->getKey(),
        ]);
    }

    /**
     * Aggancia il thread a un animale di contesto, mantenendo l'organization_id
     * coerente con l'ente dell'animale.
     */
    public function forAnimal(Animal $animal): static
    {
        return $this->state(fn (array $attributes): array => [
            'animal_id' => $animal->getKey(),
            'organization_id' => $animal->organization_id,
        ]);
    }

    /**
     * Aggancia il thread a una candidatura di adozione, mantenendo coerenti
     * l'ente e l'adottante con quelli della candidatura.
     */
    public function withApplication(AdoptionApplication $application): static
    {
        return $this->state(fn (array $attributes): array => [
            'adoption_application_id' => $application->getKey(),
            'organization_id' => $application->organization_id,
            'user_id' => $application->user_id,
            'animal_id' => $application->animal_id,
        ]);
    }
}
