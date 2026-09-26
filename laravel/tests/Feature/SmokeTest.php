<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('serves the public home page', function () {
    $this->get('/')->assertOk();
});

it('redirects guests from the admin panel to login', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});
