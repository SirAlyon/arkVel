{{--
 | Avviso «Ente dimostrativo»: sostituisce le CTA della scheda animale (candidatura, affido,
 | visita, contatto) e il widget di donazione della pagina ente quando l'ente è un esempio
 | (`organizations.is_demo`). Il rifiuto vero è nel dominio
 | (App\Support\Organizations\DemoOrganizationGuard): questo è solo il messaggio.
 |
 | Coppia di colori info-subtle / on-info-subtle (la stessa del badge info).
 |
 | <x-site.demo-notice heading-level="2" />
--}}
@props([
    'headingLevel' => 2,
])

@php
    $level = min(6, max(2, (int) $headingLevel));
@endphp

<section {{ $attributes->class('flex flex-col gap-3 rounded-panel bg-info-subtle p-panel text-on-info-subtle') }} data-demo-notice>
    <h{{ $level }} class="flex items-center gap-2 text-h4">
        <x-ui.icon name="flask-conical" />
        {{ __('demo.notice_title') }}
    </h{{ $level }}>
    <p>{{ __('demo.notice_body') }}</p>
</section>
