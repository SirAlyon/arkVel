<?php

declare(strict_types=1);

namespace App\Support\Site;

use Illuminate\Http\Request;

/**
 * Voci della navigazione principale del sito pubblico, in un solo punto: header, menu
 * mobile e footer mostrano le stesse voci con lo stesso stato attivo.
 *
 * Una voce è la pagina corrente (aria-current="page") sulla sua route, oppure la sezione
 * corrente (aria-current="true") sulle route figlie: la scheda di un animale sta nel
 * catalogo, la pagina di un ente (e le sue pagine di donazione) negli enti.
 */
final readonly class SiteNavigation
{
    /**
     * Voci: chiave => [route della voce, chiave di traduzione, route della sezione].
     *
     * @var array<string, array{0: string, 1: string, 2: list<string>}>
     */
    private const array ITEMS = [
        'catalog' => ['catalog', 'public.nav.catalog', ['animals.*']],
        'organizations' => ['organizations.index', 'public.nav.organizations', ['organizations.*']],
    ];

    public function __construct(private Request $request) {}

    /**
     * @return list<NavItem>
     */
    public function primary(): array
    {
        $items = [];

        foreach (self::ITEMS as $key => [$route, $label, $section]) {
            $items[] = new NavItem(
                key: $key,
                url: route($route),
                label: __($label),
                current: $this->currentState($route, $section),
            );
        }

        return $items;
    }

    /**
     * @param  list<string>  $section
     */
    private function currentState(string $route, array $section): ?string
    {
        if ($this->request->routeIs($route)) {
            return 'page';
        }

        return $this->request->routeIs(...$section) ? 'true' : null;
    }
}
