<?php

declare(strict_types=1);

namespace App\Support\Styleguide;

use Illuminate\Support\Facades\View;

/**
 * Gruppi di componenti x-ui documentati nella styleguide, uno per pagina
 * (/_styleguide/{group}, vista `styleguide.groups.{group}`). L'ordine è quello
 * della navigazione; un gruppo compare solo quando la sua vista esiste.
 */
final class StyleguideGroups
{
    /**
     * @var list<string>
     */
    public const array ALL = ['actions', 'forms', 'pickers', 'overlays', 'content', 'domain', 'shell', 'media', 'motion', 'map'];

    /**
     * @return list<string>
     */
    public static function available(): array
    {
        return array_values(array_filter(self::ALL, self::exists(...)));
    }

    public static function exists(string $group): bool
    {
        return in_array($group, self::ALL, true) && View::exists(self::view($group));
    }

    public static function view(string $group): string
    {
        return 'styleguide.groups.'.$group;
    }
}
