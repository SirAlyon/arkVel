<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\Request;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Il client HTTP di test (Symfony) invia di default `Accept-Language: en-us`,
        // che SetLocale rispetterebbe. I test partono invece senza preferenza di lingua
        // (→ lingua di default dell'app); chi testa la negoziazione imposta l'header.
        $this->withHeader('Accept-Language', '');

        // Seconda guardia (la prima è phpunit.xml): i test, anche quelli browser che girano
        // nel processo di Pest, non scrivono mai nell'indice Meilisearch di sviluppo, nemmeno
        // con una config in cache (`config:cache` ignora le variabili di phpunit.xml).
        // Chi prova il driver meilisearch lo imposta nel test con un client finto.
        config([
            'scout.driver' => 'collection',
            'scout.queue' => false,
            'scout.meilisearch.host' => 'http://127.0.0.1:9',
            'scout.meilisearch.key' => null,
        ]);
    }

    protected function tearDown(): void
    {
        // Gli host fidati (middleware TrustHosts) sono statici su Request: un test che gira
        // in un ambiente diverso da `testing` (es. la styleguide in `production`) li
        // imposterebbe per tutti i test successivi dello stesso processo.
        Request::setTrustedHosts([]);

        parent::tearDown();
    }
}
