{{--
 | Esito in-place di una CTA della scheda animale (candidatura, affido, prenotazione,
 | contatto): una regione live sempre presente (role="status", così l'annuncio parte anche
 | quando il messaggio compare dopo un invio) con un riquadro success o danger. Il tipo
 | non passa dal solo colore: icona e testo lo dicono.
 |
 | - `message`: il testo dell'esito (null = nessun esito, la regione resta vuota);
 | - `error`: riquadro danger invece di success;
 | - `focus`: quando il messaggio compare prende il focus (tabindex="-1"). Serve alle CTA
 |   a un solo bottone, che dopo l'invio sparisce (candidatura, affido): senza, il focus
 |   finirebbe sul body (WCAG 2.4.3).
 |
 | La regione vuota non ha né spazi né margini (il riquadro porta il suo mt-3): va messa
 | dopo il contenuto della CTA, fuori da un contenitore flex con `gap`.
 |
 | <x-site.animal.feedback :message="$applyMessage" :error="$applyMessageIsError" focus wire:key="apply-message" />
--}}
@props([
    'message' => null,
    'error' => false,
    'focus' => false,
])

<div role="status" {{ $attributes }}>@if (filled($message))<p
        @if ($focus) tabindex="-1" x-init="$nextTick(() => $el.focus({ preventScroll: false }))" @endif
        data-animal-feedback="{{ $error ? 'error' : 'success' }}"
        @class([
            'mt-3 flex items-start gap-3 rounded-card p-4 text-small font-semibold',
            'bg-danger-subtle text-on-danger-subtle' => $error,
            'bg-success-subtle text-on-success-subtle' => ! $error,
        ])
    >
        <x-ui.icon :name="$error ? 'circle-x' : 'circle-check'" class="mt-0.5 shrink-0" />
        <span class="min-w-0">{{ $message }}</span>
    </p>@endif</div>
