<?php

declare(strict_types=1);

use App\Models\Animal;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 | La scheda animale si apre per id numerico (lo slug è univoco solo per ente): qualunque
 | altro segmento risponde 404, non 500 dal binding su una colonna intera.
 */

it('answers 404 for a non numeric animal segment', function (string $segment): void {
    $this->get('/animali/'.$segment)->assertNotFound();
})->with(['luna', '12abc', '-3', '1.5']);

it('still opens the page of a visible animal by id', function (): void {
    $animal = Animal::factory()->available()->forOrganization(Organization::factory()->verified()->create())->create();

    $this->get('/animali/'.$animal->id)->assertOk();
});
