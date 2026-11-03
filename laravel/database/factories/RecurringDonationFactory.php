<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RecurringDonationStatus;
use App\Models\Organization;
use App\Models\RecurringDonation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecurringDonation>
 */
class RecurringDonationFactory extends Factory
{
    protected $model = RecurringDonation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'organization_id' => Organization::factory(),
            // Importo in centesimi: fra 5€ e 100€/mese.
            'amount' => $this->faker->numberBetween(500, 10_000),
            'currency' => 'eur',
            'interval' => 'monthly',
            'stripe_subscription_id' => null,
            'status' => RecurringDonationStatus::Pending,
            'started_at' => null,
            'ends_at' => null,
            'canceled_at' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => RecurringDonationStatus::Pending,
            'stripe_subscription_id' => null,
            'started_at' => null,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => RecurringDonationStatus::Active,
            'stripe_subscription_id' => 'sub_'.$this->faker->uuid(),
            'started_at' => now(),
        ]);
    }

    public function pastDue(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => RecurringDonationStatus::PastDue,
            'stripe_subscription_id' => 'sub_'.$this->faker->uuid(),
            'started_at' => now()->subMonth(),
        ]);
    }

    public function canceled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => RecurringDonationStatus::Canceled,
            'stripe_subscription_id' => 'sub_'.$this->faker->uuid(),
            'started_at' => now()->subMonths(3),
            'canceled_at' => now(),
        ]);
    }

    public function forDonor(User $donor): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => $donor->getKey(),
        ]);
    }

    public function forOrganization(Organization $organization): static
    {
        return $this->state(fn (array $attributes): array => [
            'organization_id' => $organization->getKey(),
        ]);
    }

    public function withSubscriptionId(string $subscriptionId): static
    {
        return $this->state(fn (array $attributes): array => [
            'stripe_subscription_id' => $subscriptionId,
        ]);
    }
}
