<?php

declare(strict_types=1);

namespace App\Support\Map;

/**
 * Un punto della mappa pubblica (x-ui.map) e la sua voce nell'elenco alternativo
 * (x-ui.map.list): gli stessi dati, così l'elenco dice tutto quello che dice la mappa.
 *
 * - `id`: chiave stabile e pubblica (lo slug dell'ente), mai l'id del database;
 * - `title`, `description` (località pubblica, mai l'indirizzo), `count` e `countLabel`
 *   («12 animali»), `url` (dove porta il popup e la voce dell'elenco);
 * - `location`: posizione APPROSSIMATA ({@see ApproximateLocation}) o null se l'ente non
 *   ha coordinate: allora compare solo nell'elenco.
 */
final readonly class MapMarker
{
    public function __construct(
        public string $id,
        public string $title,
        public ?string $description,
        public int $count,
        public string $countLabel,
        public string $url,
        public ?ApproximateLocation $location = null,
    ) {}

    public function isLocated(): bool
    {
        return $this->location !== null;
    }

    /**
     * Per il JavaScript della mappa: solo dati pubblici e la posizione approssimata.
     *
     * @return array{id: string, lat: float|null, lng: float|null, title: string, description: string|null, count: int, countLabel: string, url: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'lat' => $this->location?->latitude,
            'lng' => $this->location?->longitude,
            'title' => $this->title,
            'description' => $this->description,
            'count' => $this->count,
            'countLabel' => $this->countLabel,
            'url' => $this->url,
        ];
    }
}
