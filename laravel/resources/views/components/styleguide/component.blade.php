{{--
 | Sezione della styleguide dedicata a un componente x-ui: titolo h2 (con ancora),
 | descrizione, note di accessibilità e, nello slot, gli esempi (x-styleguide.example).
 |
 | <x-styleguide.component id="button" :title="__('…')" :description="__('…')" :a11y="[__('…'), …]">
 |     <x-styleguide.example …>…</x-styleguide.example>
 | </x-styleguide.component>
--}}
@props([
    'id',
    'title',
    'description' => null,
    'a11y' => [],
])

<section id="{{ $id }}" aria-labelledby="{{ $id }}-title" data-styleguide-component="{{ $id }}" {{ $attributes->class('flex scroll-mt-6 flex-col gap-6') }}>
    <div class="flex flex-col gap-3">
        <h2 id="{{ $id }}-title" class="text-h2">{{ $title }} <code class="align-middle text-small font-semibold text-fg-muted">&lt;x-ui.{{ $id }}&gt;</code></h2>
        @if ($description)
            <p class="max-w-3xl text-fg-muted">{{ $description }}</p>
        @endif
        @if ($a11y !== [])
            <div class="flex max-w-3xl flex-col gap-2 rounded-card bg-surface p-5">
                <h3 class="flex items-center gap-2 text-h5"><x-ui.icon name="accessibility" />{{ __('styleguide.a11y') }}</h3>
                <ul class="flex list-disc flex-col gap-1 ps-5 text-small">
                    @foreach ($a11y as $note)
                        <li>{{ $note }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>

    {{ $slot }}
</section>
