<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Animal;
use App\Models\FosterApplication;
use App\Models\Organization;
use App\Models\User;
use App\States\Foster\Active;
use App\States\Foster\Approved;
use App\States\Foster\Completed;
use App\States\Foster\Rejected;
use App\States\Foster\Submitted;
use App\States\Foster\UnderReview;
use App\States\Foster\Waitlisted;
use App\States\Foster\Withdrawn;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FosterApplication>
 */
class FosterApplicationFactory extends Factory
{
    protected $model = FosterApplication::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Un animale fresco (con il suo ente): l'organization_id della richiesta
        // DEVE combaciare con quello dell'animale, quindi lo deriviamo dallo
        // stesso animale invece di creare due enti scollegati.
        $animal = Animal::factory();

        return [
            'animal_id' => $animal,
            'user_id' => User::factory(),
            'organization_id' => fn (array $attributes): int => Animal::query()
                ->findOrFail($attributes['animal_id'])
                ->organization_id,
            'assigned_to' => null,
            // Stato di default (Submitted) applicato dal model; esplicito per
            // chiarezza e coerenza con submitted_at.
            'status' => Submitted::class,
            'staff_notes' => null,
            'submitted_at' => now(),
            'decided_at' => null,
            'decision_reason' => null,
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => Submitted::class,
        ]);
    }

    public function underReview(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => UnderReview::class,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => Approved::class,
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => Rejected::class,
            'decided_at' => now(),
            'decision_reason' => 'Requisiti non soddisfatti.',
        ]);
    }

    public function waitlisted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => Waitlisted::class,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => Active::class,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => Completed::class,
            'decided_at' => now(),
        ]);
    }

    public function withdrawn(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => Withdrawn::class,
        ]);
    }

    /**
     * Lega la richiesta a un animale specifico, mantenendo l'organization_id
     * coerente con l'ente dell'animale.
     */
    public function forAnimal(Animal $animal): static
    {
        return $this->state(fn (array $attributes): array => [
            'animal_id' => $animal->getKey(),
            'organization_id' => $animal->organization_id,
        ]);
    }

    public function forAdopter(User $adopter): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => $adopter->getKey(),
        ]);
    }

    /**
     * Forza l'ente della richiesta. Da usare con forAnimal(): l'ente deve
     * restare coerente con quello dell'animale.
     */
    public function forOrganization(Organization $organization): static
    {
        return $this->state(fn (array $attributes): array => [
            'organization_id' => $organization->getKey(),
        ]);
    }
}
