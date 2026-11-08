<?php

declare(strict_types=1);

/*
 | Componenti x-ui del gruppo "content": tabs, accordion, skeleton, empty-state, avatar.
 | Test di rendering (markup, ARIA, varianti, traduzioni). Comportamento nel browser:
 | tests/Browser/Components/ContentComponentsTest.php.
 */

describe('tabs', function (): void {
    $tabs = <<<'BLADE'
        <x-ui.tabs id="animal" selected="health" wire:model.live="tab">
            <x-ui.tabs.list label="Informazioni su Luna">
                <x-ui.tabs.tab name="about" icon="heart">Carattere</x-ui.tabs.tab>
                <x-ui.tabs.tab name="health">Salute</x-ui.tabs.tab>
            </x-ui.tabs.list>
            <x-ui.tabs.panel name="about">Socievole</x-ui.tabs.panel>
            <x-ui.tabs.panel name="health" :focusable="false">Vaccinata</x-ui.tabs.panel>
        </x-ui.tabs>
        BLADE;

    it('renders the APG tabs structure with linked ids', function () use ($tabs): void {
        $this->blade($tabs)
            ->assertSee('role="tablist"', escape: false)
            ->assertSee('aria-label="Informazioni su Luna"', escape: false)
            ->assertSee('id="animal-tab-about"', escape: false)
            ->assertSee('aria-controls="animal-panel-about"', escape: false)
            ->assertSee('id="animal-panel-health"', escape: false)
            ->assertSee('aria-labelledby="animal-tab-health"', escape: false)
            ->assertSee('data-icon="heart"', escape: false);
    });

    it('renders the initial selection on the server', function () use ($tabs): void {
        $html = (string) $this->blade($tabs);

        expect($html)
            ->toMatch('/id="animal-tab-about"[^>]*aria-selected="false"\s+tabindex="-1"/')
            ->toMatch('/id="animal-tab-health"[^>]*aria-selected="true"\s+tabindex="0"/')
            ->toMatch('/id="animal-panel-about"[^>]*tabindex="0"\s+hidden\s/')
            ->not->toMatch('/id="animal-panel-health"[^>]*\shidden\s/')
            ->not->toMatch('/id="animal-panel-health"[^>]*tabindex/');
    });

    it('binds the selection to wire:model through x-modelable', function () use ($tabs): void {
        $this->blade($tabs)
            ->assertSee('x-data="uiTabs(\'health\')"', escape: false)
            ->assertSee('x-modelable="selected"', escape: false)
            ->assertSee('wire:model.live="tab"', escape: false);
    });

    it('styles the tabs as pills with the active one solid primary', function () use ($tabs): void {
        $this->blade($tabs)
            ->assertSee('rounded-full bg-surface p-1', escape: false)
            ->assertSee('min-h-11', escape: false)
            ->assertSee('aria-selected:bg-primary aria-selected:text-on-primary', escape: false)
            ->assertSee('overflow-x-auto', escape: false);
    });
});

