{{--
 | Indicatore testuale "(obbligatorio)" / "(facoltativo)" accanto a label e legend.
 | Testo, non asterisco: si legge e non dipende dal colore. Uso interno di x-ui.field,
 | x-ui.radio-group, x-ui.checkbox-group, x-ui.checkbox e x-ui.switch.
 |
 | <x-ui.field.indicator :required="$required" :optional="$optional" />
--}}
@props([
    'required' => false,
    'optional' => false,
])

@if ($required)
    <span data-field-indicator="required" class="text-small font-normal text-fg-muted">{{ __('ui_forms.field.required') }}</span>
@elseif ($optional)
    <span data-field-indicator="optional" class="text-small font-normal text-fg-muted">{{ __('ui_forms.field.optional') }}</span>
@endif
