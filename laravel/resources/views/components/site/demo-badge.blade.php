{{--
 | Badge «Esempio» di un ente dimostrativo (`organizations.is_demo`). Il testo breve è
 | visibile; la spiegazione è nel `title` (al passaggio del mouse) e, per gli screen reader,
 | in un testo nascosto: il significato non dipende dal tooltip.
 |
 | <x-site.demo-badge />
--}}
<x-ui.badge variant="info" icon="flask-conical" :title="__('demo.badge_hint')" data-demo-badge {{ $attributes }}>
    {{ __('demo.badge') }}<span class="sr-only">: {{ __('demo.badge_hint') }}</span>
</x-ui.badge>
