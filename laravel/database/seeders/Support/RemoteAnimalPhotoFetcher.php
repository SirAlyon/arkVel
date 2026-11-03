<?php

declare(strict_types=1);

namespace Database\Seeders\Support;

use App\Enums\Species;
use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Scarica foto placeholder REALI e species-appropriate per gli animali demo.
 *
 * A differenza di {@see PlaceholderImageFactory} (rettangoli GD offline), qui si
 * recuperano immagini reali dal web, così il catalogo demo risulta credibile.
 *
 * Sorgenti per specie (foto reali, non generate):
 *   - Dog  → placedog.net, deterministico via `?id={lock}` (stessa foto tra i run);
 *   - Cat  → cataas.com, foto reali di gatti (species-correct);
 *   - Other→ picsum.photos, deterministico via `seed` (animale/generico).
 * Ogni specie ha più candidati provati in ordine: se il primo non risponde si
 * passa al successivo (fallback fino a LoremFlickr keyed-by-species).
 *
 * Determinismo: il `lock`/`seed` deriva da (animal id, variante), così re-run
 * del seeder ripescano la stessa foto e slot diversi (avatar/foto 1/…) sono
 * distinti. In pratica il seeder è idempotente (salta animali già con foto),
 * quindi il determinismo serve soprattutto a rendere stabile ogni singolo run.
 *
 * Robustezza: ogni download è wrappato e ha un timeout breve. Se TUTTI i
 * candidati falliscono (offline, timeout, HTTP non-2xx, corpo vuoto) NON solleva:
 * logga un warning e restituisce null, così il seeder prosegue senza abortire (il
 * chiamante ricade su un placeholder offline). Il chiamante è proprietario del
 * file temporaneo restituito e deve rimuoverlo dopo l'attach.
 */
final class RemoteAnimalPhotoFetcher
{
    /** Timeout complessivo (connessione + risposta) volutamente breve. */
    private const int TIMEOUT_SECONDS = 8;

    /**
     * Content-type accettati: solo i formati che la conversione `thumb`
     * (Spatie Image, driver GD) sa leggere in modo affidabile in questo build.
     * WebP/GIF sono esclusi di proposito: alcune sorgenti (es. cataas.com) li
     * servono, ma il GD del container NON ha il supporto WebP e la conversione
     * fallirebbe. Scartandoli qui passiamo al candidato successivo (o al fallback
     * offline) invece di attaccare un file inutilizzabile. Includiamo l'alias
     * non-standard `image/jpg` perché placedog.net etichetta così i suoi JPEG.
     *
     * @var list<string>
     */
    private const array ACCEPTED_CONTENT_TYPES = ['image/jpeg', 'image/jpg', 'image/png'];

    public function __construct(
        private readonly HttpClient $http,
    ) {}

    /**
     * Scarica una foto per l'animale su file temporaneo e ne restituisce il path,
     * oppure null se ogni sorgente candidata fallisce (già loggato).
     *
     * @param  int  $variant  Discriminante per ottenere foto diverse dallo stesso
     *                        animale (avatar/foto 1/foto 2…): entra nel lock/seed
     *                        così ogni slot resta stabile ma distinto.
     */
    public function fetch(Species $species, int $animalId, int $variant = 0): ?string
    {
        $lock = $animalId * 10 + $variant;

        foreach ($this->candidateUrls($species, $lock) as $url) {
            $path = $this->tryDownload($url);

            if ($path !== null) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Tenta un singolo download; restituisce il path del file temporaneo o null
     * (con warning) su qualunque fallimento non fatale.
     */
    private function tryDownload(string $url): ?string
    {
        try {
            $response = $this->http
                ->timeout(self::TIMEOUT_SECONDS)
                ->connectTimeout(self::TIMEOUT_SECONDS)
                ->get($url);

            if ($response->failed()) {
                return $this->warn($url, "HTTP {$response->status()}");
            }

            // Solo formati che GD (conversione thumb) sa leggere: scartiamo
            // WebP/GIF così un file inconvertibile non arriva mai al media.
            $contentType = $this->normalizeContentType($response->header('Content-Type'));

            if (! in_array($contentType, self::ACCEPTED_CONTENT_TYPES, true)) {
                return $this->warn($url, "content-type non supportato: {$contentType}");
            }

            $body = $response->body();

            if ($body === '') {
                return $this->warn($url, 'corpo vuoto');
            }

            $path = $this->writeTemporary($body);

            if ($path === null) {
                return $this->warn($url, 'impossibile creare il file temporaneo');
            }

            return $path;
        } catch (Throwable $exception) {
            return $this->warn($url, $exception->getMessage());
        }
    }

    /**
     * URL candidati (in ordine di preferenza) per la specie, tutti deterministici
     * rispetto al `lock`. Le specie del dominio sono Dog/Cat/Other (vedi
     * {@see Species}); l'ultimo candidato è sempre LoremFlickr keyed-by-species
     * come rete di sicurezza.
     *
     * @return list<string>
     */
    private function candidateUrls(Species $species, int $lock): array
    {
        return match ($species) {
            Species::Dog => [
                "https://placedog.net/640/480?id={$lock}",
                "https://loremflickr.com/640/480/dog?lock={$lock}",
            ],
            Species::Cat => [
                'https://cataas.com/cat?width=640&height=480',
                "https://loremflickr.com/640/480/cat?lock={$lock}",
            ],
            Species::Other => [
                "https://picsum.photos/seed/pet{$lock}/640/480",
                "https://loremflickr.com/640/480/pet?lock={$lock}",
            ],
        };
    }

    /**
     * Normalizza l'header Content-Type al solo media type in minuscolo
     * (es. "image/jpeg; charset=binary" → "image/jpeg").
     */
    private function normalizeContentType(string $header): string
    {
        $type = explode(';', $header, 2)[0];

        return strtolower(trim($type));
    }

    /**
     * Scrive i byte scaricati su un file temporaneo con estensione .jpg
     * (medialibrary deduce comunque il mime dal contenuto), o null se fallisce.
     */
    private function writeTemporary(string $bytes): ?string
    {
        $path = tempnam(sys_get_temp_dir(), 'arkvel_photo_');

        if ($path === false) {
            return null;
        }

        $jpgPath = $path.'.jpg';

        if (! rename($path, $jpgPath)) {
            $jpgPath = $path;
        }

        if (file_put_contents($jpgPath, $bytes) === false) {
            @unlink($jpgPath);

            return null;
        }

        return $jpgPath;
    }

    /**
     * Logga il fallimento (non fatale) e restituisce null per far proseguire il
     * seeder / passare al candidato successivo.
     */
    private function warn(string $url, string $reason): null
    {
        Log::warning('DemoDataSeeder: download foto placeholder fallito, salto.', [
            'url' => $url,
            'reason' => $reason,
        ]);

        return null;
    }
}
