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
