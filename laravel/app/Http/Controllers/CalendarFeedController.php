<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Organization;
use App\Support\Calendar\AppointmentCalendarExport;
use Illuminate\Http\Response;

/**
 * Espone gli appuntamenti come iCalendar (RFC-5545): il feed d'ente in
 * sottoscrizione e il download del singolo evento.
 *
 * Controller SOTTILE: risolve, autorizza e delega a
 * {@see AppointmentCalendarExport}. Nessuna logica di mapping/minimizzazione qui —
 * quella vive nel servizio, punto unico di verità.
 *
 * Due superfici, due modelli di sicurezza:
 *  - feed(): PUBBLICO ma protetto da un segreto nell'URL (come le "secret ICS URL"
 *    di Google/Fastmail). Il confronto del token è a TEMPO COSTANTE (hash_equals) e
 *    ogni fallimento è un 404 indistinguibile: non riveliamo mai se l'ente esiste né
 *    se il token è "quasi" giusto. Rate-limited a livello di rotta.
 *  - appointment(): dietro auth + Policy (view) — proprietario o staff dell'ente.
 */
final class CalendarFeedController extends Controller
{
    public function __construct(
        private readonly AppointmentCalendarExport $export,
    ) {}

    /**
     * Feed iCal d'ente in sottoscrizione: GET /calendar/{organization}/{token}.
     *
     * L'ente è risolto per SLUG o per ID (l'URL canonico usa lo slug, ma accettiamo
     * anche l'id). Il token è confrontato a tempo costante con
     * organization->calendar_feed_token: se il token dell'ente è null (mai generato)
     * o non combacia → 404, senza rivelare l'esistenza dell'ente. Solo gli
     * appuntamenti dell'ente finiscono nel feed (scoping garantito dal servizio).
     */
    public function feed(string $organization, string $token): Response
    {
        $model = $this->resolveOrganization($organization);

        // 404 uniforme: ente inesistente, token mai generato o token errato sono
        // indistinguibili dall'esterno. hash_equals evita timing attack sul confronto.
        abort_if($model === null, 404);
        abort_if($model->calendar_feed_token === null, 404);
        abort_unless(hash_equals($model->calendar_feed_token, $token), 404);

        $ics = $this->export->feed($model);

        return response($ics, 200)
            ->header('Content-Type', 'text/calendar; charset=utf-8')
            ->header('Content-Disposition', 'inline; filename="arkvel-'.$model->slug.'.ics"');
    }

    /**
     * Download .ics del singolo appuntamento: GET /appointments/{appointment}/calendar.ics.
     *
     * Dietro auth + Policy: la rotta è gated da ->can('view', $appointment), quindi
     * solo il proprietario o lo staff verificato dell'ente arriva qui. Restituito
     * come ALLEGATO (attachment) così il browser scarica il file e il client di
     * calendario lo importa.
     */
    public function appointment(Appointment $appointment): Response
    {
        $ics = $this->export->single($appointment);

        return response($ics, 200)
            ->header('Content-Type', 'text/calendar; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="appointment-'.$appointment->getKey().'.ics"');
    }

    /**
     * Risolve l'ente per slug o, in fallback, per id numerico. Ritorna null se non
     * trovato (il chiamante traduce in 404 uniforme).
     */
    private function resolveOrganization(string $identifier): ?Organization
    {
        $organization = Organization::query()->where('slug', $identifier)->first();

        if ($organization === null && ctype_digit($identifier)) {
            $organization = Organization::query()->find((int) $identifier);
        }

        return $organization;
    }
}
