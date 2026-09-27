<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\AppointmentExternalEvent;
use App\Models\CalendarConnection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AppointmentExternalEvent>
 */
class AppointmentExternalEventFactory extends Factory
{
    protected $model = AppointmentExternalEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'appointment_id' => Appointment::factory(),
            'calendar_connection_id' => CalendarConnection::factory(),
            'external_event_id' => 'ext-'.fake()->unique()->uuid(),
            'etag' => 'etag-'.fake()->numberBetween(1, 9999),
            'last_pushed_at' => now(),
        ];
    }

    public function forAppointment(Appointment $appointment): static
    {
        return $this->state(fn (array $attributes): array => [
            'appointment_id' => $appointment->getKey(),
        ]);
    }

    public function forConnection(CalendarConnection $connection): static
    {
        return $this->state(fn (array $attributes): array => [
            'calendar_connection_id' => $connection->getKey(),
        ]);
    }
}
