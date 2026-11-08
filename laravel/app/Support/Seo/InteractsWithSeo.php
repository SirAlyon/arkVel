<?php

declare(strict_types=1);

namespace App\Support\Seo;

use Illuminate\Contracts\View\View;

/**
 * Per i componenti Livewire full-page: consegna il {@see SeoData} al layout.
 *
 * - `seo`: letto da `partials/seo/head` (title, description, Open Graph) e
 *   `partials/seo/breadcrumbs`;
 * - `title`: il titolo come lo dà Livewire (`->title()`), lo stesso di `$seo->title`.
 *
 * `title()`/`layoutData()` sono macro di Livewire sulla View, invisibili a PHPStan.
 */
trait InteractsWithSeo
{
    protected function withSeo(View $view, SeoData $seo): View
    {
        $view->title($seo->title); // @phpstan-ignore method.notFound
        $view->layoutData(['seo' => $seo]); // @phpstan-ignore method.notFound

        return $view;
    }
}
