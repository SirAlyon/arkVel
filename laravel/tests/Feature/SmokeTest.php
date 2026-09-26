<?php

declare(strict_types=1);

it('serves the welcome page', function () {
    $this->get('/')->assertOk();
});

it('redirects guests from the admin panel to login', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});
