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

    /**
     * Connessione Google con credenziali cifrate pronte all'uso: access token FRESCO
     * (scadenza futura), refresh token e calendario 'primary'. I test possono comunque
     * sovrascrivere `credentials`/`sync_token` per esercitare refresh e pull.
     */
    public function google(): static
    {
        return $this->provider(CalendarProvider::Google)->state(fn (array $attributes): array => [
            'name' => 'Google Calendar',
            'external_calendar_id' => 'primary',
            'credentials' => [
                'access_token' => 'fresh-access-token',
                'refresh_token' => 'refresh-token',
                'expires_at' => now()->addHour()->toIso8601String(),
                'scope' => 'https://www.googleapis.com/auth/calendar.events',
                'token_type' => 'Bearer',
            ],
        ]);
    }

    /**
     * Connessione CalDAV con credenziali cifrate pronte all'uso: url della collezione,
     * username e app-password. L'`external_calendar_id` coincide con l'url della
     * collezione (dove vivono i singoli eventi `{uid}.ics`). I test possono sovrascrivere
     * `credentials`/`sync_token`/`external_calendar_id` per esercitare push e pull.
     */
    public function calDav(): static
    {
        $collectionUrl = 'https://caldav.example.test/dav/calendars/user/agent@example.test/work/';

        return $this->provider(CalendarProvider::CalDav)->state(fn (array $attributes): array => [
            'name' => 'CalDAV',
            'external_calendar_id' => $collectionUrl,
            'credentials' => [
                'url' => $collectionUrl,
                'username' => 'agent@example.test',
                'app_password' => 'app-secret-password',
            ],
        ]);
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
