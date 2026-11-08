<?php

declare(strict_types=1);

/*
 | Componenti x-ui del gruppo "domain": animal-card (+ favorite, cta, skeleton),
 | organization-card, stat, section-heading, cta-band.
 | Test di rendering (markup, ARIA, varianti, traduzione EN). Comportamento nel browser:
 | tests/Browser/Components/DomainComponentsTest.php.
 */

describe('animal card', function (): void {
    it('renders a single extended link on the name, inside a level 3 heading by default', function (): void {
        $view = $this->blade('<x-ui.animal-card name="Fido" href="/animali/fido" />');

        $view->assertSee('<article', escape: false)
            ->assertSee('<h3 class="text-h4">', escape: false)
            ->assertSee('href="/animali/fido"', escape: false)
            ->assertSee('data-card-link', escape: false)
            ->assertSee('after:absolute after:inset-0', escape: false)
            ->assertSee('min-h-11', escape: false)
            ->assertSee('Fido');

        expect(preg_match_all('/<a\s/', (string) $view))->toBe(1)
            ->and((string) $view)->not->toContain('aria-label=')
            ->not->toContain('<button');
    });

    it('lets the page choose the heading level', function (int|string $level, string $tag): void {
        $this->blade('<x-ui.animal-card name="Fido" href="#" :heading-level="$level" />', ['level' => $level])
            ->assertSee('<'.$tag.' class="text-h4">', escape: false)
            ->assertSee('</'.$tag.'>', escape: false);
    })->with([
        [2, 'h2'],
        ['4', 'h4'],
        [1, 'h2'],
        [9, 'h6'],
    ]);

    it('shows the photo lazily with stable proportions and a translated alt', function (): void {
        $this->blade('<x-ui.animal-card name="Luna" href="#" photo="/img/luna.jpg" />')
            ->assertSee('src="/img/luna.jpg"', escape: false)
            ->assertSee('alt="Foto di Luna"', escape: false)
            ->assertSee('width="400"', escape: false)
            ->assertSee('height="500"', escape: false)
            ->assertSee('loading="lazy"', escape: false)
            ->assertSee('object-cover', escape: false)
            ->assertSee('aspect-4/5', escape: false)
            ->assertDontSee('data-animal-card-fallback', escape: false);
    });

    it('falls back to a decorative paw without a photo', function (): void {
        $this->blade('<x-ui.animal-card name="Birba" href="#" />')
            ->assertSee('data-animal-card-fallback', escape: false)
            ->assertSee('data-icon="paw-print"', escape: false)
            ->assertSee('aria-hidden="true"', escape: false)
            ->assertDontSee('<img', escape: false);
    });

    it('puts the photo on the chosen colour block', function (string $tone, string $classes): void {
        $this->blade('<x-ui.animal-card name="Fido" href="#" :tone="$tone" />', ['tone' => $tone])
            ->assertSee('data-animal-card="'.$tone.'"', escape: false)
            ->assertSee($classes, escape: false);
    })->with([
        ['sun', 'bg-block-sun text-on-block-sun dark:bg-surface-raised'],
        ['peach', 'bg-block-peach text-on-block-peach'],
        ['mint', 'bg-block-mint text-on-block-mint'],
    ]);

    it('ignores an unknown tone', function (): void {
        $this->blade('<x-ui.animal-card name="Fido" href="#" tone="purple" />')
            ->assertSee('data-animal-card="sun"', escape: false);
    });

    it('lists species, sex, age and size as badges, with the location', function (): void {
        $this->blade('<x-ui.animal-card name="Fido" href="#" species="Cane" sex="Maschio" age="3 anni" size="Media" location="Bergamo, Lombardia" />')
            ->assertSee('<ul role="list"', escape: false)
            ->assertSeeInOrder(['Cane', 'Maschio', '3 anni', 'Taglia media'])
            ->assertSee('data-icon="map-pin"', escape: false)
            ->assertSee('Si trova a')
            ->assertSee('Bergamo, Lombardia');
    });

    it('omits empty badges and location', function (): void {
        $this->blade('<x-ui.animal-card name="Zeus" href="#" />')
            ->assertDontSee('<ul', escape: false)
            ->assertDontSee('data-icon="map-pin"', escape: false)
            ->assertDontSee('data-animal-card-featured', escape: false);
    });

    it('marks a featured animal with text and an icon, not colour alone', function (): void {
        $this->blade('<x-ui.animal-card name="Fido" href="#" featured />')
            ->assertSee('data-animal-card-featured', escape: false)
            ->assertSee('data-icon="star"', escape: false)
            ->assertSee('In evidenza');
    });

    it('renders the favorite slot above the link, as a sibling', function (): void {
        $html = (string) $this->blade(<<<'BLADE'
            <x-ui.animal-card name="Fido" href="/animali/fido">
                <x-slot:favorite><x-ui.animal-card.favorite name="Fido" /></x-slot:favorite>
            </x-ui.animal-card>
            BLADE);

        expect($html)->toContain('data-animal-card-favorite')
            ->toContain('z-10')
            ->and(strpos($html, 'data-favorite'))->toBeLessThan(strpos($html, 'href="/animali/fido"'))
            ->and(preg_match('/<a [^>]*>.*<button/s', $html))->toBe(0);
    });

    it('translates its texts in English', function (): void {
        app()->setLocale('en');

        $this->blade('<x-ui.animal-card name="Luna" href="#" photo="/luna.jpg" size="Small" location="Torino" featured />')
            ->assertSee('alt="Photo of Luna"', escape: false)
            ->assertSee('Size: small')
            ->assertSee('Located in')
            ->assertSee('Featured');
    });
});

