<?php

declare(strict_types=1);
use Illuminate\Pagination\LengthAwarePaginator;

/*
 | Componenti x-ui del gruppo "actions": icon, button, link, badge, tag, pagination.
 | Test di rendering (markup, ARIA, varianti). Comportamento nel browser:
 | tests/Browser/Components/ActionsComponentsTest.php.
 */

describe('icon', function (): void {
    it('renders a decorative Lucide icon by default', function (): void {
        $this->blade('<x-ui.icon name="paw-print" />')
            ->assertSee('data-icon="paw-print"', escape: false)
            ->assertSee('aria-hidden="true"', escape: false)
            ->assertSee('class="size-5 shrink-0"', escape: false)
            ->assertDontSee('role="img"', escape: false);
    });

    it('becomes a named image when it has a label', function (): void {
        $this->blade('<x-ui.icon name="circle-check" size="lg" label="Ente verificato" class="text-success" />')
            ->assertSee('role="img"', escape: false)
            ->assertSee('aria-label="Ente verificato"', escape: false)
            ->assertSee('class="size-6 shrink-0 text-success"', escape: false)
            ->assertDontSee('aria-hidden', escape: false);
    });
});

describe('button', function (): void {
    it('renders a primary button by default', function (): void {
        $this->blade('<x-ui.button wire:click="save">Salva</x-ui.button>')
            ->assertSee('<button', escape: false)
            ->assertSee('type="button"', escape: false)
            ->assertSee('data-variant="primary"', escape: false)
            ->assertSee('bg-primary text-on-primary', escape: false)
            ->assertSee('hover:bg-primary-hover', escape: false)
            ->assertSee('min-h-12', escape: false)
            ->assertSee('wire:click="save"', escape: false)
            ->assertSee('Salva');
    });

    it('applies every variant and size', function (string $variant, string $classes): void {
        $this->blade('<x-ui.button :variant="$variant" size="lg">Ok</x-ui.button>', ['variant' => $variant])
            ->assertSee($classes, escape: false)
            ->assertSee('min-h-14', escape: false);
    })->with([
        ['secondary', 'border-2 border-fg text-fg'],
        ['highlight', 'bg-highlight text-on-highlight'],
        ['ghost', 'hover:bg-surface'],
        ['danger', 'bg-danger text-on-danger'],
    ]);

    it('keeps every size at least 44 px tall', function (string $size, string $box): void {
        $this->blade('<x-ui.button :size="$size">Ok</x-ui.button>', ['size' => $size])->assertSee($box, escape: false);
        $this->blade('<x-ui.button :size="$size" icon="x" label="Chiudi" />', ['size' => $size])->assertSee(str_replace('min-h-', 'size-', explode(' ', $box)[0]), escape: false);
    })->with([
        ['sm', 'min-h-11 px-4'],
        ['md', 'min-h-12 px-6'],
        ['lg', 'min-h-14 px-7'],
    ]);

    it('renders a link when it has an href', function (): void {
        $this->blade('<x-ui.button href="/animali" type="submit">Catalogo</x-ui.button>')
            ->assertSee('<a', escape: false)
            ->assertSee('href="/animali"', escape: false)
            ->assertDontSee('type="submit"', escape: false);
    });

    it('names an icon-only button with its label', function (): void {
        $this->blade('<x-ui.button variant="ghost" icon="x" label="Chiudi" />')
            ->assertSee('aria-label="Chiudi"', escape: false)
            ->assertSee('data-icon="x"', escape: false)
            ->assertSee('size-12', escape: false);
    });

    it('renders leading and trailing icons next to the text', function (): void {
        $this->blade('<x-ui.button icon="search" icon-trailing="arrow-right">Cerca</x-ui.button>')
            ->assertSeeInOrder(['data-icon="search"', 'Cerca', 'data-icon="arrow-right"'], escape: false)
            ->assertDontSee('aria-label=', escape: false);
    });

    it('disables a button natively and drops the hover', function (): void {
        $this->blade('<x-ui.button disabled>Invia</x-ui.button>')
            ->assertSee('disabled', escape: false)
            ->assertSee('cursor-not-allowed bg-surface text-fg-muted', escape: false)
            ->assertDontSee('hover:bg-primary-hover', escape: false);
    });

    it('disables a link by dropping the href', function (): void {
        $this->blade('<x-ui.button href="/animali" disabled>Catalogo</x-ui.button>')
            ->assertSee('aria-disabled="true"', escape: false)
            ->assertSee('role="link"', escape: false)
            ->assertDontSee('href=', escape: false);
    });

    it('shows a busy state while loading', function (): void {
        $this->blade('<x-ui.button loading>Salva</x-ui.button>')
            ->assertSee('data-loading', escape: false)
            ->assertSee('aria-busy="true"', escape: false)
            ->assertSee('disabled', escape: false)
            ->assertSee('data-icon="loader-circle"', escape: false)
            ->assertSee('Caricamento in corso');
    });

    it('translates the loading announcement', function (): void {
        app()->setLocale('en');

        $this->blade('<x-ui.button loading>Save</x-ui.button>')->assertSee('Loading');
    });

    it('never grows wider than its container: long labels wrap, balanced and centred', function (): void {
        $this->blade('<x-ui.button href="/enti">Scopri tutti gli enti che si prendono cura degli animali</x-ui.button>')
            ->assertSee('max-w-full', escape: false)
            ->assertSee('text-balance', escape: false)
            ->assertSee('text-center', escape: false)
            ->assertSee('<span class="min-w-0 wrap-break-word">', escape: false);

        // Un bottone solo icona resta un cerchio: niente padding verticale.
        $this->blade('<x-ui.button icon="x" label="Chiudi" />')->assertDontSee('py-2', escape: false);
    });
});

