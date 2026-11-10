{{--
 | /termini (App\Livewire\Legal\TermsOfService): termini di servizio della piattaforma.
 | Contenuto da lang/*/legal_terms.php; intelaiatura in x-site.legal-page. Gerarchia:
 | h1 → h2 di sezione (x-site.legal-section) → h3. BOZZA DA VALIDARE (avviso in cima).
--}}
@php
    $sections = ['object', 'account', 'conduct', 'content', 'donations', 'liability', 'law', 'changes'];
    $toc = array_map(fn (string $id): array => ['id' => $id, 'label' => __("legal_terms.{$id}.title")], $sections);
@endphp

<x-site.legal-page
    :title="__('legal_terms.title')"
    :intro="__('legal_terms.intro')"
    :eyebrow="__('legal_terms.eyebrow')"
    :version="$version"
    :updated-on="$updatedOn"
    :toc="$toc"
>
    <x-site.legal-section id="object" :title="__('legal_terms.object.title')">
        <p class="text-fg-muted">{{ __('legal_terms.object.body') }}</p>
    </x-site.legal-section>

    <x-site.legal-section id="account" :title="__('legal_terms.account.title')">
        @foreach (__('legal_terms.account.body') as $paragraph)
            <p class="text-fg-muted">{{ $paragraph }}</p>
        @endforeach
    </x-site.legal-section>

    <x-site.legal-section id="conduct" :title="__('legal_terms.conduct.title')">
        <p class="text-fg-muted">{{ __('legal_terms.conduct.intro') }}</p>
        <ul role="list" class="flex flex-col gap-2">
            @foreach (__('legal_terms.conduct.items') as $item)
                <li class="flex gap-3 text-fg-muted">
                    <x-ui.icon name="x" class="mt-1 shrink-0 text-primary" aria-hidden="true" />
                    <span>{{ $item }}</span>
                </li>
            @endforeach
        </ul>
    </x-site.legal-section>

    <x-site.legal-section id="content" :title="__('legal_terms.content.title')">
        <p class="text-fg-muted">{{ __('legal_terms.content.body') }}</p>
    </x-site.legal-section>

    <x-site.legal-section id="donations" :title="__('legal_terms.donations.title')">
        <p class="text-fg-muted">{{ __('legal_terms.donations.body') }}</p>
    </x-site.legal-section>

    <x-site.legal-section id="liability" :title="__('legal_terms.liability.title')">
        <p class="text-fg-muted">{{ __('legal_terms.liability.body') }}</p>
    </x-site.legal-section>

    <x-site.legal-section id="law" :title="__('legal_terms.law.title')">
        <p class="text-fg-muted">{{ __('legal_terms.law.body') }}</p>
    </x-site.legal-section>

    <x-site.legal-section id="changes" :title="__('legal_terms.changes.title')">
        <p class="text-fg-muted">{{ __('legal_terms.changes.body') }}</p>
    </x-site.legal-section>
</x-site.legal-page>