describe('animal card favorite', function (): void {
    it('is a 44 px toggle named after the animal', function (): void {
        $this->blade('<x-ui.animal-card.favorite name="Fido" wire:click="toggle" />')
            ->assertSee('<button', escape: false)
            ->assertSee('type="button"', escape: false)
            ->assertSee('aria-pressed="false"', escape: false)
            ->assertSee('aria-label="Salva Fido nei preferiti"', escape: false)
            ->assertSee('size-11', escape: false)
            ->assertSee('wire:click="toggle"', escape: false)
            ->assertSee('data-icon="heart"', escape: false);
    });

    it('keeps its name when pressed and fills the heart', function (): void {
        $this->blade('<x-ui.animal-card.favorite name="Luna" pressed />')
            ->assertSee('aria-pressed="true"', escape: false)
            ->assertSee('aria-label="Salva Luna nei preferiti"', escape: false)
            ->assertSee('group-aria-pressed/favorite:fill-current', escape: false);
    });

    it('accepts a custom label and translates the default one', function (): void {
        $this->blade('<x-ui.animal-card.favorite name="Fido" label="Salva Fido" />')
            ->assertSee('aria-label="Salva Fido"', escape: false);

        app()->setLocale('en');

        $this->blade('<x-ui.animal-card.favorite name="Fido" />')
            ->assertSee('aria-label="Save Fido to favorites"', escape: false);
        $this->blade('<x-ui.animal-card.favorite name="Fido" pressed />')
            ->assertSee('aria-label="Save Fido to favorites"', escape: false);
    });
});

describe('animal card cta', function (): void {
    it('renders the dark last card with the yellow catalog button', function (): void {
        $html = (string) $this->blade('<x-ui.animal-card.cta href="/animali" />');

        expect($html)->toContain('data-animal-card-cta')
            ->toContain('bg-inverse')
            ->toContain('text-on-inverse')
            ->toContain('<h3 class="text-h4">Non hai ancora trovato il tuo amico?</h3>')
            ->toContain('data-variant="highlight"')
            ->toContain('href="/animali"')
            ->toContain('Sfoglia il catalogo')
            ->toContain('Nel catalogo ci sono tanti altri animali')
            ->and(preg_match_all('/<a\s/', $html))->toBe(1);
    });

    it('pluralises the remaining animals', function (int $count, string $text): void {
        $this->blade('<x-ui.animal-card.cta href="#" :count="$count" heading-level="2" />', ['count' => $count])
            ->assertSee('<h2 class="text-h4">', escape: false)
            ->assertSee($text);
    })->with([
        [0, 'Ogni settimana arrivano nuovi animali'],
        [1, 'c’è un altro animale'],
        [12, 'ci sono altri 12 animali'],
    ]);

    it('formats the count in the current locale', function (): void {
        app()->setLocale('en');

        $this->blade('<x-ui.animal-card.cta href="#" :count="1240" />')
            ->assertSee('1,240 more animals in the catalog')
            ->assertSee('Browse the catalog')
            ->assertSee('Still looking for your new friend?');
    });

    it('accepts custom texts', function (): void {
        $this->blade('<x-ui.animal-card.cta href="#" title="Altri amici" action="Vedi tutti">Testo libero</x-ui.animal-card.cta>')
            ->assertSee('Altri amici')
            ->assertSee('Vedi tutti')
            ->assertSee('Testo libero')
            ->assertDontSee('Sfoglia il catalogo');
    });
});

describe('animal card skeleton', function (): void {
    it('announces the loading once with a translated status', function (): void {
        $this->blade('<x-ui.animal-card.skeleton />')
            ->assertSee('role="status"', escape: false)
            ->assertDontSee('aria-busy', escape: false)
            ->assertSee('Caricamento degli animali…')
            ->assertSee('data-animal-card-skeleton', escape: false);

        app()->setLocale('en');

        $this->blade('<x-ui.animal-card.skeleton />')->assertSee('Loading animals…');
    });

    it('renders only the hidden shape when bare', function (): void {
        $this->blade('<x-ui.animal-card.skeleton bare class="w-64" />')
            ->assertSee('aria-hidden="true"', escape: false)
            ->assertSee('w-64', escape: false)
            ->assertDontSee('role="status"', escape: false);
    });
});

