<?php

declare(strict_types=1);

namespace App\Support\Site;

use App\Models\Animal;

/**
 * Nome della View Transition della foto di un animale (UI-7): lo stesso sulla card
 * (x-site.animal-card), sulla foto della hero della home e sulla galleria della scheda,
 * così durante una navigazione wire:navigate il browser anima la foto da una pagina
 * all'altra (`wire:transition.navigate="<nome>"`, regole in resources/css/ui/motion.css).
 *
 * Il nome deve essere unico nella pagina: con due elementi dallo stesso nome il browser
 * annulla la transizione. Per questo è legato all'id e ogni pagina mostra un animale
 * una volta sola (la vetrina della home esclude l'animale della hero, gli animali simili
 * escludono quello della scheda, il catalogo non ripete gli animali tra le pagine caricate).
 */
final class AnimalPhotoTransition
{
    public const string PREFIX = 'animal-photo-';

    public static function name(Animal|int $animal): string
    {
        $id = $animal instanceof Animal ? (int) $animal->getKey() : $animal;

        return self::PREFIX.$id;
    }
}
