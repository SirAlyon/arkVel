<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
{{ config('app.name') }}
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer brandizzato ArkVel: tagline localizzata + anno corrente. La stringa
     vive in notifications_center.php (chiave email_footer_tagline). --}}
<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} {{ __('notifications_center.email_footer_tagline') }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