describe('organization card', function (): void {
    it('renders the extended link, location, type, verified badge and count', function (): void {
        $html = (string) $this->blade('<x-ui.organization-card name="Rifugio Il Faro" href="/enti/faro" city="Genova" region="Liguria" type="Rifugio" verified :available="12" />');

        expect($html)->toContain('data-organization-card')
            ->toContain('<h3 class="text-h5 font-extrabold">')
            ->toContain('href="/enti/faro"')
            ->toContain('after:absolute after:inset-0')
            ->toContain('Genova, Liguria')
            ->toContain('Sede')
            ->toContain('Rifugio')
            ->toContain('data-organization-verified')
            ->toContain('data-icon="badge-check"')
            ->toContain('Verificato')
            ->toContain('12 animali disponibili')
            ->and(preg_match_all('/<a\s/', $html))->toBe(1);
    });

    it('shows initials when there is no logo, without naming them twice', function (): void {
        $this->blade('<x-ui.organization-card name="Rifugio Il Faro" href="#" />')
            ->assertSee('RF')
            ->assertDontSee('role="img"', escape: false)
            ->assertDontSee('data-organization-verified', escape: false)
            ->assertDontSee('data-organization-available', escape: false);
    });

    it('shows the logo as a decorative image', function (): void {
        $this->blade('<x-ui.organization-card name="Rifugio Il Faro" href="#" logo="/logo.png" />')
            ->assertSee('src="/logo.png"', escape: false)
            ->assertSee('alt=""', escape: false);
    });

    it('pluralises the available animals', function (int $count, string $text): void {
        $this->blade('<x-ui.organization-card name="Ente" href="#" :available="$count" />', ['count' => $count])
            ->assertSee($text);
    })->with([
        [0, 'Nessun animale disponibile'],
        [1, '1 animale disponibile'],
        [5, '5 animali disponibili'],
    ]);

    it('lets the page choose the heading level and translates in English', function (): void {
        app()->setLocale('en');

        $this->blade('<x-ui.organization-card name="Ente" href="#" city="Bari" verified :available="1240" heading-level="2" />')
            ->assertSee('<h2 class="text-h5 font-extrabold">', escape: false)
            ->assertSee('Based in')
            ->assertSee('Verified')
            ->assertSee('1,240 animals available');
    });
});

describe('stat', function (): void {
    it('renders the number and its label in the same paragraph, leaf by default', function (): void {
        $this->blade('<x-ui.stat :value="86" label="enti in rete" />')
            ->assertSee('data-stat="leaf"', escape: false)
            ->assertSee('bg-block-leaf text-on-block-leaf', escape: false)
            ->assertSee('text-h2', escape: false)
            ->assertSeeInOrder(['<p', '86', 'enti in rete', '</p>'], escape: false);
    });

    it('applies every tone with its on-* text', function (string $tone, string $classes): void {
        $this->blade('<x-ui.stat value="1" label="x" :tone="$tone" />', ['tone' => $tone])
            ->assertSee('data-stat="'.$tone.'"', escape: false)
            ->assertSee($classes, escape: false);
    })->with([
        ['surface', 'bg-surface text-fg'],
        ['sun', 'bg-block-sun text-on-block-sun'],
        ['peach', 'bg-block-peach text-on-block-peach'],
        ['mint', 'bg-block-mint text-on-block-mint'],
        ['inverse', 'bg-inverse text-on-inverse'],
    ]);

    it('falls back to leaf for an unknown tone', function (): void {
        $this->blade('<x-ui.stat value="1" label="x" tone="purple" />')
            ->assertSee('data-stat="leaf"', escape: false);
    });

    it('formats numbers in the current locale and keeps ready-made text', function (): void {
        app()->setLocale('en');

        $this->blade('<x-ui.stat :value="3200" label="volunteers" />')->assertSee('3,200');
        $this->blade('<x-ui.stat :value="4.8" label="rating" />')->assertSee('4.8');
        $this->blade('<x-ui.stat value="98%" label="success" size="md" />')
            ->assertSee('98%')
            ->assertSee('text-h3', escape: false);
    });

    it('uses the Italian number format', function (): void {
        $this->blade('<x-ui.stat :value="3200" label="volontari" />')->assertSee('3.200');
        $this->blade('<x-ui.stat :value="4.8" label="voto" />')->assertSee('4,8');
    });

    it('shows an optional decorative icon', function (): void {
        $this->blade('<x-ui.stat :value="1240" label="adottati" icon="heart" />')
            ->assertSee('data-icon="heart"', escape: false)
            ->assertSee('aria-hidden="true"', escape: false);
    });
});

