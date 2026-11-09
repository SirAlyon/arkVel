{{--
 | Styleguide · gruppo "motion" (UI-7): reveal allo scroll (x-reveal), cuore dei preferiti,
 | conferma dei toast e transizione della foto card → scheda. Testi in
 | lang/{it,en}/styleguide_motion.php; JS in resources/js/ui/motion.js, CSS in
 | resources/css/ui/motion.css. Gli attributi data-demo servono ai test browser
 | (tests/Browser/Components/MotionComponentsTest.php).
--}}
@php
    use Illuminate\Support\Js;

    $t = static fn (string $key, array $replace = []): string => __('styleguide_motion.'.$key, $replace);

    $revealItems = ['one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight'];
    $revealTones = ['leaf', 'surface', 'peach', 'mint'];
@endphp
<x-styleguide.layout :title="__('styleguide.page_title', ['group' => __('styleguide.groups.motion.title'), 'styleguide' => __('styleguide.title')])" current="motion">
    <div class="flex flex-col gap-3 pt-4 md:pt-8">
        <h1 class="text-h1">{{ __('styleguide.groups.motion.title') }}</h1>
        <p class="max-w-2xl text-lead text-fg-muted">{{ __('styleguide.groups.motion.intro') }}</p>
    </div>

    {{-- ── Regole ────────────────────────────────────────────────────────── --}}
    <section aria-labelledby="motion-rules-title" class="flex flex-col gap-4" data-demo="motion-rules">
        <h2 id="motion-rules-title" class="text-h2">{{ $t('rules.title') }}</h2>
        <ul class="flex max-w-3xl list-disc flex-col gap-2 ps-5">
            @foreach (['css', 'library', 'tokens', 'reduced', 'layout'] as $rule)
                <li>{{ $t('rules.'.$rule) }}</li>
            @endforeach
        </ul>
    </section>

    {{-- ── Preferito ─────────────────────────────────────────────────────── --}}
    <x-styleguide.component id="favorite" :title="$t('favorite.title')" :description="$t('favorite.description')" tag="x-ui.animal-card.favorite" :a11y="[$t('favorite.a11y_state'), $t('favorite.a11y_reduced')]">
        <x-styleguide.example :title="$t('favorite.example')" background="surface">
            <x-slot:code>@verbatim<x-ui.animal-card.favorite :name="$animal->name" :pressed="$favorited" wire:click="toggle" />@endverbatim</x-slot:code>
            <x-ui.animal-card.favorite
                :name="$t('demo_name')"
                x-data="{ pressed: false }"
                x-on:click="pressed = ! pressed"
                x-bind:aria-pressed="pressed.toString()"
                data-demo="favorite"
            />
        </x-styleguide.example>
    </x-styleguide.component>

    {{-- ── Toast ─────────────────────────────────────────────────────────── --}}
    <x-styleguide.component id="toast" :title="$t('toast.title')" :description="$t('toast.description')" :a11y="[$t('toast.a11y_reduced')]">
        <x-styleguide.example :title="$t('toast.example')">
            <x-slot:code>@verbatim$this->dispatch('toast', type: 'success', title: __('…'));@endverbatim</x-slot:code>
            <x-ui.button
                x-data
                x-on:click="$dispatch('toast', {{ Js::from(['type' => 'success', 'title' => $t('toast.sample_title'), 'description' => $t('toast.sample_description')]) }})"
                data-demo="toast"
            >{{ $t('toast.launch') }}</x-ui.button>
        </x-styleguide.example>
    </x-styleguide.component>

    {{-- ── Transizione card → scheda ────────────────────────────────────── --}}
    <x-styleguide.component id="photo-transition" :title="$t('transition.title')" :description="$t('transition.description')" tag="wire:transition.navigate" :a11y="[$t('transition.a11y_reduced'), $t('transition.a11y_unique')]">
        <x-styleguide.example :title="$t('transition.example')" background="surface">
            <x-slot:code>@verbatim{{-- Card: il nome arriva da x-site.animal-card --}}
<x-site.animal-card :animal="$animal" />

{{-- Scheda: la galleria con lo stesso nome --}}
<x-ui.gallery :media="$photos" :name="$animal->name"
    :transition-name="App\Support\Site\AnimalPhotoTransition::name($animal)" />@endverbatim</x-slot:code>
            <p class="max-w-2xl text-small text-fg-muted">{{ $t('transition.hint') }}</p>
        </x-styleguide.example>
    </x-styleguide.component>

    {{-- ── Reveal allo scroll ────────────────────────────────────────────── --}}
    <x-styleguide.component id="reveal" :title="$t('reveal.title')" :description="$t('reveal.description')" tag="x-reveal" :a11y="[$t('reveal.a11y_nojs'), $t('reveal.a11y_fold'), $t('reveal.a11y_focus'), $t('reveal.a11y_reduced')]">
        <x-styleguide.example :title="$t('reveal.example')">
            <x-slot:code>@verbatim<x-ui.section-heading :title="__('…')" />            {{-- reveal di default --}}
<x-site.animal-card :animal="$animal" />                 {{-- reveal di default --}}
<x-ui.stat :value="3" :label="__('…')" :reveal="false" /> {{-- spento (es. nella hero) --}}
<div class="…" x-reveal>…</div>                         {{-- qualunque blocco --}}@endverbatim</x-slot:code>
            <p class="w-full text-small text-fg-muted">{{ $t('reveal.hint') }}</p>
            <ul role="list" aria-label="{{ $t('reveal.list_label') }}" class="grid w-full gap-4 sm:grid-cols-2 lg:grid-cols-4" data-demo="reveal-grid">
                @foreach ($revealItems as $item)
                    <li>
                        <x-ui.stat :value="$loop->iteration" :label="$t('reveal.items.'.$item)" :tone="$revealTones[$loop->index % 4]" size="md" class="h-full" data-demo-reveal="{{ $item }}" />
                    </li>
                @endforeach
            </ul>
        </x-styleguide.example>
    </x-styleguide.component>

    <x-ui.toaster />
</x-styleguide.layout>