describe('accordion', function (): void {
    it('puts each button inside a heading of the configured level', function (): void {
        $this->blade(<<<'BLADE'
            <x-ui.accordion id="faq" heading-level="2">
                <x-ui.accordion.item name="cost" title="Quanto costa?" open>Gratis</x-ui.accordion.item>
                <x-ui.accordion.item name="time" title="Quanto tempo?">Tre settimane</x-ui.accordion.item>
            </x-ui.accordion>
            BLADE)
            ->assertSeeInOrder(['<h2', '<button', 'Quanto costa?', '</h2>', 'role="region"', 'Gratis'], escape: false)
            ->assertSee('id="faq-cost-trigger"', escape: false)
            ->assertSee('aria-controls="faq-cost-panel"', escape: false)
            ->assertSee('aria-labelledby="faq-time-trigger"', escape: false)
            ->assertSee('x-collapse', escape: false)
            ->assertSee('x-data="uiAccordion(false)"', escape: false)
            ->assertSee('rounded-card bg-surface', escape: false);
    });

    it('renders the open state on the server', function (): void {
        $html = (string) $this->blade(<<<'BLADE'
            <x-ui.accordion id="faq">
                <x-ui.accordion.item name="cost" title="Quanto costa?" open>Gratis</x-ui.accordion.item>
                <x-ui.accordion.item name="time" title="Quanto tempo?">Tre settimane</x-ui.accordion.item>
            </x-ui.accordion>
            BLADE);

        expect($html)
            ->toContain('<h3')
            ->toMatch('/id="faq-cost-trigger"[^>]*aria-expanded="true"/')
            ->toMatch('/id="faq-time-trigger"[^>]*aria-expanded="false"/')
            ->not->toMatch('/id="faq-cost-panel"[^>]*\shidden\s/')
            ->toMatch('/id="faq-time-panel"[^>]*\shidden\s/');
    });

    it('supports the multiple mode and clamps the heading level', function (): void {
        $this->blade('<x-ui.accordion multiple heading-level="9"><x-ui.accordion.item title="Specie">…</x-ui.accordion.item></x-ui.accordion>')
            ->assertSee('x-data="uiAccordion(true)"', escape: false)
            ->assertSee('<h6', escape: false);
    });

    it('derives a stable name from the title when none is given', function (): void {
        $first = (string) $this->blade('<x-ui.accordion><x-ui.accordion.item title="Specie">…</x-ui.accordion.item></x-ui.accordion>');
        $second = (string) $this->blade('<x-ui.accordion><x-ui.accordion.item title="Specie">…</x-ui.accordion.item></x-ui.accordion>');

        expect($first)->toContain('data-accordion-trigger="item-'.substr(md5('Specie'), 0, 8).'"')
            ->and($first)->toBe($second);
    });

    it('rotates the chevron from the expanded state, not from colour alone', function (): void {
        $this->blade('<x-ui.accordion><x-ui.accordion.item title="Specie">…</x-ui.accordion.item></x-ui.accordion>')
            ->assertSee('data-icon="chevron-down"', escape: false)
            ->assertSee('group-aria-expanded/trigger:rotate-180', escape: false)
            ->assertSee('duration-(--duration-base)', escape: false);
    });
});

describe('skeleton', function (): void {
    it('announces the loading state and hides the blocks', function (): void {
        $this->blade('<x-ui.skeleton class="flex gap-3"><x-ui.skeleton.block shape="circle" size="lg" /><x-ui.skeleton.block /></x-ui.skeleton>')
            ->assertSee('role="status"', escape: false)
            ->assertDontSee('aria-busy', escape: false)
            ->assertSee('<span class="sr-only">Caricamento in corso…</span>', escape: false)
            ->assertSee('class="flex gap-3"', escape: false)
            ->assertSee('data-skeleton-block="circle"', escape: false)
            ->assertSee('size-14', escape: false)
            ->assertSee('aria-hidden="true"', escape: false)
            ->assertSee('animate-pulse bg-line', escape: false);
    });

    it('uses a custom label and translates the default one', function (): void {
        $this->blade('<x-ui.skeleton label="Caricamento degli animali…" />')->assertSee('Caricamento degli animali…');

        app()->setLocale('en');

        $this->blade('<x-ui.skeleton />')->assertSee('Loading…');
    });

    it('draws every shape with the token radii', function (string $shape, string $classes): void {
        $this->blade('<x-ui.skeleton.block :shape="$shape" />', ['shape' => $shape])
            ->assertSee('data-skeleton-block="'.$shape.'"', escape: false)
            ->assertSee($classes, escape: false);
    })->with([
        ['text', 'h-4 rounded-full'],
        ['heading', 'h-8 rounded-full'],
        ['circle', 'rounded-full size-11'],
        ['icon', 'rounded-icon size-11'],
        ['pill', 'h-11 shrink-0 rounded-full w-32'],
        ['media', 'aspect-4/3 rounded-media'],
        ['card', 'h-40 rounded-card'],
    ]);

    it('lets a width class replace the default size of a shape', function (): void {
        $this->blade('<x-ui.skeleton.block shape="pill" class="w-20" />')
            ->assertSee('w-20', escape: false)
            ->assertDontSee('w-32', escape: false);
    });
});