describe('section heading', function (): void {
    it('falls back to a hashed id when the title has no latin letters', function (): void {
        $this->blade('<x-ui.section-heading title="猫" />')
            ->assertSee('id="heading-'.substr(md5('猫'), 0, 8).'"', escape: false);
    });

    it('renders eyebrow, h2 title with id, description and action', function (): void {
        $this->blade(<<<'BLADE'
            <x-ui.section-heading id="new-arrivals" title="Ti aspettano a casa" eyebrow="Nuovi arrivi" description="Gli ultimi arrivati.">
                <x-slot:action><a href="/animali">Vedi tutti</a></x-slot:action>
            </x-ui.section-heading>
            BLADE)
            ->assertSee('data-section-eyebrow="badge"', escape: false)
            ->assertSee('<h2 id="new-arrivals" class="text-fg text-h2">Ti aspettano a casa</h2>', escape: false)
            ->assertSee('text-lead text-fg-muted', escape: false)
            ->assertSee('data-section-action', escape: false)
            ->assertSee('md:flex-row', escape: false)
            ->assertSeeInOrder(['Nuovi arrivi', 'Ti aspettano a casa', 'Gli ultimi arrivati.', 'Vedi tutti']);
    });

    it('derives the id from the title and separates level from size', function (): void {
        $this->blade('<x-ui.section-heading title="Quattro passi" heading-level="3" size="h3" eyebrow="Come funziona" eyebrow-variant="text" />')
            ->assertSee('<h3 id="heading-quattro-passi" class="text-fg text-h3">', escape: false)
            ->assertSee('data-section-eyebrow="text"', escape: false)
            ->assertDontSee('data-section-action', escape: false);
    });

    it('takes the description from the slot', function (): void {
        $this->blade('<x-ui.section-heading title="Titolo">Descrizione nello slot</x-ui.section-heading>')
            ->assertSee('Descrizione nello slot');
    });
});

describe('cta band', function (): void {
    it('renders a labelled leaf section with text and actions', function (): void {
        $this->blade(<<<'BLADE'
            <x-ui.cta-band title="Aiutaci" icon="hand-heart">
                Con 10 € copri una settimana di cure.
                <x-slot:actions><x-ui.button variant="highlight" href="/dona">Dona ora</x-ui.button></x-slot:actions>
            </x-ui.cta-band>
            BLADE)
            ->assertSee('<section', escape: false)
            ->assertSee('aria-labelledby="cta-aiutaci"', escape: false)
            ->assertSee('<h2 id="cta-aiutaci" class="text-h2">Aiutaci</h2>', escape: false)
            ->assertSee('data-cta-band="leaf"', escape: false)
            ->assertSee('rounded-panel p-panel', escape: false)
            ->assertSee('bg-block-leaf text-on-block-leaf', escape: false)
            ->assertSee('bg-highlight text-on-highlight', escape: false)
            ->assertSee('data-cta-actions', escape: false)
            ->assertSee('Dona ora');
    });

    it('applies every tone', function (string $tone, string $classes): void {
        $this->blade('<x-ui.cta-band title="T" :tone="$tone" heading-level="3" size="h3" id="band" />', ['tone' => $tone])
            ->assertSee('data-cta-band="'.$tone.'"', escape: false)
            ->assertSee($classes, escape: false)
            ->assertSee('<h3 id="band" class="text-h3">', escape: false);
    })->with([
        ['inverse', 'bg-inverse text-on-inverse'],
        ['sun', 'bg-block-sun text-on-block-sun'],
        ['peach', 'bg-block-peach text-on-block-peach'],
        ['mint', 'bg-block-mint text-on-block-mint'],
    ]);

    it('never puts yellow text on the green band', function (): void {
        $this->blade('<x-ui.cta-band title="T" icon="heart" />')
            ->assertDontSee('text-highlight', escape: false);
    });
});

describe('styleguide page', function (): void {
    beforeEach(function (): void {
        app()->detectEnvironment(static fn (): string => 'local');
    });

    it('documents every component of the group', function (): void {
        $this->get(route('styleguide.group', 'domain'))
            ->assertOk()
            ->assertSeeInOrder(['id="animal-card"', 'id="organization-card"', 'id="stat"', 'id="section-heading"', 'id="cta-band"'], escape: false)
            ->assertSee('Salva Fido nei preferiti')
            ->assertSee('Salva Luna nei preferiti')
            ->assertSee('Sfoglia il catalogo');
    });

    it('is translated in English', function (): void {
        $this->withSession(['locale' => 'en'])
            ->get(route('styleguide.group', 'domain'))
            ->assertOk()
            ->assertSee('Save Fido to favorites')
            ->assertSee('Browse the catalog')
            ->assertSee('Help us find a home for everyone');
    });
});
