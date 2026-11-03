<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use App\Models\Animal;
use App\Models\Appointment;
use App\Models\Organization;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    protected $model = Appointment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Un animale fresco (con il suo ente): l'organization_id
        // dell'appuntamento DEVE combaciare con quello dell'animale, quindi lo
        // deriviamo dallo stesso animale invece di creare due enti scollegati.
        $animal = Animal::factory();

        return [
            'animal_id' => $animal,
            'user_id' => User::factory(),
            'organization_id' => fn (array $attributes): int => Animal::query()
                ->findOrFail($attributes['animal_id'])
                ->organization_id,
            // Slot futuro, agganciato all'ora piena per rendere prevedibile il
            // calcolo dell'overlap nei test.
            'scheduled_at' => now()->addDays(fake()->numberBetween(1, 30))->startOfHour(),
            'type' => fake()->randomElement(AppointmentType::cases()),
            'status' => AppointmentStatus::Requested,
            'notes' => null,
        ];
    }

    public function requested(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AppointmentStatus::Requested,
        ]);
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AppointmentStatus::Confirmed,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AppointmentStatus::Completed,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AppointmentStatus::Cancelled,
        ]);
    }

    public function noShow(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AppointmentStatus::NoShow,
        ]);
    }

    public function ofType(AppointmentType $type): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => $type,
        ]);
    }

    public function at(CarbonInterface $scheduledAt): static
    {
        return $this->state(fn (array $attributes): array => [
            'scheduled_at' => $scheduledAt,
        ]);
    }

    /**
     * Lega l'appuntamento a un animale specifico, mantenendo l'organization_id
     * coerente con l'ente dell'animale.
     */
    public function forAnimal(Animal $animal): static
    {
        return $this->state(fn (array $attributes): array => [
            'animal_id' => $animal->getKey(),
            'organization_id' => $animal->organization_id,
        ]);
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => $user->getKey(),
        ]);
    }

    /**
     * Forza l'ente dell'appuntamento. Da usare con forAnimal(): l'ente deve
     * restare coerente con quello dell'animale.
     */
    public function forOrganization(Organization $organization): static
    {
        return $this->state(fn (array $attributes): array => [
            'organization_id' => $organization->getKey(),
        ]);
    }
}