describe('empty state', function (): void {
    it('renders a variant with its icon, block and translated texts', function (string $variant, string $icon, string $block, string $title): void {
        $this->blade('<x-ui.empty-state :variant="$variant" />', ['variant' => $variant])
            ->assertSee('data-empty-state="'.$variant.'"', escape: false)
            ->assertSee('data-icon="'.$icon.'"', escape: false)
            ->assertSee('bg-block-'.$block.' text-on-block-'.$block, escape: false)
            ->assertSee('<h2 class="text-h4 text-fg">'.$title.'</h2>', escape: false)
            ->assertSee('text-fg-muted', escape: false);
    })->with([
        ['search', 'search-x', 'mint', 'Nessun risultato'],
        ['favorites', 'heart', 'peach', 'Ancora nessun preferito'],
        ['error', 'triangle-alert', 'peach', 'Qualcosa è andato storto'],
    ]);

    it('renders custom texts, heading level, actions and the unframed version', function (): void {
        $this->blade(<<<'BLADE'
            <x-ui.empty-state icon="calendar" block="leaf" title="Nessun appuntamento" description="Le visite compariranno qui." heading-level="3" :framed="false">
                <x-slot:actions><x-ui.button>Prenota</x-ui.button></x-slot:actions>
            </x-ui.empty-state>
            BLADE)
            ->assertSee('<h3 class="text-h4 text-fg">Nessun appuntamento</h3>', escape: false)
            ->assertSee('Le visite compariranno qui.')
            ->assertSee('bg-block-leaf text-on-block-leaf', escape: false)
            ->assertSeeInOrder(['Le visite compariranno qui.', 'Prenota'])
            ->assertDontSee('rounded-panel bg-surface', escape: false);
    });

    it('uses the slot as the description', function (): void {
        $this->blade('<x-ui.empty-state variant="search">Prova <a href="/animali">il catalogo</a>.</x-ui.empty-state>')
            ->assertSee('<a href="/animali">il catalogo</a>', escape: false)
            ->assertDontSee('Nessun animale corrisponde', escape: false)
            ->assertSee('rounded-panel bg-surface', escape: false);
    });

    it('translates the variant texts', function (): void {
        app()->setLocale('en');

        $this->blade('<x-ui.empty-state variant="favorites" />')
            ->assertSee('No favorites yet')
            ->assertSee('Tap the heart');
    });
});

describe('avatar', function (): void {
    it('names the initials fallback as an image', function (): void {
        $this->blade('<x-ui.avatar name="Maria Rossi" />')
            ->assertSee('role="img"', escape: false)
            ->assertSee('aria-label="Maria Rossi"', escape: false)
            ->assertSee('>MR</span>', escape: false)
            ->assertSee('size-11 text-small rounded-full', escape: false);
    });

    it('takes the initials from the first and last word, multibyte safe', function (string $name, string $initials): void {
        $this->blade('<x-ui.avatar :name="$name" />', ['name' => $name])->assertSee('>'.$initials.'</span>', escape: false);
    })->with([
        ['Luna', 'L'],
        ['Rifugio Il Faro', 'RF'],
        ['élodie   özil', 'ÉÖ'],
    ]);

    it('picks the same block for the same name', function (): void {
        $colors = ['sun', 'peach', 'mint', 'leaf'];
        $expected = $colors[crc32('maria rossi') % 4];

        $classes = $expected === 'sun' ? 'bg-highlight text-on-highlight' : 'bg-block-'.$expected.' text-on-block-'.$expected;

        $this->blade('<x-ui.avatar name="Maria Rossi" />')
            ->assertSee('data-avatar="'.$expected.'"', escape: false)
            ->assertSee($classes, escape: false);

        $this->blade('<x-ui.avatar name="  maria rossi " />')->assertSee('data-avatar="'.$expected.'"', escape: false);
    });

    it('forces a block colour', function (): void {
        $this->blade('<x-ui.avatar name="Maria Rossi" color="peach" />')->assertSee('bg-block-peach text-on-block-peach', escape: false);
    });

    it('keeps the sun tone yellow in dark mode, where block-sun matches the surface', function (): void {
        // block-sun in dark è navy-800 come bg-surface: le iniziali sparirebbero su una card.
        $this->blade('<x-ui.avatar name="Amici a Quattro Zampe" color="sun" shape="square" />')
            ->assertSee('data-avatar="sun"', escape: false)
            ->assertSee('bg-highlight text-on-highlight', escape: false)
            ->assertDontSee('bg-block-sun', escape: false);
    });

    it('renders a photo with the name as alt and fixed dimensions', function (): void {
        $this->blade('<x-ui.avatar name="Maria Rossi" src="/foto.jpg" size="xl" />')
            ->assertSee('src="/foto.jpg"', escape: false)
            ->assertSee('alt="Maria Rossi"', escape: false)
            ->assertSee('width="80"', escape: false)
            ->assertSee('height="80"', escape: false)
            ->assertSee('x-on:error="fail()"', escape: false)
            ->assertDontSee('role="img"', escape: false);
    });

    it('is decorative when the name is written next to it', function (): void {
        $this->blade('<x-ui.avatar name="Maria Rossi" src="/foto.jpg" decorative />')
            ->assertSee('alt=""', escape: false)
            ->assertDontSee('aria-label="', escape: false)
            ->assertDontSee('role="img"', escape: false);

        $this->blade('<x-ui.avatar name="Maria Rossi" decorative />')
            ->assertSee('aria-hidden="true"', escape: false)
            ->assertDontSee('role="img"', escape: false);
    });

    it('renders organization logos as rounded squares', function (string $size, string $radius): void {
        $this->blade('<x-ui.avatar name="Rifugio Il Faro" shape="square" :size="$size" />', ['size' => $size])
            ->assertSee($radius, escape: false)
            ->assertDontSee('rounded-full', escape: false);
    })->with([
        ['sm', 'rounded-icon '],
        ['lg', 'rounded-icon-lg'],
    ]);
});

