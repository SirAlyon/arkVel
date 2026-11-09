<?php

declare(strict_types=1);

use App\Models\Animal;
use App\Support\Site\AnimalPhotoTransition;

/*
 | Nome della View Transition della foto di un animale (UI-7): deve essere un <custom-ident>
 | CSS valido e legato all'id, così card e galleria dello stesso animale si riconoscono.
 */

it('derives the name from the animal id', function (): void {
    $animal = new Animal;
    $animal->id = 42;

    expect(AnimalPhotoTransition::name($animal))->toBe('animal-photo-42')
        ->and(AnimalPhotoTransition::name(42))->toBe('animal-photo-42')
        ->and(AnimalPhotoTransition::name(7))->not->toBe(AnimalPhotoTransition::name(42));
});

it('is a valid CSS custom identifier', function (): void {
    expect(AnimalPhotoTransition::name(123))->toMatch('/^[a-z][a-z0-9-]*$/');
});
