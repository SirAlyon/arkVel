<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CalendarConnectionStatus;
use App\Enums\CalendarProvider;
use App\Enums\CalendarSyncDirection;
use App\Models\CalendarConnection;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CalendarConnection>
 */
class CalendarConnectionFactory extends Factory
{
    protected $model = CalendarConnection::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            // Default: il provider demo (log), l'unico realmente utilizzabile in
            // Cal-4 senza credenziali reali.
            'provider' => CalendarProvider::Log,
            'name' => fake()->words(2, true),
            'external_calendar_id' => 'cal-'.fake()->unique()->uuid(),
            'credentials' => null,
            'sync_token' => null,
            'direction' => CalendarSyncDirection::Both,
            'status' => CalendarConnectionStatus::Active,
            'last_synced_at' => null,
            'last_error' => null,
        ];
    }

    public function forOrganization(Organization $organization): static
    {
        return $this->state(fn (array $attributes): array => [
            'organization_id' => $organization->getKey(),
        ]);
    }

    public function provider(CalendarProvider $provider): static
    {
        return $this->state(fn (array $attributes): array => [
            'provider' => $provider,
        ]);
    }

    public function log(): static
    {
        return $this->provider(CalendarProvider::Log);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => CalendarConnectionStatus::Active,
        ]);
    }

    public function errored(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => CalendarConnectionStatus::Error,
        ]);
    }

    public function disconnected(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => CalendarConnectionStatus::Disconnected,
        ]);
    }
}
