<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\FosterPlacementStatus;
use App\Enums\FosterPlacementType;
use App\Models\Animal;
use App\Models\FosterApplication;
use App\Models\FosterPlacement;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FosterPlacement>
 */
class FosterPlacementFactory extends Factory
{
    protected $model = FosterPlacement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Un animale fresco (con il suo ente): l'organization_id dell'affido DEVE
        // combaciare con quello dell'animale, quindi lo deriviamo dallo stesso
        // animale invece di creare due enti scollegati.
        $animal = Animal::factory();

        return [
            'animal_id' => $animal,
            'user_id' => User::factory(),
            'organization_id' => fn (array $attributes): int => Animal::query()
                ->findOrFail($attributes['animal_id'])
                ->organization_id,
            'foster_application_id' => null,
            'type' => fake()->randomElement(FosterPlacementType::cases()),
            'start_date' => now(),
            'end_date' => null,
            'status' => FosterPlacementStatus::Active,
            'notes' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => FosterPlacementStatus::Active,
            'end_date' => null,
        ]);
    }

    public function ended(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => FosterPlacementStatus::Ended,
            'end_date' => now()->subDays(7),
        ]);
    }

    public function ofType(FosterPlacementType $type): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => $type,
        ]);
    }

    /**
     * Lega l'affido a un animale specifico, mantenendo l'organization_id
     * coerente con l'ente dell'animale.
     */
    public function forAnimal(Animal $animal): static
    {
        return $this->state(fn (array $attributes): array => [
            'animal_id' => $animal->getKey(),
            'organization_id' => $animal->organization_id,
        ]);
    }

    public function forCarer(User $carer): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => $carer->getKey(),
        ]);
    }

    /**
     * Forza l'ente dell'affido. Da usare con forAnimal(): l'ente deve restare
     * coerente con quello dell'animale.
     */
    public function forOrganization(Organization $organization): static
    {
        return $this->state(fn (array $attributes): array => [
            'organization_id' => $organization->getKey(),
        ]);
    }

    /**
     * Lega l'affido a una pratica di origine e ne allinea animale, affidatario
     * ed ente, così che l'affido resti coerente con la richiesta approvata.
     */
    public function forApplication(FosterApplication $application): static
    {
        return $this->state(fn (array $attributes): array => [
            'foster_application_id' => $application->getKey(),
            'animal_id' => $application->animal_id,
            'user_id' => $application->user_id,
            'organization_id' => $application->organization_id,
        ]);
    }
}