describe('avatar group', function (): void {
    $people = [['name' => 'Maria Rossi', 'src' => '/maria.jpg'], ['name' => 'Luca Bianchi'], ['name' => 'Sofia Ricci'], ['name' => 'Marco Conti']];

    it('renders a labelled list and collapses the rest into +N', function () use ($people): void {
        $this->blade('<x-ui.avatar.group :people="$people" :max="2" label="Volontari" />', ['people' => $people])
            ->assertSee('role="list"', escape: false)
            ->assertSee('aria-label="Volontari"', escape: false)
            ->assertSee('alt="Maria Rossi"', escape: false)
            ->assertSee('aria-label="Luca Bianchi"', escape: false)
            ->assertDontSee('Sofia Ricci')
            ->assertSee('data-avatar-more="2"', escape: false)
            ->assertSee('<span aria-hidden="true">+2</span>', escape: false)
            ->assertSee('e altri 2')
            ->assertDontSee('persone')
            ->assertSee('ring-2 ring-canvas', escape: false)
            ->assertSee('-space-x-2', escape: false)
            ->assertSee('bg-inverse', escape: false);
    });

    it('counts the more bubble from the real total', function () use ($people): void {
        $this->blade('<x-ui.avatar.group :people="$people" :total="5" ring="surface" size="sm" />', ['people' => $people])
            ->assertSee('data-avatar-more="1"', escape: false)
            ->assertSee('e un altro')
            ->assertSee('ring-2 ring-surface', escape: false)
            ->assertSee('-space-x-1', escape: false);
    });

    it('shows no bubble when everyone fits', function () use ($people): void {
        $this->blade('<x-ui.avatar.group :people="$people" />', ['people' => $people])->assertDontSee('data-avatar-more', escape: false);
    });

    it('translates the more bubble', function () use ($people): void {
        app()->setLocale('en');

        $this->blade('<x-ui.avatar.group :people="$people" :max="1" />', ['people' => $people])->assertSee('and 3 more')->assertDontSee('people');
    });
});

describe('styleguide page', function (): void {
    beforeEach(function (): void {
        app()->detectEnvironment(static fn (): string => 'local');
    });

    it('documents every component of the group', function (): void {
        $this->get(route('styleguide.group', 'content'))
            ->assertOk()
            ->assertSeeInOrder(['id="tabs"', 'id="accordion"', 'id="skeleton"', 'id="empty-state"', 'id="avatar"'], escape: false)
            ->assertSee('Contenuti');
    });

    it('is translated in English', function (): void {
        $this->withSession(['locale' => 'en'])
            ->get(route('styleguide.group', 'content'))
            ->assertOk()
            ->assertSee('How much does adopting cost?')
            ->assertSee('No results');
    });
});
