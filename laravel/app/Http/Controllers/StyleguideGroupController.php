<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Styleguide\StyleguideGroups;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\View as ViewFactory;

/**
 * Pagina della styleguide dedicata a un gruppo di componenti x-ui.
 * Un gruppo sconosciuto (o non ancora documentato) risponde 404.
 */
final class StyleguideGroupController
{
    public function __invoke(string $group): View
    {
        abort_unless(StyleguideGroups::exists($group), 404);

        return ViewFactory::make(StyleguideGroups::view($group), ['group' => $group]);
    }
}
