<?php

declare(strict_types=1);

namespace Tests\Support\Media;

use RuntimeException;

/**
 * Genera nei test un JPEG con un segmento EXIF vero (APP1): orientamento e coordinate GPS,
 * come una foto scattata col telefono. PHP non scrive EXIF, quindi la struttura TIFF è
 * costruita a mano (little-endian): IFD0 con Orientation e il puntatore al GPS IFD, poi il
 * GPS IFD con latitudine e longitudine (45°27'50.4" N, 9°11'24" E).
 */
final class ExifJpeg
{
    /**
     * Crea il file e ne restituisce il percorso. `$width`×`$height` sono le dimensioni
     * dei pixel salvati; con `$orientation` 6 la foto va mostrata ruotata di 90° (in
     * verticale).
     */
    public static function create(string $path, int $width = 64, int $height = 32, int $orientation = 1): string
    {
        $image = imagecreatetruecolor($width, $height);

        if ($image === false) {
            throw new RuntimeException('GD could not create the test image.');
        }

        // Metà sinistra rossa, metà destra blu: dopo la rotazione si vede dov'è finita.
        imagefilledrectangle($image, 0, 0, intdiv($width, 2) - 1, $height - 1, (int) imagecolorallocate($image, 220, 40, 40));
        imagefilledrectangle($image, intdiv($width, 2), 0, $width - 1, $height - 1, (int) imagecolorallocate($image, 40, 40, 220));

        ob_start();
        imagejpeg($image, null, 90);
        $jpeg = (string) ob_get_clean();

        $tiff = self::tiff($orientation);
        $app1 = "\xFF\xE1".pack('n', 2 + 6 + strlen($tiff))."Exif\x00\x00".$tiff;

        // Subito dopo SOI (FFD8), come nei file delle fotocamere.
        file_put_contents($path, substr($jpeg, 0, 2).$app1.substr($jpeg, 2));

        return $path;
    }

    private static function tiff(int $orientation): string
    {
        $ifd0Offset = 8;
        $ifd0Size = 2 + 2 * 12 + 4;
        $gpsOffset = $ifd0Offset + $ifd0Size;
        $gpsSize = 2 + 5 * 12 + 4;
        $latitudeOffset = $gpsOffset + $gpsSize;
        $longitudeOffset = $latitudeOffset + 24;

        $entry = static fn (int $tag, int $type, int $count, string $value): string => pack('vvV', $tag, $type, $count).str_pad($value, 4, "\x00");
        $rationals = static fn (array $values): string => implode('', array_map(static fn (array $pair): string => pack('VV', ...$pair), $values));

        return 'II'.pack('vV', 42, $ifd0Offset)
            // IFD0: Orientation (SHORT), GPSInfo (LONG → offset del GPS IFD).
            .pack('v', 2)
            .$entry(0x0112, 3, 1, pack('v', $orientation))
            .$entry(0x8825, 4, 1, pack('V', $gpsOffset))
            .pack('V', 0)
            // GPS IFD: versione, latitudine N, longitudine E.
            .pack('v', 5)
            .$entry(0x0000, 1, 4, "\x02\x02\x00\x00")
            .$entry(0x0001, 2, 2, "N\x00")
            .$entry(0x0002, 5, 3, pack('V', $latitudeOffset))
            .$entry(0x0003, 2, 2, "E\x00")
            .$entry(0x0004, 5, 3, pack('V', $longitudeOffset))
            .pack('V', 0)
            .$rationals([[45, 1], [27, 1], [504, 10]])
            .$rationals([[9, 1], [11, 1], [24, 1]]);
    }

    /**
     * Le chiavi GPS che exif_read_data() trova nel file (vuoto se non ce ne sono).
     *
     * @return list<string>
     */
    public static function gpsKeys(string $path): array
    {
        $exif = @exif_read_data($path);

        if (! is_array($exif)) {
            return [];
        }

        return array_values(array_filter(array_map(strval(...), array_keys($exif)), static fn (string $key): bool => str_starts_with($key, 'GPS')));
    }
}
