<?php

declare(strict_types=1);

use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\App;

/*
 | Componenti x-ui della shell pubblica (UI-3): nav-link e language-switcher.
 */

describe('x-ui.nav-link', function (): void {
    it('renders an SPA link with hover prefetch', function (): void {
        $this->blade('<x-ui.nav-link href="/animali">Catalogo</x-ui.nav-link>')
            ->assertSee('href="/animali"', escape: false)
            ->assertSee('wire:navigate.hover', escape: false)
            ->assertSee('min-h-11', escape: false)
            ->assertDontSee('aria-current', escape: false)
            ->assertSee('text-fg hover:bg-surface-raised', escape: false);
    });

    it('marks the current page or section with aria-current and the filled style', function (string $current): void {
        $this->blade('<x-ui.nav-link href="/animali" :current="$current">Catalogo</x-ui.nav-link>', ['current' => $current])
            ->assertSee('aria-current="'.$current.'"', escape: false)
            ->assertSee('bg-primary text-on-primary hover:bg-primary-hover', escape: false);
    })->with(['page', 'true']);

    it('can opt out of SPA navigation for links outside the public site', function (): void {
        $this->blade('<x-ui.nav-link href="/account" :navigate="false">Area</x-ui.nav-link>')
            ->assertDontSee('wire:navigate', escape: false);
    });

    it('has a full-width block layout with an icon for the mobile menu', function (): void {
        $this->blade('<x-ui.nav-link href="/enti" layout="block" icon="building-2">Enti</x-ui.nav-link>')
            ->assertSee('min-h-12 w-full', escape: false)
            ->assertSee('hover:bg-surface', escape: false)
            ->assertSee('data-icon="building-2"', escape: false);
    });
});

describe('x-ui.language-switcher', function (): void {
    it('renders one POST form per supported locale, without JavaScript', function (): void {
        $html = (string) $this->blade('<x-ui.language-switcher />');

        expect($html)
            ->toContain('role="group"')
            ->toContain('aria-label="Lingua"')
            ->toContain('method="POST" action="'.route('locale.update', ['locale' => 'it']).'"')
            ->toContain('method="POST" action="'.route('locale.update', ['locale' => 'en']).'"')
            ->not->toContain('x-on:')
            ->and(substr_count($html, 'type="submit"'))->toBe(2)
            ->and(substr_count($html, 'name="_token"'))->toBe(2);
    });

    it('presses the active locale and labels each option in its own language', function (string $locale): void {
        App::setLocale($locale);

        $html = (string) $this->blade('<x-ui.language-switcher />');

        expect($html)
            ->toMatch('/lang="'.$locale.'"\s+aria-pressed="true"/')
            ->toMatch('/lang="it"[^>]*>\s*Italiano\s*</')
            ->toMatch('/lang="en"[^>]*>\s*English\s*</')
            ->and(substr_count($html, 'aria-pressed="true"'))->toBe(1);
    })->with(['it', 'en']);

    it('shows the locale codes in compact mode, keeping the endonym in the accessible name', function (): void {
        $this->blade('<x-ui.language-switcher compact />')
            ->assertSee('IT<span class="sr-only"> Italiano</span>', escape: false)
            ->assertSee('EN<span class="sr-only"> English</span>', escape: false);
    });

    it('translates the group label', function (): void {
        App::setLocale('en');

        $this->blade('<x-ui.language-switcher />')->assertSee('aria-label="Language"', escape: false);
    });

    it('switches the locale through the existing route', function (): void {
        $this->from(route('catalog'))
            ->post(route('locale.update', ['locale' => 'en']))
            ->assertRedirect(route('catalog'))
            ->assertSessionHas(SetLocale::SESSION_KEY, 'en');
    });
});
