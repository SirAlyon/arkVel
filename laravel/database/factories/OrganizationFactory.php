<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OrganizationStatus;
use App\Enums\OrganizationType;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $faker = fake('it_IT');

        $prefix = $faker->randomElement([
            'Canile Municipale di',
            'Canile Comunale di',
            'Rifugio',
            'Associazione',
            'Oasi Felina di',
        ]);
        $name = trim($prefix.' '.$faker->city());

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.$faker->unique()->numberBetween(1, 999999),
            'type' => $faker->randomElement(OrganizationType::cases()),
            'description' => [
                'it' => $faker->sentence(12),
                'en' => $faker->sentence(12),
            ],
            'legal_name' => $name.' ETS',
            'vat_number' => 'IT'.$faker->numerify('###########'),
            'tax_code' => $faker->numerify('###########'),
            'registration_number' => $faker->bothify('REG-#####'),
            'country' => 'IT',
            'region' => $faker->randomElement([
                'Lombardia', 'Lazio', 'Piemonte', 'Toscana', 'Veneto', 'Campania',
            ]),
            'province' => $faker->randomElement(['MI', 'RM', 'TO', 'FI', 'VE', 'NA']),
            'city' => $faker->city(),
            'postal_code' => $faker->postcode(),
            'address_line' => $faker->streetAddress(),
            'latitude' => $faker->randomFloat(7, 36.6, 47.1),
            'longitude' => $faker->randomFloat(7, 6.6, 18.5),
            'email' => $faker->companyEmail(),
            'phone' => $faker->phoneNumber(),
            'website' => $faker->url(),
            'status' => OrganizationStatus::Pending,
            'verified_at' => null,
        ];
    }

    public function verified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OrganizationStatus::Verified,
            'verified_at' => now(),
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OrganizationStatus::Pending,
            'verified_at' => null,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OrganizationStatus::Suspended,
        ]);
    }
}
