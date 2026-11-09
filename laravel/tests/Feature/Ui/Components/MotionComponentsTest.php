<?php

declare(strict_types=1);

use App\Support\Media\GalleryImage;
use App\Support\Media\Picture;
use App\Support\Styleguide\StyleguideGroups;

/*
 | Motion dei componenti x-ui (UI-7): reveal allo scroll (x-reveal) attivo di default e
 | spegnibile, mai sull'immagine LCP; nome della View Transition della foto su card e
 | galleria; pop dell'icona dei toast; pagina «motion» della styleguide.
 | Comportamento nel browser: tests/Browser/Components/MotionComponentsTest.php.
 */

/** Il tag di apertura dell'elemento che contiene `$marker` (il primo `<` prima del marcatore). */
function openingTagWith(string $html, string $marker): string
{
    $position = strpos($html, $marker);
    expect($position)->not->toBeFalse();

    $start = (int) strrpos(substr($html, 0, (int) $position), '<');

    return substr($html, $start, (int) strpos($html, '>', (int) $position) - $start + 1);
}

describe('reveal', function (): void {
    it('reveals the animal card by default, but never the priority (LCP) card', function (): void {
        $default = (string) $this->blade('<x-ui.animal-card name="Fido" href="/animali/1" />');
        $priority = (string) $this->blade('<x-ui.animal-card name="Fido" href="/animali/1" priority />');
        $off = (string) $this->blade('<x-ui.animal-card name="Fido" href="/animali/1" :reveal="false" />');

        expect(openingTagWith($default, 'data-animal-card='))->toContain('x-reveal')
            ->and(openingTagWith($priority, 'data-animal-card='))->not->toContain('x-reveal')
            ->and(openingTagWith($off, 'data-animal-card='))->not->toContain('x-reveal');
    });

    it('reveals the shared blocks by default and lets the page turn it off', function (string $component, string $marker): void {
        expect(openingTagWith((string) $this->blade($component), $marker))->toContain('x-reveal')
            ->and(openingTagWith((string) $this->blade(str_replace(' />', ' :reveal="false" />', $component)), $marker))->not->toContain('x-reveal');
    })->with([
        'section heading' => ['<x-ui.section-heading title="Animali" />', 'data-section-heading'],
        'stat' => ['<x-ui.stat :value="3" label="animali" />', 'data-stat='],
        'cta band' => ['<x-ui.cta-band title="Sostieni" />', 'data-cta-band='],
        'animal card cta' => ['<x-ui.animal-card.cta href="/animali" />', 'data-animal-card-cta'],
    ]);

    it('never reveals the empty state, which follows a user action', function (): void {
        expect((string) $this->blade('<x-ui.empty-state title="Nessun risultato" />'))->not->toContain('x-reveal');
    });
});

describe('photo transition', function (): void {
    it('names the card photo for wire:navigate and links it to the card destination', function (): void {
        $html = (string) $this->blade('<x-ui.animal-card name="Fido" href="/animali/7" transition-name="animal-photo-7" />');
        $photo = openingTagWith($html, 'data-animal-card-photo');

        expect($photo)
            ->toContain('wire:transition.navigate="animal-photo-7"')
            ->toContain('data-photo-transition="/animali/7"')
            ->and(substr_count($html, 'wire:transition.navigate'))->toBe(1);
    });

    it('leaves the card photo unnamed without a transition name', function (): void {
        expect((string) $this->blade('<x-ui.animal-card name="Fido" href="/animali/7" />'))
            ->not->toContain('wire:transition.navigate')
            ->not->toContain('data-photo-transition');
    });

    it('frames the photo in the tone of the card', function (): void {
        $html = (string) $this->blade('<x-ui.animal-card name="Fido" href="/animali/7" tone="peach" photo="/img/fido.jpg" />');

        expect(openingTagWith($html, 'data-animal-card-media'))->toContain('bg-block-peach')->toContain('p-2')
            ->and(openingTagWith($html, 'data-animal-card-photo'))->toContain('overflow-hidden')->toContain('rounded-icon-lg');
    });

    it('names the gallery viewport as the subject of the page', function (): void {
        $image = new GalleryImage(
            slide: Picture::fromUrl(src: '/img/a.jpg', width: 960, height: 640),
            thumb: Picture::fromUrl(src: '/img/a-thumb.jpg', width: 96, height: 64),
            alt: 'Foto di Luna',
        );

        $named = (string) $this->blade('<x-ui.gallery :images="$images" name="Luna" transition-name="animal-photo-3" />', ['images' => [$image]]);
        $plain = (string) $this->blade('<x-ui.gallery :images="$images" name="Luna" />', ['images' => [$image]]);

        expect(openingTagWith($named, 'data-gallery-viewport'))
            ->toContain('wire:transition.navigate="animal-photo-3"')
            ->toMatch('/data-photo-transition(?!=)/')
            ->and($plain)->not->toContain('wire:transition.navigate');
    });
});

it('pops the toast icon in with the motion tokens, only in the toaster', function (): void {
    $static = (string) $this->blade('<x-ui.toast type="success" title="Salvato" />');
    $toaster = (string) $this->blade('<x-ui.toaster />');

    expect(openingTagWith($toaster, 'data-toast-icon="success"'))
        ->toContain('starting:scale-50')
        ->toContain('ease-pop')
        ->toContain('duration-(--duration-slow)')
        // Niente ritardo: l'entrata resta entro 300 ms.
        ->not->toContain('delay-')
        ->and(openingTagWith($static, 'data-toast-icon="success"'))->not->toContain('starting:scale-50');
});

it('marks the heart of a guest, whose click leads to the login instead of saving', function (): void {
    expect((string) $this->blade('<x-ui.animal-card.favorite name="Fido" guest />'))->toContain('data-favorite-guest')
        ->and((string) $this->blade('<x-ui.animal-card.favorite name="Fido" />'))->not->toContain('data-favorite-guest');
});

it('switches the scroll reveal off from the configuration', function (): void {
    app()->detectEnvironment(static fn (): string => 'local');

    $this->get(route('styleguide.group', 'motion'))->assertOk()->assertDontSee('data-reveal="off"', false);

    config(['arkvel.reveal' => false]);

    $this->get(route('styleguide.group', 'motion'))->assertOk()->assertSee('data-reveal="off"', false);
});

describe('styleguide', function (): void {
    beforeEach(function (): void {
        app()->detectEnvironment(static fn (): string => 'local');
    });

    it('documents motion in its own styleguide page, after media', function (): void {
        expect(StyleguideGroups::ALL)->toContain('motion')
            ->and(array_search('motion', StyleguideGroups::ALL, true))->toBe(array_search('media', StyleguideGroups::ALL, true) + 1);

        $this->get(route('styleguide.group', 'motion'))
            ->assertOk()
            ->assertSee(__('styleguide.groups.motion.title'))
            ->assertSee('data-demo="favorite"', false)
            ->assertSee('data-demo="toast"', false)
            ->assertSee('data-demo="reveal-grid"', false)
            ->assertSee('data-toaster', false);
    });

    it('translates the motion page in English', function (): void {
        app()->setLocale('en');

        expect(__('styleguide_motion.reveal.title'))->toBe('Reveal on scroll')
            ->and(__('styleguide.groups.motion.title'))->toBe('Motion');
    });
});
