{{--
 | Messaggio di errore di validazione: icona + testo in `danger`, con il prefisso
 | "Errore:" solo per gli screen reader (lo stato non passa dal solo colore).
 | Esiste nel DOM già al render ed è collegato al controllo con aria-describedby:
 | per gli errori dal server non serve una live region. Uso interno dei componenti
 | del gruppo "forms"; `danger` è verificato su canvas e surface.
 |
 | <x-ui.field.error id="field-email-error" :message="$message" />
--}}
@props([
    'id',
    'message',
])

<p id="{{ $id }}" data-field-error {{ $attributes->class('flex items-start gap-2 text-small font-semibold text-danger') }}>
    <x-ui.icon name="circle-alert" size="sm" class="mt-0.5" />
    <span><span class="sr-only">{{ __('ui_forms.field.error') }}</span> {{ $message }}</span>
</p>
