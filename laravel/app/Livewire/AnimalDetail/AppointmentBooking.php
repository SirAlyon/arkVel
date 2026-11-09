<?php

declare(strict_types=1);

namespace App\Livewire\AnimalDetail;

use App\Actions\Appointments\RequestAppointment;
use App\Enums\AppointmentType;
use App\Exceptions\AppointmentSlotUnavailable;
use App\Exceptions\InvalidAppointmentSlot;
use App\Livewire\AnimalDetail\Concerns\ActsOnAnimal;
use App\Models\Animal;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View as ViewFacade;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * CTA "prenota una visita" della scheda animale. A differenza di candidatura/affido
 * richiede input (tipo + data/ora + note), quindi ha un piccolo form validato, e
 * delega all'Action {@see RequestAppointment}. Non c'è guardia di profilo (un
 * appuntamento richiede solo l'autenticazione): ospite → login adottante;
 * autenticato → validazione + delega, con le eccezioni di dominio (slot passato /
 * slot occupato) tradotte in feedback in-place. Nessuna business logic qui.
 */
class AppointmentBooking extends Component
{
    use ActsOnAnimal;

    /**
     * Tipo di incontro scelto (value dell'enum {@see AppointmentType}), validato con
     * la regola `in` sui value dei case (vedi {@see self::rules()}).
     */
    public string $appointmentType = '';

    /**
     * Data/ora scelta, come stringa `datetime-local` (Y-m-d\TH:i): la vista la compone da
     * giorno e orario liberi. Il parsing verso Carbon e la guardia "slot nel futuro"
     * vivono nell'Action; qui si validano solo presenza e formato.
     */
    public string $appointmentScheduledAt = '';

    /**
     * Note facoltative del richiedente, passate all'Action.
     */
    public string $appointmentNotes = '';

    /**
     * Feedback in-place (successo o errore "amichevole"), mostrato accanto al form.
     * Le redirezioni (ospite) usano il flash di sessione.
     */
    #[Locked]
    public ?string $bookingMessage = null;

    #[Locked]
    public bool $bookingMessageIsError = false;

    /**
     * Tentativi di invio: entra nel wire:key del riepilogo errori e del messaggio, così a
     * ogni invio vengono ricreati e prendono il focus (WCAG 2.4.3 / 3.3.1). Solo stato di
     * presentazione, come il contatore del widget donazione.
     */
    #[Locked]
    public int $bookingAttempts = 0;

    public function mount(Animal $animal): void
    {
        $this->animalId = $animal->getKey();
    }

    /**
     * Prenota un appuntamento (visita/colloquio/controllo domiciliare) per questo
     * animale a nome dell'utente corrente.
     */
    public function book(RequestAppointment $requestAppointment): mixed
    {
        $user = Auth::user();
        $animal = $this->animal();

        if (! $user instanceof User) {
            return $this->redirectGuestToLogin($animal);
        }

        // Chiunque autenticato può prenotare (RequestAppointment impone le guardie
        // di dominio): manteniamo il gancio alla policy.
        $this->authorize('create', Appointment::class);

        $this->bookingAttempts++;

        $validated = $this->validate();

        try {
            $requestAppointment->handle(
                requester: $user,
                animal: $animal,
                type: AppointmentType::from($validated['appointmentType']),
                scheduledAt: Carbon::parse($validated['appointmentScheduledAt']),
                notes: $validated['appointmentNotes'] !== '' ? $validated['appointmentNotes'] : null,
            );

            $this->reset('appointmentType', 'appointmentScheduledAt', 'appointmentNotes');
            $this->bookingMessageIsError = false;
            $this->bookingMessage = __('appointments.booking.flash_success');
        } catch (InvalidAppointmentSlot) {
            $this->bookingMessageIsError = true;
            $this->bookingMessage = __('appointments.booking.slot_in_past');
        } catch (AppointmentSlotUnavailable) {
            // Overlap animale/richiedente: nessuna riga creata.
            $this->bookingMessageIsError = true;
            $this->bookingMessage = __('appointments.booking.slot_unavailable');
        }

        return null;
    }

    public function render(): View
    {
        return ViewFacade::make('livewire.animal-detail.appointment-booking', [
            'animal' => $this->animal(),
            'appointmentTypes' => AppointmentType::cases(),
        ]);
    }

    /**
     * Il tipo è ristretto ai value dell'enum {@see AppointmentType}; la data/ora deve
     * solo essere presente e parsabile (la guardia "nel futuro" e l'overlap vivono
     * nell'Action).
     *
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'appointmentType' => ['required', Rule::in(array_column(AppointmentType::cases(), 'value'))],
            'appointmentScheduledAt' => ['required', 'date'],
            'appointmentNotes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'appointmentType.required' => __('appointments.booking.validation.type_required'),
            'appointmentType.in' => __('appointments.booking.validation.type_invalid'),
            'appointmentScheduledAt.required' => __('appointments.booking.validation.scheduled_at_required'),
            'appointmentScheduledAt.date' => __('appointments.booking.validation.scheduled_at_invalid'),
        ];
    }
}