describe('pagination', function (): void {
    it('renders nothing for a single page', function (): void {
        expect(trim((string) $this->blade('<x-ui.pagination :current="1" :last="1" :url="fn ($page) => \'/l?page=\'.$page" />')))->toBe('');
    });

    it('links every shown page with absolute urls, the current one marked', function (): void {
        $html = (string) $this->blade('<x-ui.pagination :current="5" :last="9" :url="fn (int $page): string => url(\'/lista?page=\'.$page)" />');

        expect($html)
            ->toContain('<nav aria-label="Paginazione"')
            ->toContain('Pagina 5 di 9')
            ->toMatch('/href="'.preg_quote(url('/lista?page=4'), '/').'"[^>]*rel="prev"/s')
            ->toMatch('/href="'.preg_quote(url('/lista?page=6'), '/').'"[^>]*rel="next"/s')
            ->toMatch('/aria-current="page"\s+aria-label="Pagina 5"/')
            ->toContain('data-pagination-page="1"')
            ->toContain('data-pagination-page="9"')
            ->not->toContain('data-pagination-page="2"')
            ->toContain('aria-hidden="true"')
            ->toContain('wire:navigate');
    });

    it('disables previous on the first page and next on the last one', function (): void {
        $first = (string) $this->blade('<x-ui.pagination :current="1" :last="3" :url="fn ($page) => \'/l?page=\'.$page" />');
        $last = (string) $this->blade('<x-ui.pagination :current="3" :last="3" :url="fn ($page) => \'/l?page=\'.$page" />');

        expect($first)->toMatch('/aria-disabled="true"[^>]*aria-label="Pagina precedente"/s')
            ->and($last)->toMatch('/aria-disabled="true"[^>]*aria-label="Pagina successiva"/s');
    });

    it('marks a range of loaded pages as current and summarises it', function (): void {
        $html = (string) $this->blade('<x-ui.pagination :current="1" :current-to="3" :last="5" :url="fn ($page) => \'/l?page=\'.$page" label="Pagine del catalogo" />');

        expect($html)->toContain('aria-label="Pagine del catalogo"')
            ->toContain('Pagine da 1 a 3 di 5')
            ->and(substr_count($html, 'aria-current="page"'))->toBe(1)
            ->and($html)->toMatch('/href="\/l\?page=4"[^>]*rel="next"/s');
    });

    it('changes page in place inside a Livewire component, from a paginator', function (): void {
        $paginator = new LengthAwarePaginator(range(1, 12), 30, 12, 2, ['path' => 'enti/il-faro']);

        $html = (string) $this->blade('<x-ui.pagination :paginator="$paginator" mode="livewire" scroll-to="results" />', ['paginator' => $paginator]);

        expect($html)
            ->toContain('href="'.url('enti/il-faro?page=3').'"')
            ->toContain('wire:click.prevent="nextPage(\'page\')"')
            ->toContain('wire:click.prevent="gotoPage(3, \'page\')"')
            ->toContain('getElementById(&#039;results&#039;)')
            ->not->toContain('wire:navigate');
    });

    it('speaks English', function (): void {
        app()->setLocale('en');

        $this->blade('<x-ui.pagination :current="2" :last="3" :url="fn ($page) => \'/l?page=\'.$page" />')
            ->assertSee('Page 2 of 3')
            ->assertSee('aria-label="Previous page"', escape: false);
    });
});

