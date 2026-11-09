<?php

declare(strict_types=1);

use App\Livewire\Home;
use App\Models\Animal;
use App\Models\Organization;
use App\Support\Site\AnimalPhotoTransition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
 | Motion delle pagine pubbliche (UI-7): la foto di ogni animale ha il nome di View
 | Transition della scheda, unico nella pagina (due elementi con lo stesso nome annullano
 | la transizione); la hero della home resta ferma (niente reveal sull'LCP); le sezioni
 | della scheda compaiono allo scroll. Nel browser: tests/Browser/MotionTest.php.
 */

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('public');
    Queue::fake();
    $this->organization = Organization::factory()->verified()->create();
});

/**
 * I nomi delle View Transition della pagina (attributi wire:transition.navigate con valore).
 *
 * @return list<string>
 */
function photoTransitionNames(string $html): array
{
    preg_match_all('/wire:transition\.navigate="([^"]+)"/', $html, $matches);

    return $matches[1];
}

function motionAnimal(Organization $organization, array $attributes = []): Animal
{
    return Animal::factory()->available()->forOrganization($organization)->create(['featured' => false, ...$attributes]);
}

it('names every animal photo of the home once, the hero photo included', function (): void {
    $luna = motionAnimal($this->organization, ['name' => 'Luna', 'featured' => true]);
    $luna->addMedia(UploadedFile::fake()->image('luna.jpg', 900, 600))->toMediaCollection('photos');
    $others = collect(['Argo', 'Micia', 'Fiocco'])->map(fn (string $name): Animal => motionAnimal($this->organization, ['name' => $name]));

    $html = Livewire::test(Home::class)->html();
    $names = photoTransitionNames($html);
    $hero = substr($html, (int) strpos($html, 'data-home-spotlight'), (int) strpos($html, 'data-home-stats') - (int) strpos($html, 'data-home-spotlight'));

    expect($names)->toHaveCount(4)
        ->and(array_unique($names))->toHaveCount(4)
        ->and($names)->toContain(AnimalPhotoTransition::name($luna))
        ->and($names)->toContain(...$others->map(fn (Animal $animal): string => AnimalPhotoTransition::name($animal))->all())
        ->and($hero)->toContain('wire:transition.navigate="'.AnimalPhotoTransition::name($luna).'"')
        ->toContain('data-photo-transition="'.route('animals.show', $luna).'"');
});

it('keeps the home hero still and reveals what follows', function (): void {
    motionAnimal($this->organization, ['name' => 'Argo']);

    $html = Livewire::test(Home::class)->html();
    $hero = substr($html, (int) strpos($html, 'data-home-hero'), (int) strpos($html, 'data-home-showcase') - (int) strpos($html, 'data-home-hero'));
    $rest = substr($html, (int) strpos($html, 'data-home-showcase'));

    expect($hero)->not->toContain('x-reveal')
        ->and(substr_count($rest, 'x-reveal'))->toBeGreaterThanOrEqual(8);
});

it('switches the scroll reveal of the public pages off from the configuration', function (): void {
    $this->get(route('home'))->assertOk()->assertDontSee('data-reveal="off"', false);

    config(['arkvel.reveal' => false]);

    $this->get(route('home'))->assertOk()->assertSee('data-reveal="off"', false);
});

it('reveals the title and the steps of «how it works», not the panel around them', function (): void {
    $html = Livewire::test(Home::class)->html();
    $how = substr($html, (int) strpos($html, 'data-home-how'), (int) strpos($html, 'data-home-stories') - (int) strpos($html, 'data-home-how'));

    // Un reveal per il titolo e uno per ognuno dei quattro passi, nessuno annidato.
    expect(substr_count($how, 'x-reveal'))->toBe(5)
        ->and($how)->toMatch('/data-section-heading\s+x-reveal/')
        ->and(preg_match_all('/data-home-step="[a-z_]+" x-reveal/', $how))->toBe(4)
        ->and($how)->not->toMatch('/rounded-panel bg-surface p-panel"\s+x-reveal/');
});

it('names the gallery of the profile like the card of the animal, and the similar animals apart', function (): void {
    $argo = motionAnimal($this->organization, ['name' => 'Argo']);
    $argo->addMedia(UploadedFile::fake()->image('a.jpg', 900, 600))->toMediaCollection('photos');
    $similar = collect(range(1, 3))->map(fn (int $index): Animal => motionAnimal($this->organization, ['name' => 'Simile'.$index, 'species' => $argo->species]));

    $html = $this->get(route('animals.show', $argo))->assertOk()->getContent();
    $names = photoTransitionNames((string) $html);

    expect($names)->toHaveCount(4)
        ->and(array_unique($names))->toHaveCount(4)
        ->and($names[0])->toBe(AnimalPhotoTransition::name($argo))
        ->and($names)->toContain(...$similar->map(fn (Animal $animal): string => AnimalPhotoTransition::name($animal))->all())
        ->and((string) $html)->toMatch('/data-gallery-viewport\s+wire:transition\.navigate="'.AnimalPhotoTransition::name($argo).'"\s+data-photo-transition\s/');
});

it('names the fallback photo of a profile without a gallery', function (): void {
    $argo = motionAnimal($this->organization, ['name' => 'Argo']);

    $html = (string) $this->get(route('animals.show', $argo))->assertOk()->getContent();

    // Valore vuoto: è il soggetto della pagina, non il link a un'altra scheda (motion.js).
    expect($html)->toContain('data-animal-photo-fallback')
        ->toMatch('/data-photo-transition=""/')
        ->and(photoTransitionNames($html))->toBe([AnimalPhotoTransition::name($argo)]);
});

it('reveals the sections of the profile below the gallery, not their headings twice', function (): void {
    $argo = motionAnimal($this->organization, ['name' => 'Argo']);

    $html = (string) $this->get(route('animals.show', $argo))->assertOk()->getContent();

    foreach (['data-animal-about', 'data-animal-facts', 'data-animal-organization-section'] as $section) {
        expect($html)->toMatch('/'.$section.' x-reveal>/');
    }

    $about = substr($html, (int) strpos($html, 'data-animal-about'), 600);
    expect($about)->not->toMatch('/data-section-heading\s+x-reveal/')
        ->and(substr($html, 0, (int) strpos($html, 'data-animal-header')))->not->toContain('x-reveal');
});

it('lets a page turn the photo transition of a site card off', function (): void {
    $argo = motionAnimal($this->organization, ['name' => 'Argo']);

    $named = (string) $this->blade('<x-site.animal-card :animal="$animal" />', ['animal' => $argo]);
    $plain = (string) $this->blade('<x-site.animal-card :animal="$animal" :photo-transition="false" :reveal="false" />', ['animal' => $argo]);

    expect(photoTransitionNames($named))->toBe([AnimalPhotoTransition::name($argo)])
        ->and($named)->toContain('data-photo-transition="'.route('animals.show', $argo).'"')
        ->toContain('x-reveal')
        ->and(photoTransitionNames($plain))->toBe([])
        ->and($plain)->not->toContain('x-reveal');
});
