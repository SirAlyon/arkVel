<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AnimalStatus;
use App\Enums\EnergyLevel;
use App\Enums\Sex;
use App\Enums\Size;
use App\Enums\Species;
use App\Models\Animal;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Animal>
 */
class AnimalFactory extends Factory
{
    protected $model = Animal::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $faker = fake('it_IT');

        $name = $faker->randomElement([
            'Rex', 'Luna', 'Fido', 'Micio', 'Bella', 'Argo', 'Nina', 'Zeus',
            'Maya', 'Leo', 'Stella', 'Rocky', 'Kira', 'Simba', 'Pallina', 'Birba',
        ]);

        return [
            'organization_id' => Organization::factory(),
            'name' => $name,
            // Slug lasciato vuoto: lo genera il model (deduplica per-ente).
            'slug' => null,
            'species' => $faker->randomElement(Species::cases()),
            'breed' => $faker->optional()->randomElement([
                'Meticcio', 'Labrador', 'Pastore Tedesco', 'Europeo', 'Beagle',
            ]),
            'sex' => $faker->randomElement(Sex::cases()),
            'size' => $faker->randomElement(Size::cases()),
            'birthdate' => $faker->optional()->dateTimeBetween('-12 years', '-3 months'),
            'estimated_age_months' => $faker->optional()->numberBetween(2, 180),
            'microchip_number' => $faker->numerify('###############'),
            'sterilized' => $faker->boolean(),
            'vaccinated' => $faker->boolean(),
            'dewormed' => $faker->boolean(),
            'health_notes' => $faker->optional()->sentence(10),
            'temperament' => $faker->optional()->randomElement([
                'Giocoso', 'Timido', 'Affettuoso', 'Vivace', 'Tranquillo',
            ]),
            'energy_level' => $faker->optional()->randomElement(EnergyLevel::cases()),
            'good_with_kids' => $faker->optional()->boolean(),
            'good_with_dogs' => $faker->optional()->boolean(),
            'good_with_cats' => $faker->optional()->boolean(),
            'description' => [
                'it' => $faker->sentence(12),
                'en' => $faker->sentence(12),
            ],
            'status' => AnimalStatus::Draft,
            'intake_date' => $faker->optional()->dateTimeBetween('-2 years', 'now'),
            'intake_source' => $faker->optional()->randomElement([
                'Rinuncia di proprieta', 'Recupero da strada', 'Sequestro', 'Cucciolata',
            ]),
            'application_paused' => false,
            'pause_reason' => null,
            'featured' => $faker->boolean(20),
        ];
    }

    public function available(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AnimalStatus::Available,
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AnimalStatus::Draft,
        ]);
    }

    public function adopted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AnimalStatus::Adopted,
        ]);
    }

    public function featured(): static
    {
        return $this->state(fn (array $attributes): array => [
            'featured' => true,
        ]);
    }

    public function forOrganization(Organization $organization): static
    {
        return $this->state(fn (array $attributes): array => [
            'organization_id' => $organization->getKey(),
        ]);
    }
}