describe('link', function (): void {
    it('renders an underlined link', function (): void {
        $this->blade('<x-ui.link href="/animali">Catalogo</x-ui.link>')
            ->assertSee('href="/animali"', escape: false)
            ->assertSee('underline', escape: false)
            ->assertSee('text-link', escape: false)
            ->assertDontSee('target="_blank"', escape: false);
    });

    it('announces external links that open in a new tab', function (): void {
        $this->blade('<x-ui.link href="https://lucide.dev" external>Lucide</x-ui.link>')
            ->assertSee('target="_blank"', escape: false)
            ->assertSee('rel="noopener noreferrer"', escape: false)
            ->assertSee('data-icon="arrow-up-right"', escape: false)
            ->assertSee('(si apre in una nuova scheda)');
    });

    it('inherits the text colour on coloured blocks', function (): void {
        $this->blade('<x-ui.link href="#" variant="inherit">Catalogo</x-ui.link>')
            ->assertSee('text-current', escape: false)
            ->assertDontSee('hover:text-link-hover', escape: false);
    });
});

describe('badge', function (): void {
    it('renders each variant with its token pair', function (string $variant, string $classes): void {
        $this->blade('<x-ui.badge :variant="$variant">Stato</x-ui.badge>', ['variant' => $variant])
            ->assertSee('data-variant="'.$variant.'"', escape: false)
            ->assertSee($classes, escape: false)
            ->assertSee('Stato');
    })->with([
        ['neutral', 'border-2 border-line text-fg'],
        ['primary', 'bg-primary text-on-primary'],
        ['highlight', 'bg-highlight text-on-highlight'],
        ['stamp', 'bg-stamp text-on-stamp'],
        ['success', 'bg-success-subtle text-on-success-subtle'],
        ['warning', 'bg-warning-subtle text-on-warning-subtle'],
        ['danger', 'bg-danger-subtle text-on-danger-subtle'],
        ['info', 'bg-info-subtle text-on-info-subtle'],
    ]);

    it('adds a decorative icon', function (): void {
        $this->blade('<x-ui.badge variant="success" icon="circle-check">Adottato</x-ui.badge>')
            ->assertSee('data-icon="circle-check"', escape: false)
            ->assertSee('aria-hidden="true"', escape: false);
    });
});

describe('tag', function (): void {
    it('renders a static label that is not interactive', function (): void {
        $this->blade('<x-ui.tag>Taglia media</x-ui.tag>')
            ->assertSee('<span', escape: false)
            ->assertDontSee('<button', escape: false)
            ->assertSee('Taglia media');
    });

    it('renders a toggle filter with aria-pressed', function (bool $pressed): void {
        $this->blade('<x-ui.tag :pressed="$pressed" wire:click="toggle">Cani</x-ui.tag>', ['pressed' => $pressed])
            ->assertSee('<button', escape: false)
            ->assertSee('aria-pressed="'.($pressed ? 'true' : 'false').'"', escape: false)
            ->assertSee('border-line-strong', escape: false)
            ->assertSee('wire:click="toggle"', escape: false);
    })->with([true, false]);

    it('names the remove button after its label', function (): void {
        $this->blade('<x-ui.tag removable label="Cani" wire:click="remove" class="ms-2">Cani</x-ui.tag>')
            ->assertSee('aria-label="Rimuovi Cani"', escape: false)
            ->assertSee('data-icon="x"', escape: false)
            ->assertSee('size-11', escape: false)
            ->assertSeeInOrder(['ms-2"', 'wire:click="remove"'], escape: false);
    });

    it('falls back to the slot text for the remove label', function (): void {
        app()->setLocale('en');

        $this->blade('<x-ui.tag removable>Cats</x-ui.tag>')->assertSee('aria-label="Remove Cats"', escape: false);
    });
});

describe('theme toggle', function (): void {
    it('uses Lucide icons through x-ui.icon', function (): void {
        $this->blade('<x-ui.theme-toggle />')
            ->assertSeeInOrder(['data-icon="sun"', 'data-icon="moon"', 'data-icon="monitor"'], escape: false)
            ->assertDontSee('{!!', escape: false);
    });
});

it('marks only the first loaded page as current when a range of pages is shown', function (): void {
    $html = (string) $this->blade('<x-ui.pagination :current="2" :current-to="4" :last="6" :url="fn ($page) => \'/animali?page=\'.$page" />');

    expect(substr_count($html, 'aria-current="page"'))->toBe(1)
        ->and($html)->toContain('aria-label="Pagina 3, già mostrata"')
        ->and($html)->toContain('aria-label="Pagina 2"');
});
