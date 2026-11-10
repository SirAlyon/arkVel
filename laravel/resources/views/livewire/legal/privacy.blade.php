{{--
 | /privacy (App\Livewire\Legal\PrivacyPolicy): informativa sul trattamento dei dati (GDPR
 | art. 13). Contenuto da lang/*/legal_privacy.php; intelaiatura (avviso di bozza, indice,
 | versione) in x-site.legal-page. Gerarchia: h1 → h2 di sezione (x-site.legal-section) → h3.
 | Testo marcato come BOZZA DA VALIDARE (avviso in cima e commento nei file lang).
--}}
@inject('accountLinks', 'App\Support\Site\AccountLinks')

@php
    $sections = ['controller', 'data', 'purposes', 'recipients', 'cookies', 'retention', 'transfers', 'rights', 'minors', 'changes'];
    $toc = array_map(fn (string $id): array => ['id' => $id, 'label' => __("legal_privacy.{$id}.title")], $sections);
@endphp

<x-site.legal-page
    :title="__('legal_privacy.title')"
    :intro="__('legal_privacy.intro')"
    :eyebrow="__('legal_privacy.eyebrow')"
    :version="$version"
    :updated-on="$updatedOn"
    :toc="$toc"
>
    <x-site.legal-section id="controller" :title="__('legal_privacy.controller.title')">
        <p class="text-fg-muted">{{ __('legal_privacy.controller.body') }}</p>
        <dl class="flex flex-col gap-2 rounded-card bg-surface p-6">
            <div><dt class="sr-only">{{ __('legal_privacy.controller.title') }}</dt><dd class="font-bold text-fg">{{ __('legal_privacy.controller.name') }}</dd></div>
            @foreach (['address', 'email', 'pec'] as $field)
                <div class="flex flex-wrap gap-x-2">
                    <dt class="font-semibold text-fg">{{ __("legal_privacy.controller.{$field}_label") }}:</dt>
                    <dd class="text-fg-muted">{{ __("legal_privacy.controller.{$field}") }}</dd>
                </div>
            @endforeach
        </dl>
        <p class="text-small text-fg-muted">{{ __('legal_privacy.controller.note') }}</p>
    </x-site.legal-section>

    <x-site.legal-section id="data" :title="__('legal_privacy.data.title')">
        <p class="text-fg-muted">{{ __('legal_privacy.data.intro') }}</p>
        <ul role="list" class="flex flex-col gap-3">
            @foreach (__('legal_privacy.data.items') as $item)
                <li class="flex gap-3">
                    <x-ui.icon name="dot" class="mt-1 shrink-0 text-primary" aria-hidden="true" />
                    <span><span class="font-semibold text-fg">{{ $item['label'] }}:</span> <span class="text-fg-muted">{{ $item['text'] }}</span></span>
                </li>
            @endforeach
        </ul>
    </x-site.legal-section>

    <x-site.legal-section id="purposes" :title="__('legal_privacy.purposes.title')">
        <p class="text-fg-muted">{{ __('legal_privacy.purposes.intro') }}</p>
        <ul role="list" class="flex flex-col gap-3">
            @foreach (__('legal_privacy.purposes.items') as $item)
                <li class="flex gap-3">
                    <x-ui.icon name="dot" class="mt-1 shrink-0 text-primary" aria-hidden="true" />
                    <span><span class="font-semibold text-fg">{{ $item['label'] }}:</span> <span class="text-fg-muted">{{ $item['text'] }}</span></span>
                </li>
            @endforeach
        </ul>
    </x-site.legal-section>

    <x-site.legal-section id="recipients" :title="__('legal_privacy.recipients.title')">
        <p class="text-fg-muted">{{ __('legal_privacy.recipients.intro') }}</p>
        <ul role="list" class="flex flex-col gap-3">
            @foreach (__('legal_privacy.recipients.items') as $item)
                <li class="flex gap-3">
                    <x-ui.icon name="dot" class="mt-1 shrink-0 text-primary" aria-hidden="true" />
                    <span><span class="font-semibold text-fg">{{ $item['label'] }}:</span> <span class="text-fg-muted">{{ $item['text'] }}</span></span>
                </li>
            @endforeach
        </ul>
        <p class="text-fg-muted">{{ __('legal_privacy.recipients.note') }}</p>
    </x-site.legal-section>

    <x-site.legal-section id="cookies" :title="__('legal_privacy.cookies.title')">
        @foreach (__('legal_privacy.cookies.body') as $paragraph)
            <p class="text-fg-muted">{{ $paragraph }}</p>
        @endforeach
    </x-site.legal-section>

    <x-site.legal-section id="retention" :title="__('legal_privacy.retention.title')">
        <p class="text-fg-muted">{{ __('legal_privacy.retention.body') }}</p>
    </x-site.legal-section>

    <x-site.legal-section id="transfers" :title="__('legal_privacy.transfers.title')">
        <p class="text-fg-muted">{{ __('legal_privacy.transfers.body') }}</p>
    </x-site.legal-section>

    <x-site.legal-section id="rights" :title="__('legal_privacy.rights.title')">
        <p class="text-fg-muted">{{ __('legal_privacy.rights.intro') }}</p>
        <ul role="list" class="flex flex-col gap-2">
            @foreach (__('legal_privacy.rights.items') as $right)
                <li class="flex gap-3 text-fg-muted">
                    <x-ui.icon name="check" class="mt-1 shrink-0 text-primary" aria-hidden="true" />
                    <span>{{ $right }}</span>
                </li>
            @endforeach
        </ul>
        <p class="text-fg-muted">
            {{ __('legal_privacy.rights.complaint_intro') }}
            <x-ui.link href="https://www.garanteprivacy.it" external>{{ __('legal_privacy.rights.garante') }}</x-ui.link>.
        </p>
        <p class="text-fg-muted">{{ __('legal_privacy.rights.how') }}</p>
        <p><x-ui.link :href="$accountLinks->account()" wire:navigate.hover>{{ __('legal_privacy.rights.account_link') }}</x-ui.link></p>
    </x-site.legal-section>

    <x-site.legal-section id="minors" :title="__('legal_privacy.minors.title')">
        <p class="text-fg-muted">{{ __('legal_privacy.minors.body') }}</p>
    </x-site.legal-section>

    <x-site.legal-section id="changes" :title="__('legal_privacy.changes.title')">
        <p class="text-fg-muted">{{ __('legal_privacy.changes.body') }}</p>
    </x-site.legal-section>
</x-site.legal-page>
