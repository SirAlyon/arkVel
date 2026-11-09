<?php

declare(strict_types=1);

use App\Support\Site\WebUrl;

it('accepts only valid http and https addresses', function (mixed $value, ?string $expected): void {
    expect(WebUrl::safe($value))->toBe($expected);
})->with([
    'https' => ['https://ilfaro.example/chi-siamo', 'https://ilfaro.example/chi-siamo'],
    'http upper case' => ['HTTP://ilfaro.example', 'HTTP://ilfaro.example'],
    'spaces around' => ['  https://ilfaro.example  ', 'https://ilfaro.example'],
    'javascript' => ['javascript://ilfaro.example/%0Aalert(1)', null],
    'data' => ['data:text/html,ciao', null],
    'mailto' => ['mailto:info@ilfaro.example', null],
    'relative' => ['/enti/il-faro', null],
    'text' => ['il nostro sito', null],
    'null' => [null, null],
    'not a string' => [42, null],
]);
