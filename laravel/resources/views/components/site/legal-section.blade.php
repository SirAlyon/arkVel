{{--
 | Una sezione di una pagina legale: <section> con h2 (id ancorabile dall'indice), spaziatura
 | di lettura e reveal allo scroll (degrada senza JS o con reduced-motion: il contenuto è
 | sempre visibile). I paragrafi, le liste e i sotto-titoli h3 arrivano dallo slot.
 |
 | - `id` (obbligatorio): id della sezione, usato dall'indice e come àncora;
 | - `title` (obbligatorio): testo dell'h2.
--}}
@props([
    'id',
    'title',
])

<section
    id="{{ $id }}"
    aria-labelledby="{{ $id }}-title"
    class="flex scroll-mt-28 flex-col gap-4"
    x-reveal
    {{ $attributes }}
>
    <h2 id="{{ $id }}-title" class="text-h3 text-fg">{{ $title }}</h2>
    {{ $slot }}
</section>
