{{--
 | Paginazione di una lista: «Pagina n di N», precedente, numeri di pagina, successiva.
 | Link veri con href ASSOLUTI (funzionano senza JavaScript e per i motori di ricerca):
 | il paginatore di Livewire dà URL relativi (`enti/slug?page=2`), che da /enti/slug
 | punterebbero a /enti/enti/slug, quindi ogni URL passa da url().
 |
 | Due sorgenti:
 | - `paginator`: un LengthAwarePaginator (WithPagination di Livewire o Eloquent): pagina
 |   corrente, ultima e URL arrivano da lì (con la query string di appends());
 | - oppure `current`, `last` e `url` (closure int → URL) per liste paginate a mano (il
 |   catalogo). `current-to` estende la pagina corrente a un intervallo (le pagine caricate
 |   di seguito con «Carica altri»): tutte risultano correnti.
 |
 | Due comportamenti dei link (`mode`):
 | - `navigate` (default): link con wire:navigate (navigazione SPA della shell);
 | - `livewire`: dentro un componente con WithPagination il clic cambia pagina sul posto
 |   (gotoPage / previousPage / nextPage, con `page-name`) e porta in vista `scroll-to`
 |   (id, default `main`). Il link resta vero per chi apre in una nuova scheda.
 |
 | Accessibilità: <nav> con nome (`label`, default «Paginazione»), pagina corrente con
 | aria-current="page" e fondo `primary` (non solo colore: anche il bordo pieno), ogni
 | numero con un nome completo («Pagina 3»), rel="prev"/"next", target da 44 px; ai capi
 | della lista precedente/successiva restano visibili ma disattivati (aria-disabled).
 | Niente se c'è una pagina sola.
 |
 | <x-ui.pagination :paginator="$organizations" mode="livewire" scroll-to="organizations-results" />
 | <x-ui.pagination :current="$page" :current-to="$lastLoaded" :last="$lastPage" :url="$results->pageUrl(...)" :label="__('…')" />
--}}
@props([
    'paginator' => null,
    'current' => 1,
    'currentTo' => null,
    'last' => 1,
    'url' => null,
    'mode' => 'navigate',
    'pageName' => null,
    'scrollTo' => 'main',
    'label' => null,
    'onEachSide' => 1,
])

@php
    if ($paginator instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator) {
        $current = $paginator->currentPage();
        $currentTo = null;
        $last = $paginator->lastPage();
        $pageName ??= $paginator->getPageName();
        $url = static fn (int $page): string => url($paginator->url($page));
    }

    $last = max(1, (int) $last);
    $current = min(max(1, (int) $current), $last);
    $currentTo = min(max($current, (int) ($currentTo ?? $current)), $last);
    $pageName ??= 'page';
    // Senza `url` (sconsigliato): l'URL della richiesta con la pagina cambiata.
    $toUrl = $url instanceof \Closure ? $url : static fn (int $page): string => request()->fullUrlWithQuery([$pageName => $page > 1 ? $page : null]);
    $livewire = $mode === 'livewire';
    $scroll = "document.getElementById('".e($scrollTo)."')?.scrollIntoView({ block: 'start' })";
    $previous = $current > 1 ? $current - 1 : null;
    $next = $currentTo < $last ? $currentTo + 1 : null;
    $label ??= __('ui_navigation.pagination.label');
    $summary = $currentTo > $current
        ? __('ui_navigation.pagination.summary_range', ['from' => $current, 'to' => $currentTo, 'last' => $last])
        : __('ui_navigation.pagination.summary', ['page' => $current, 'last' => $last]);
    $pageClasses = 'inline-flex size-11 items-center justify-center rounded-full border-2 font-bold tabular-nums no-underline transition-colors duration-(--duration-fast) ease-out';
@endphp

@if ($last > 1)
    <nav aria-label="{{ $label }}" {{ $attributes->class('flex flex-col items-center gap-4 sm:flex-row sm:justify-between') }} data-pagination>
        <p class="text-small font-semibold text-fg-muted" data-pagination-summary>{{ $summary }}</p>

        <ul role="list" class="flex flex-wrap items-center justify-center gap-2">
            <li>
                @if ($previous === null)
                    <x-ui.button variant="secondary" size="sm" icon="chevron-left" :label="__('ui_navigation.pagination.previous')" href="#" disabled data-pagination-previous />
                @elseif ($livewire)
                    <x-ui.button
                        variant="secondary"
                        size="sm"
                        icon="chevron-left"
                        :label="__('ui_navigation.pagination.previous')"
                        :href="$toUrl($previous)"
                        rel="prev"
                        wire:click.prevent="previousPage('{{ $pageName }}')"
                        x-on:click="{{ $scroll }}"
                        data-pagination-previous
                    />
                @else
                    <x-ui.button variant="secondary" size="sm" icon="chevron-left" :label="__('ui_navigation.pagination.previous')" :href="$toUrl($previous)" rel="prev" wire:navigate data-pagination-previous />
                @endif
            </li>

            @foreach (\App\Support\Site\PaginationWindow::pages($current, $last, $currentTo, (int) $onEachSide) as $page)
                <li @if ($page === null) aria-hidden="true" class="inline-flex size-11 items-center justify-center font-bold text-fg-muted" @endif>
                    @if ($page === null)
                        …
                    @elseif ($page >= $current && $page <= $currentTo)
                        <a
                            href="{{ $toUrl($page) }}"
                            aria-current="page"
                            aria-label="{{ __('ui_navigation.pagination.page', ['page' => $page]) }}"
                            class="{{ $pageClasses }} border-primary bg-primary text-on-primary"
                            @if ($livewire) wire:click.prevent @endif
                            data-pagination-page="{{ $page }}"
                        >{{ $page }}</a>
                    @else
                        <a
                            href="{{ $toUrl($page) }}"
                            aria-label="{{ __('ui_navigation.pagination.page', ['page' => $page]) }}"
                            class="{{ $pageClasses }} border-line-strong bg-canvas text-fg hover:border-fg"
                            @if ($livewire)
                                wire:click.prevent="gotoPage({{ $page }}, '{{ $pageName }}')"
                                x-on:click="{{ $scroll }}"
                            @else
                                wire:navigate
                            @endif
                            data-pagination-page="{{ $page }}"
                        >{{ $page }}</a>
                    @endif
                </li>
            @endforeach

            <li>
                @if ($next === null)
                    <x-ui.button variant="secondary" size="sm" icon="chevron-right" :label="__('ui_navigation.pagination.next')" href="#" disabled data-pagination-next />
                @elseif ($livewire)
                    <x-ui.button
                        variant="secondary"
                        size="sm"
                        icon="chevron-right"
                        :label="__('ui_navigation.pagination.next')"
                        :href="$toUrl($next)"
                        rel="next"
                        wire:click.prevent="nextPage('{{ $pageName }}')"
                        x-on:click="{{ $scroll }}"
                        data-pagination-next
                    />
                @else
                    <x-ui.button variant="secondary" size="sm" icon="chevron-right" :label="__('ui_navigation.pagination.next')" :href="$toUrl($next)" rel="next" wire:navigate data-pagination-next />
                @endif
            </li>
        </ul>
    </nav>
@endif
