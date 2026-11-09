{{--
 | Logo del sito (direzione B): badge giallo stondato con la zampa (Lucide paw-print) e
 | wordmark "arkvel" minuscolo 800. È il link alla home (wire:navigate.hover).
 |
 | Nome accessibile con aria-label, «arkvel, pagina iniziale»: inizia col wordmark visibile
 | (WCAG 2.5.3) e non dipende dagli spazi tra gli span (un testo sr-only accanto al wordmark
 | veniva letto «arkvel , pagina iniziale»).
 |
 | <x-site.logo />
--}}
<a
    href="{{ route('home') }}"
    wire:navigate.hover
    aria-label="{{ __('public.nav.logo', ['name' => 'arkvel']) }}"
    data-site-logo
    {{ $attributes->class('inline-flex min-h-11 shrink-0 items-center gap-3 rounded-full text-fg no-underline') }}
>
    <span class="inline-flex size-11 items-center justify-center rounded-icon bg-highlight text-on-highlight">
        <x-ui.icon name="paw-print" size="lg" />
    </span>
    <span class="text-h4">arkvel</span>
</a>
