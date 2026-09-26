<?php

declare(strict_types=1);

use App\Enums\Species;
use Database\Seeders\Support\RemoteAnimalPhotoFetcher;
use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Support\Facades\Log;

/**
 * Il fetcher scarica foto placeholder REALI per il seeder demo. Deve produrre
 * URL deterministici e species-appropriate, e non sollevare mai: in caso di
 * errore logga e restituisce null così il seeding prosegue.
 */
function fetcherWith(HttpClient $http): RemoteAnimalPhotoFetcher
{
    return new RemoteAnimalPhotoFetcher($http);
}

/**
 * Risposta HTTP finta con un body immagine e il content-type dato (default JPEG,
 * accettato dal fetcher).
 */
function imageResponse(HttpClient $http, string $body = 'IMG-BYTES', string $contentType = 'image/jpeg')
{
    return $http->response($body, 200, ['Content-Type' => $contentType]);
}

afterEach(function () {
    // Pulizia dei file temporanei creati durante i test.
    foreach (glob(sys_get_temp_dir().'/arkvel_photo_*') ?: [] as $file) {
        @unlink($file);
    }
});

it('downloads a real photo to a temporary jpg file on success', function () {
    $http = new HttpClient;
    $http->fake([
        '*' => imageResponse($http, 'JPEG-BYTES'),
    ]);

    $path = fetcherWith($http)->fetch(Species::Dog, animalId: 7);

    expect($path)->not->toBeNull()
        ->and(is_file($path))->toBeTrue()
        ->and(file_get_contents($path))->toBe('JPEG-BYTES')
        ->and($path)->toEndWith('.jpg');
});

it('hits a species-appropriate primary source, deterministic per animal id', function () {
    $http = new HttpClient;
    $http->fake([
        '*' => imageResponse($http),
    ]);

    // Dog → placedog.net con id deterministico (animalId*10 + variant = 420).
    fetcherWith($http)->fetch(Species::Dog, animalId: 42, variant: 0);

    $http->assertSent(fn ($request): bool => $request->url() === 'https://placedog.net/640/480?id=420');
});

it('uses a species-correct primary source for every species', function (Species $species, string $host) {
    $http = new HttpClient;
    $http->fake(['*' => imageResponse($http)]);

    fetcherWith($http)->fetch($species, animalId: 1, variant: 0);

    // La PRIMA richiesta (candidato preferito) colpisce la sorgente per-specie.
    $http->assertSentInOrder([
        fn ($request): bool => str_contains($request->url(), $host),
    ]);
})->with([
    'dog' => [Species::Dog, 'placedog.net'],
    'cat' => [Species::Cat, 'cataas.com'],
    'other' => [Species::Other, 'picsum.photos'],
]);

it('accepts png as well as jpeg', function () {
    $http = new HttpClient;
    $http->fake(['*' => imageResponse($http, 'PNG-BYTES', 'image/png')]);

    $path = fetcherWith($http)->fetch(Species::Other, animalId: 2);

    expect($path)->not->toBeNull()
        ->and(file_get_contents($path))->toBe('PNG-BYTES');
});

it('rejects a webp response (GD cannot convert it) and falls through to null', function () {
    Log::spy();

    $http = new HttpClient;
    // Alcune sorgenti (cataas) servono WebP: il GD del container non lo legge,
    // quindi va scartato prima di arrivare al media.
    $http->fake(['*' => imageResponse($http, 'WEBP-BYTES', 'image/webp')]);

    $path = fetcherWith($http)->fetch(Species::Cat, animalId: 3);

    expect($path)->toBeNull();
    Log::shouldHaveReceived('warning');
});

it('falls back to the next candidate source when the primary fails', function () {
    $http = new HttpClient;
    $http->fake([
        'placedog.net/*' => $http->response('nope', 401),
        'loremflickr.com/*' => imageResponse($http, 'REAL'),
    ]);

    $path = fetcherWith($http)->fetch(Species::Dog, animalId: 5, variant: 0);

    expect($path)->not->toBeNull()
        ->and(file_get_contents($path))->toBe('REAL');

    // Ha provato prima placedog (fallito) e poi LoremFlickr (riuscito).
    $http->assertSent(fn ($request): bool => str_contains($request->url(), 'placedog.net'));
    $http->assertSent(fn ($request): bool => str_contains($request->url(), 'loremflickr.com/640/480/dog?lock=50'));
});

it('varies the deterministic key per variant so avatar and photos differ', function () {
    $http = new HttpClient;
    $http->fake(['*' => imageResponse($http)]);

    fetcherWith($http)->fetch(Species::Dog, animalId: 3, variant: 0);
    fetcherWith($http)->fetch(Species::Dog, animalId: 3, variant: 1);

    $http->assertSent(fn ($request): bool => str_contains($request->url(), 'id=30'));
    $http->assertSent(fn ($request): bool => str_contains($request->url(), 'id=31'));
});

it('returns null and logs a warning on a non-2xx response instead of throwing', function () {
    Log::spy();

    $http = new HttpClient;
    // Ogni candidato risponde 503: tutti falliscono, la fetch restituisce null.
    $http->fake(['*' => $http->response('nope', 503)]);

    $path = fetcherWith($http)->fetch(Species::Dog, animalId: 1);

    expect($path)->toBeNull();
    Log::shouldHaveReceived('warning');
});

it('returns null and logs a warning when the response body is empty', function () {
    Log::spy();

    $http = new HttpClient;
    $http->fake(['*' => $http->response('', 200)]);

    $path = fetcherWith($http)->fetch(Species::Cat, animalId: 1);

    expect($path)->toBeNull();
    Log::shouldHaveReceived('warning');
});

it('returns null and logs a warning when the request throws (offline/timeout)', function () {
    Log::spy();

    $http = new HttpClient;
    $http->fake(function (): void {
        throw new RuntimeException('offline');
    });

    $path = fetcherWith($http)->fetch(Species::Dog, animalId: 1);

    expect($path)->toBeNull();
    Log::shouldHaveReceived('warning');
});
