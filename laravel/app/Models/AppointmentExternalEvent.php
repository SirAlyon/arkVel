<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AppointmentExternalEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Mappatura fra un appuntamento ArkVel e l'evento che lo rappresenta su una specifica
 * connessione esterna. Rende il push IDEMPOTENTE: conoscere id/etag dell'evento remoto
 * consente di aggiornarlo (non duplicarlo) e di cancellarlo quando l'appuntamento
 * sparisce.
 *
 * Model magro: relazioni e cast. La logica di upsert/delete vive nel job di push.
 *
 * @property int $id
 * @property int $appointment_id
 * @property int $calendar_connection_id
 * @property string $external_event_id
 * @property string|null $etag
 * @property Carbon|null $last_pushed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Appointment $appointment
 * @property-read CalendarConnection $calendarConnection
 */
class AppointmentExternalEvent extends Model
{
    /** @use HasFactory<AppointmentExternalEventFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'appointment_id',
        'calendar_connection_id',
        'external_event_id',
        'etag',
        'last_pushed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_pushed_at' => 'datetime',
        ];
    }

    /**
     * Appuntamento di origine.
     *
     * @return BelongsTo<Appointment, $this>
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /**
     * Connessione esterna su cui vive l'evento.
     *
     * @return BelongsTo<CalendarConnection, $this>
     */
    public function calendarConnection(): BelongsTo
    {
        return $this->belongsTo(CalendarConnection::class);
    }
}
