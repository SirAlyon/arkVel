<?php

declare(strict_types=1);

use App\Models\Animal;
use App\Support\Site\TranslatedText;

/**
 * Le descrizioni sono RichEditor (HTML) ma il sito pubblico le mostra come TESTO.
 * TranslatedText::pick deve appiattire l'HTML a testo pulito e trattare un markup
 * "visivamente vuoto" (es. <p></p> salvato da un RichEditor svuotato) come assente.
 */
it('treats visually-empty HTML as no text', function (string $html): void {
    $animal = new Animal;
    $animal->setTranslations('description', ['it' => $html]);

    expect(TranslatedText::pick($animal, 'description', 'it'))->toBeNull();
})->with([
    'empty paragraph' => '<p></p>',
    'paragraph with break' => '<p><br></p>',
    'whitespace only' => "   \n  ",
    'empty string' => '',
]);

it('flattens rich-text HTML to clean plain text with line breaks', function (): void {
    $animal = new Animal;
    $animal->setTranslations('description', ['it' => '<p>Ciao</p><p>Mondo</p>']);

    $picked = TranslatedText::pick($animal, 'description', 'it');

    expect($picked)->not->toBeNull()
        ->and($picked->text)->toBe("Ciao\nMondo")
        ->and($picked->locale)->toBe('it');
});

it('decodes entities and strips inline tags', function (): void {
    $animal = new Animal;
    $animal->setTranslations('description', ['it' => '<p>Cani &amp; gatti <strong>adottabili</strong></p>']);

    expect(TranslatedText::pick($animal, 'description', 'it')?->text)
        ->toBe('Cani & gatti adottabili');
});

it('falls back to a non-empty locale when the current one is visually empty', function (): void {
    $animal = new Animal;
    $animal->setTranslations('description', ['it' => '<p></p>', 'en' => '<p>Adoptable</p>']);

    $picked = TranslatedText::pick($animal, 'description', 'it', 'en');

    expect($picked?->text)->toBe('Adoptable')
        ->and($picked?->locale)->toBe('en');
});
