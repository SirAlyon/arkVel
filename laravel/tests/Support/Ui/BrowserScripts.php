<?php

declare(strict_types=1);

namespace Tests\Support\Ui;

/**
 * Espressioni JavaScript per le asserzioni dei test browser sui componenti x-ui
 * (`$page->assertScript(BrowserScripts::…(), …)`).
 */
final class BrowserScripts
{
    /**
     * Elementi interattivi visibili dentro `$scope` con un target sotto `$minimum` px
     * (WCAG 2.5.8; per ArkVel il minimo dei controlli è 44 px). Restituisce l'elenco
     * dei colpevoli come stringhe "tag.classe WxH", vuoto se va tutto bene.
     * I link nel testo (`a` dentro un paragrafo) sono esclusi, come prevede la regola;
     * i link testuali isolati (`[data-text-link]`, x-ui.link) devono arrivare a 24 px.
     */
    public static function undersizedTargets(string $scope, int $minimum = 44): string
    {
        $scope = json_encode($scope, JSON_THROW_ON_ERROR);

        return <<<JS
            [...document.querySelectorAll({$scope})].flatMap((root) => [...root.querySelectorAll('button, a[href], input:not([type=hidden]), select, textarea, summary, [role=button], [role=tab], [role=switch], [role=option], [role=menuitem], [tabindex]:not([tabindex="-1"])')])
                .filter((element) => element.checkVisibility() && !element.closest('p, li > p, pre'))
                .filter((element) => !['checkbox', 'radio'].includes(element.type) || element.closest('label') === null)
                .map((element) => [element, element.getBoundingClientRect()])
                .filter(([element, box]) => { const min = element.matches('[data-text-link]') ? 24 : {$minimum}; return box.width < min || box.height < min; })
                .map(([element, box]) => `\${element.tagName.toLowerCase()}.\${element.className.toString().split(' ').slice(0, 3).join('.')} \${Math.round(box.width)}x\${Math.round(box.height)}`)
            JS;
    }

    /**
     * Un attributo (o, con `$attribute = null`, l'id) dell'elemento attivo.
     */
    public static function activeElement(?string $attribute = null): string
    {
        return $attribute === null
            ? 'document.activeElement?.id ?? null'
            : 'document.activeElement?.getAttribute('.json_encode($attribute, JSON_THROW_ON_ERROR).') ?? null';
    }
}
