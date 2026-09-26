<?php

declare(strict_types=1);

namespace Database\Seeders\Support;

use RuntimeException;

/**
 * Genera immagini placeholder JPEG OFFLINE (solo GD, nessuna chiamata di rete),
 * usate dai seeder per popolare le collection media degli animali senza
 * dipendere da servizi esterni tipo placeholder.com.
 *
 * L'immagine è un rettangolo a tinta unita con il testo (nome animale + etichetta)
 * scritto al centro tramite il font bitmap integrato di GD (imagestring): così
 * non serve alcun file .ttf a bordo, restando completamente autosufficienti.
 *
 * Il chiamante è proprietario del file temporaneo restituito e deve rimuoverlo
 * dopo averlo attaccato al media (addMedia sposta/copia il file).
 */
final class PlaceholderImageFactory
{
    /**
     * Palette di colori di sfondo (RGB) per differenziare visivamente le foto.
     *
     * @var list<array{int, int, int}>
     */
    private const array PALETTE = [
        [59, 130, 246],   // blu
        [16, 185, 129],   // verde
        [245, 158, 11],   // ambra
        [239, 68, 68],    // rosso
        [139, 92, 246],   // viola
        [236, 72, 153],   // rosa
        [14, 165, 233],   // azzurro
        [34, 197, 94],    // lime
    ];

    /**
     * Crea un JPEG placeholder su file temporaneo e ne restituisce il path.
     *
     * @param  string  $label  Testo mostrato al centro (es. nome animale).
     * @param  string  $subtitle  Riga secondaria (es. "avatar", "foto 1").
     * @param  int  $paletteIndex  Indice per scegliere il colore di sfondo (mod PALETTE).
     * @return string Path assoluto del file temporaneo generato (JPEG).
     */
    public function create(
        string $label,
        string $subtitle = '',
        int $paletteIndex = 0,
        int $width = 640,
        int $height = 480,
    ): string {
        // Dipendenza esplicita da ext-gd: senza l'estensione le funzioni image*
        // non esistono affatto. Falliamo subito con un messaggio chiaro invece di
        // un fatal "call to undefined function" più avanti.
        if (! function_exists('imagecreatetruecolor')) {
            throw new RuntimeException(
                'Estensione PHP ext-gd mancante: necessaria per generare le immagini placeholder dei seeder.',
            );
        }

        $image = imagecreatetruecolor($width, $height);

        if ($image === false) {
            throw new RuntimeException('Impossibile creare la risorsa immagine GD.');
        }

        try {
            [$r, $g, $b] = self::PALETTE[$paletteIndex % count(self::PALETTE)];

            $background = imagecolorallocate($image, $r, $g, $b);
            $foreground = imagecolorallocate($image, 255, 255, 255);

            if ($background === false || $foreground === false) {
                throw new RuntimeException('Impossibile allocare i colori GD.');
            }

            imagefilledrectangle($image, 0, 0, $width, $height, $background);

            $this->writeCentered($image, $label, $foreground, $height / 2 - 20);

            if ($subtitle !== '') {
                $this->writeCentered($image, $subtitle, $foreground, $height / 2 + 10);
            }

            $path = $this->temporaryPath();

            if (! imagejpeg($image, $path, 85)) {
                throw new RuntimeException("Impossibile scrivere il JPEG placeholder in {$path}.");
            }

            return $path;
        } finally {
            imagedestroy($image);
        }
    }

    /**
     * Scrive una riga di testo centrata orizzontalmente col font bitmap 5 di GD.
     */
    private function writeCentered(\GdImage $image, string $text, int $color, float $y): void
    {
        $font = 5; // font bitmap integrato: nessun file esterno richiesto.
        $charWidth = imagefontwidth($font);
        $width = imagesx($image);

        $textWidth = $charWidth * strlen($text);
        $x = (int) max(0, ($width - $textWidth) / 2);

        imagestring($image, $font, $x, (int) $y, $text, $color);
    }

    /**
     * Percorso temporaneo dedicato con estensione .jpg (medialibrary deduce il
     * mime dal contenuto, ma un'estensione coerente evita ambiguità).
     */
    private function temporaryPath(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'arkvel_seed_');

        if ($path === false) {
            throw new RuntimeException('Impossibile creare un file temporaneo per il placeholder.');
        }

        $jpgPath = $path.'.jpg';

        if (! rename($path, $jpgPath)) {
            // Fallback: se il rename fallisce, usiamo comunque il path originale.
            return $path;
        }

        return $jpgPath;
    }
}
