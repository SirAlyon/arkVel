{{--
 | Template markdown delle mail di autenticazione (App\Notifications\Auth\AuthMailMessage):
 | come notifications::email di Laravel, ma con la riga di riserva del bottone tradotta
 | (auth_public.mail.fallback) invece della chiave JSON inglese del framework. Header e
 | footer brandizzati da x-mail::message (resources/views/vendor/mail). Markdown: niente
 | indentazione.
--}}
<x-mail::message>
# {{ $greeting }}

@foreach ($introLines as $line)
{{ $line }}

@endforeach
@isset($actionText)
<x-mail::button :url="$actionUrl" color="primary">
{{ $actionText }}
</x-mail::button>
@endisset

@foreach ($outroLines as $line)
{{ $line }}

@endforeach
{{ $salutation }}

@isset($actionText)
<x-slot:subcopy>
{{ __('auth_public.mail.fallback', ['action' => $actionText]) }} <span class="break-all">[{{ $displayableActionUrl }}]({{ $actionUrl }})</span>
</x-slot:subcopy>
@endisset
</x-mail::message>
