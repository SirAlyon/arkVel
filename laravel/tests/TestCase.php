<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

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
}
