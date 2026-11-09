{{--
 | Card di un ente (canile, rifugio, associazione): logo o iniziali, nome, sede, tipo,
 | badge «Verificato» e numero di animali disponibili. Props primitive, niente Model.
 | Stesso pattern "link esteso" della card animale (adattato da Sheaf UI, MIT): l'unico
 | link è sul nome e il suo ::after copre tutta la card.
 |
 | - `name`, `href` (obbligatori): nome dell'ente e pagina pubblica;
 | - `logo`: URL del logo, in <x-ui.avatar shape="square">. Senza logo: le iniziali su un
 |   blocco colorato. L'avatar è decorativo, perché il nome è scritto accanto;
 | - `location`: la sede già pronta (App\Support\Site\PublicLocation::of($organization));
 |   in alternativa `city` e `region`, composti nello stesso formato «città, regione»;
 | - `type`: tipo di ente già tradotto (es. `$organization->type->getLabel()`);
 | - `verified`: badge «Verificato» con icona (il significato è nel testo);
 | - `available`: numero di animali disponibili, formattato nella lingua corrente
 |   («Nessun animale disponibile», «1 animale disponibile», «1.240 animali disponibili»);
 | - `navigate` (default true): il link usa wire:navigate.hover (navigazione SPA della shell),
 |   come x-ui.animal-card;
 | - `heading-level` (default 3): livello del titolo (h2…h6) secondo la pagina.
 |
 | <x-ui.organization-card
 |     :name="$organization->name"
 |     :href="route('organizations.show', $organization)"
 |     :logo="$organization->getFirstMediaUrl('logo') ?: null"
 |     :location="\App\Support\Site\PublicLocation::of($organization)"
 |     :type="$organization->type->getLabel()"
 |     :verified="$organization->isVerified()"
 |     :available="$organization->available_animals_count"
 | />
--}}
@props([
    'name',
    'href',
    'logo' => null,
    'location' => null,
    'city' => null,
    'region' => null,
    'type' => null,
    'verified' => false,
    'available' => null,
    'navigate' => true,
    'headingLevel' => 3,
])

@use('Illuminate\Support\Number')

@php
    $level = min(6, max(2, (int) $headingLevel));
    $location = filled($location) ? (string) $location : (string) \App\Support\Site\PublicLocation::format($city, $region);
    $availableText = $available !== null
        ? trans_choice('ui_domain.organization_card.available', (int) $available, ['count' => Number::format((int) $available, locale: app()->getLocale())])
        : null;
@endphp

<article
    data-organization-card
    {{ $attributes->class([
        'group/card relative flex h-full flex-col gap-4 rounded-card bg-surface p-5 text-fg',
        'transition-transform duration-(--duration-base) ease-out',
        'motion-safe:hover:-translate-y-1 motion-safe:has-[[data-card-link]:focus-visible]:-translate-y-1',
    ]) }}
>
    <div class="flex items-center gap-4">
        <x-ui.avatar :name="$name" :src="$logo" shape="square" size="lg" decorative />

        <div class="flex min-w-0 flex-col gap-0.5">
            <h{{ $level }} class="text-h5 font-extrabold">
                <a
                    href="{{ $href }}"
                    @if ($navigate) wire:navigate.hover @endif
                    data-card-link
                    class="flex min-h-11 items-center rounded-sm text-fg no-underline decoration-2 underline-offset-4 group-hover/card:underline after:absolute after:inset-0 after:rounded-card"
                >{{ $name }}</a>
            </h{{ $level }}>
            @if ($location !== '')
                <p class="flex items-center gap-1.5 text-small text-fg-muted">
                    <x-ui.icon name="map-pin" size="sm" />
                    <span class="sr-only">{{ __('ui_domain.organization_card.location') }}</span>
                    <span class="min-w-0 truncate">{{ $location }}</span>
                </p>
            @endif
        </div>
    </div>

    @if (filled($type) || $verified)
        <ul role="list" class="flex flex-wrap gap-1.5">
            @if (filled($type))
                <li><x-ui.badge class="bg-canvas">{{ $type }}</x-ui.badge></li>
            @endif
            @if ($verified)
                <li><x-ui.badge variant="success" icon="badge-check" data-organization-verified>{{ __('ui_domain.organization_card.verified') }}</x-ui.badge></li>
            @endif
        </ul>
    @endif

    @if ($availableText !== null)
        <p class="mt-auto flex items-center gap-2 font-semibold" data-organization-available>
            <span class="inline-flex size-8 shrink-0 items-center justify-center rounded-icon bg-block-mint text-on-block-mint">
                <x-ui.icon name="paw-print" size="sm" />
            </span>
            {{ $availableText }}
        </p>
    @endif
</article>
