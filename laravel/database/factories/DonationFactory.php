<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DonationStatus;
use App\Enums\DonationType;
use App\Models\Donation;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Donation>
 */
class DonationFactory extends Factory
{
    protected $model = Donation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->name();

        return [
            'organization_id' => Organization::factory(),
            'donor_user_id' => User::factory(),
            'donor_name' => $name,
            'donor_email' => $this->faker->safeEmail(),
            // Importo in centesimi: fra 5€ e 500€.
            'amount' => $this->faker->numberBetween(500, 50_000),
            'currency' => 'eur',
            'type' => DonationType::OneOff,
            'status' => DonationStatus::Pending,
            'provider' => 'stripe',
            'provider_ref' => null,
            'message' => null,
            'paid_at' => null,
        ];
    }

    public function oneOff(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => DonationType::OneOff,
        ]);
    }

    public function recurring(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => DonationType::Recurring,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => DonationStatus::Pending,
            'paid_at' => null,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => DonationStatus::Completed,
            'provider_ref' => 'cs_test_'.$this->faker->uuid(),
            'paid_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => DonationStatus::Failed,
            'paid_at' => null,
        ]);
    }

    public function refunded(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => DonationStatus::Refunded,
            'provider_ref' => 'cs_test_'.$this->faker->uuid(),
            'paid_at' => now()->subDay(),
        ]);
    }

    public function forOrganization(Organization $organization): static
    {
        return $this->state(fn (array $attributes): array => [
            'organization_id' => $organization->getKey(),
        ]);
    }

    public function forDonor(User $donor): static
    {
        return $this->state(fn (array $attributes): array => [
            'donor_user_id' => $donor->getKey(),
            'donor_name' => $donor->name,
            'donor_email' => $donor->email,
        ]);
    }

    /**
     * Donazione ANONIMA / da ospite: nessun utente collegato. donor_name/email
     * restano opzionali (una ricevuta può comunque richiederli in P4-6).
     */
    public function anonymous(): static
    {
        return $this->state(fn (array $attributes): array => [
            'donor_user_id' => null,
            'donor_name' => null,
            'donor_email' => null,
        ]);
    }
}
