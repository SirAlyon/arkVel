@props(['url'])
<tr>
<td class="header">
{{-- Wordmark testuale ArkVel: nessun file logo. Lo slot porta il nome brand
     ("ArkVel" da config('app.name')); lo stile terracotta arriva dal tema
     inlineato (.header a). Niente immagine per restare email-client-safe. --}}
<a href="{{ $url }}" style="display: inline-block;">
{!! $slot !!}
</a>
</td>
</tr>
