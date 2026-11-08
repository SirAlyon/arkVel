<?php

declare(strict_types=1);

/*
 | Componenti x-ui del gruppo "pickers": combobox e date-picker. Test di rendering
 | (markup, ARIA, contesto di x-ui.field, valori, traduzioni). Comportamento nel browser:
 | tests/Browser/Components/PickersComponentsTest.php. Le regole di id ed errore sono in
 | App\Support\Ui\FieldControl (test unit in tests/Unit/Support/Ui).
 */

use Illuminate\Support\Facades\Blade;
use Livewire\Component;
use Livewire\Livewire;

// Helper condivisi con FormsComponentsTest (stessa definizione, chi carica prima la dichiara).
if (! function_exists('fieldLinks')) {
    /**
     * Collegamenti ARIA di un frammento: la label punta a un controllo che esiste (con
     * `for`, o con aria-labelledby sull'id della label quando il controllo non è
     * etichettabile) e ogni id di aria-describedby esiste nel DOM.
     *
     * @return array{for: string, via: string, id: string, control: string, described: list<string>, missing: list<string>}
     */
    function fieldLinks(string $html): array
    {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?><body>'.$html.'</body>');
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);

        $label = $xpath->query('//label')->item(0);
        $for = (string) $label?->getAttribute('for');
        $labelId = (string) $label?->getAttribute('id');
        $control = match (true) {
            $for !== '' => $xpath->query("//*[@id='{$for}']")->item(0),
            $labelId !== '' => $xpath->query("//*[contains(concat(' ', normalize-space(@aria-labelledby), ' '), ' {$labelId} ')]")->item(0),
            default => null,
        };
        $described = array_values(array_filter(explode(' ', (string) $control?->getAttribute('aria-describedby'))));
        $missing = array_values(array_filter($described, fn (string $id): bool => $xpath->query("//*[@id='{$id}']")->length === 0));

        return [
            'for' => $for,
            'via' => $for !== '' ? 'for' : 'aria-labelledby',
            'id' => (string) $control?->getAttribute('id'),
            'control' => (string) $control?->nodeName,
            'described' => $described,
            'missing' => $missing,
        ];
    }
}

if (! function_exists('registerLabelledWrapper')) {
    /** Componente anonimo di test con una prop `label` (come dropdown, tabs.list, skeleton...). */
    function registerLabelledWrapper(): void
    {
        $directory = sys_get_temp_dir().'/arkvel-ui-test-components';
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
        file_put_contents($directory.'/labelled.blade.php', "@props(['label', 'name' => null])\n<div data-labelled=\"{{ \$label }}\">{{ \$slot }}</div>");
        Blade::anonymousComponentPath($directory, 'uitest');
    }
}

beforeEach(function (): void {
    app()->setLocale('it');
});

describe('combobox', function (): void {
    $breeds = ['labrador' => 'Labrador', 'beagle' => 'Beagle', 'mixed' => 'Meticcio'];

    it('renders an APG combobox with a listbox popup', function () use ($breeds): void {
        $this->blade('<x-ui.combobox :options="$options" aria-label="Razza" />', ['options' => $breeds])
            ->assertSee('role="combobox"', escape: false)
            ->assertSee('aria-autocomplete="list"', escape: false)
            ->assertSee('aria-expanded="false"', escape: false)
            ->assertSee('aria-controls="combobox-', escape: false)
            ->assertSee('x-bind:aria-activedescendant="activeId"', escape: false)
            ->assertSee('autocomplete="off"', escape: false)
            ->assertSee('role="listbox"', escape: false)
            ->assertSee('aria-label="Razza"', escape: false)
            ->assertSee('popover="manual"', escape: false)
            ->assertSee('x-anchor.bottom-start.offset.8.fixed="$refs.box"', escape: false)
            ->assertSee('rounded-card bg-surface-raised', escape: false)
            ->assertSee('shadow-overlay', escape: false);
    });

    it('renders one option per entry with value, label and a check icon', function () use ($breeds): void {
        $this->blade('<x-ui.combobox :options="$options" aria-label="Razza" />', ['options' => $breeds])
            ->assertSeeInOrder(['data-value="labrador"', 'Labrador', 'data-icon="check"', 'data-value="beagle"', 'data-value="mixed"'], escape: false)
            ->assertSee('role="option"', escape: false)
            ->assertSee('aria-selected="false"', escape: false)
            ->assertDontSee('aria-selected="true"', escape: false);
    });

    it('marks the initial value as selected and shows its label', function () use ($breeds): void {
        $this->blade('<x-ui.combobox name="breed" value="beagle" :options="$options" aria-label="Razza" />', ['options' => $breeds])
            ->assertSee('<input type="hidden" name="breed" value="beagle"', escape: false)
            ->assertSee('value="Beagle"', escape: false)
            ->assertSee('aria-selected="true"', escape: false)
            ->assertSee('id="combobox-breed"', escape: false)
            ->assertSee('id="combobox-breed-option-1"', escape: false);
    });

    it('accepts options with a description and searches it', function (): void {
        $this->blade('<x-ui.combobox :options="$options" aria-label="Rifugio" />', ['options' => [
            'faro' => ['label' => 'Rifugio Il Faro', 'description' => 'Genova'],
        ]])
            ->assertSee('data-label="Rifugio Il Faro"', escape: false)
            ->assertSee('data-search="Rifugio Il Faro Genova"', escape: false)
            ->assertSeeInOrder(['Rifugio Il Faro', 'text-small text-fg-muted', 'Genova'], escape: false);
    });

    it('inherits id, name, help, error and required from x-ui.field', function () use ($breeds): void {
        $view = $this->blade('<x-ui.field name="breed" label="Razza" help="Aiuto" error="Scegli una razza" required><x-ui.combobox :options="$options" /></x-ui.field>', ['options' => $breeds])
            ->assertSee('<label id="field-breed-label" for="field-breed"', escape: false)
            ->assertSee('id="field-breed"', escape: false)
            ->assertSee('aria-controls="field-breed-listbox"', escape: false)
            ->assertSee('name="breed"', escape: false)
            ->assertSee('aria-describedby="field-breed-help field-breed-error"', escape: false)
            ->assertSee('aria-invalid="true"', escape: false)
            ->assertSee('border-danger', escape: false)
            ->assertSee('data-field-indicator="required"', escape: false)
            ->assertSee('aria-label="Razza"', escape: false);

        expect((string) $view)->toMatch('/role="combobox"[^>]*\srequired[\s>]/');
    });

    it('reads the error from the validation bag by name', function () use ($breeds): void {
        $this->withViewErrors(['breed' => 'La razza è obbligatoria.'])
            ->blade('<x-ui.field name="breed" label="Razza"><x-ui.combobox :options="$options" /></x-ui.field>', ['options' => $breeds])
            ->assertSee('aria-invalid="true"', escape: false)
            ->assertSee('La razza è obbligatoria.');
    });

    it('puts wire:model and class on the modelable container, the rest on the input', function () use ($breeds): void {
        $view = $this->blade('<x-ui.combobox wire:model.live="breed" class="max-w-md" placeholder="Cerca" :options="$options" aria-label="Razza" />', ['options' => $breeds]);

        $view->assertSeeInOrder(['x-modelable="value"', 'class="relative w-full min-w-0 max-w-md" wire:model.live="breed"', 'role="combobox"', 'placeholder="Cerca"'], escape: false);

        expect(substr_count((string) $view, 'wire:model.live="breed"'))->toBe(1);
    });

    it('renders the clear and toggle buttons with translated names', function () use ($breeds): void {
        $this->blade('<x-ui.combobox :options="$options" aria-label="Razza" />', ['options' => $breeds])
            ->assertSee('aria-label="Svuota il campo"', escape: false)
            ->assertSee('aria-label="Mostra le opzioni"', escape: false)
            ->assertSee('tabindex="-1"', escape: false)
            ->assertSee('Nessun risultato')
            ->assertSee('role="status"', escape: false)
            ->assertSee('size-11', escape: false);
    });

    it('disables the input and the buttons', function () use ($breeds): void {
        $html = (string) $this->blade('<x-ui.combobox :options="$options" aria-label="Razza" disabled />', ['options' => $breeds]);

        expect($html)->toMatch('/role="combobox"[^>]*disabled="disabled"/')
            ->and(preg_match_all('/\sdisabled\s+data-combobox-(clear|toggle)/', $html))->toBe(2);
    });

    it('enables server-side search with keyed options', function () use ($breeds): void {
        $this->blade('<x-ui.combobox search="query" :options="$options" aria-label="Razza" />', ['options' => $breeds])
            ->assertSee('\u0022search\u0022:\u0022query\u0022', escape: false)
            ->assertSee('wire:key="combobox-', escape: false);
    });

    it('translates its texts in English', function () use ($breeds): void {
        app()->setLocale('en');

        $this->blade('<x-ui.combobox :options="$options" />', ['options' => $breeds])
            ->assertSee('aria-label="Clear the field"', escape: false)
            ->assertSee('aria-label="Show options"', escape: false)
            ->assertSee('aria-label="Options"', escape: false)
            ->assertSee('No results')
            ->assertSee('1 result available.', escape: false);
    });
});

describe('date-picker', function (): void {
    it('renders a pill field with a calendar button and a popover dialog', function (): void {
        $this->blade('<x-ui.date-picker name="visit" value="2026-11-12" aria-label="Data" />')
            ->assertSee('data-ui-date-picker="popover"', escape: false)
            ->assertSee('<input type="hidden" name="visit" value="2026-11-12"', escape: false)
            ->assertSee('readonly', escape: false)
            ->assertSee('value="12 novembre 2026"', escape: false)
            ->assertSee('aria-haspopup="dialog"', escape: false)
            ->assertSee('aria-expanded="false"', escape: false)
            ->assertSee('aria-controls="date-picker-visit-calendar"', escape: false)
            ->assertSee('aria-label="Apri il calendario"', escape: false)
            ->assertSee('role="dialog"', escape: false)
            ->assertSee('aria-label="Calendario"', escape: false)
            ->assertSee('popover="manual"', escape: false)
            ->assertSee('rounded-full border-2 bg-canvas', escape: false)
            ->assertSee('border-line-strong', escape: false);
    });

    it('wraps a Cally calendar with translated previous and next buttons', function (): void {
        $this->blade('<x-ui.date-picker value="2026-11-12" min="2026-11-01" max="2026-12-31" aria-label="Data" />')
            ->assertSee('<calendar-date', escape: false)
            ->assertSee('<calendar-month></calendar-month>', escape: false)
            ->assertSee('min="2026-11-01"', escape: false)
            ->assertSee('max="2026-12-31"', escape: false)
            ->assertSee('locale="it"', escape: false)
            ->assertSee('first-day-of-week="1"', escape: false)
            ->assertSee('x-on:change="select($event)"', escape: false)
            ->assertSeeInOrder(['slot="previous"', 'data-icon="chevron-left"', 'Mese precedente', 'slot="next"', 'data-icon="chevron-right"', 'Mese successivo'], escape: false);
    });

    it('accepts dates as DateTimeInterface and ignores invalid ones', function (): void {
        $this->blade('<x-ui.date-picker :value="$value" min="2026-02-30" aria-label="Data" />', ['value' => new DateTimeImmutable('2026-03-05 18:30')])
            ->assertSee('\u0022value\u0022:\u00222026-03-05\u0022', escape: false)
            ->assertSee('value="5 marzo 2026"', escape: false)
            ->assertDontSee('min="2026-02-30"', escape: false);
    });

    it('passes disallowed dates and weekdays to the calendar', function (): void {
        $this->blade('<x-ui.date-picker :disallowed="[0, 6, \'2026-12-25\', \'not-a-date\', 9]" aria-label="Data" />')
            ->assertSee('\u0022disallowed\u0022:{\u0022dates\u0022:[\u00222026-12-25\u0022],\u0022weekdays\u0022:[0,6]}', escape: false);
    });

    it('uses the locale and first day of week props', function (): void {
        $this->blade('<x-ui.date-picker value="2026-11-12" locale="en" :first-day-of-week="0" aria-label="Date" />')
            ->assertSee('locale="en"', escape: false)
            ->assertSee('first-day-of-week="0"', escape: false)
            ->assertSee('value="November 12, 2026"', escape: false);
    });

    it('inherits id, help, error and required from x-ui.field', function (): void {
        $this->blade('<x-ui.field name="visit_date" label="Data della visita" help="Da oggi" error="Scegli una data" required><x-ui.date-picker /></x-ui.field>')
            ->assertSee('<label id="field-visit-date-label" for="field-visit-date"', escape: false)
            ->assertSee('id="field-visit-date"', escape: false)
            ->assertSee('name="visit_date"', escape: false)
            ->assertSee('aria-describedby="field-visit-date-help field-visit-date-error"', escape: false)
            ->assertSee('aria-invalid="true"', escape: false)
            ->assertSee('aria-required="true"', escape: false)
            ->assertSee('border-danger', escape: false)
            ->assertSee('placeholder="Scegli una data"', escape: false);
    });

    it('puts wire:model and class on the modelable container', function (): void {
        $view = $this->blade('<x-ui.date-picker wire:model.live="visitDate" class="max-w-xs" aria-label="Data" />');

        $view->assertSeeInOrder(['x-modelable="value"', 'class="relative w-full min-w-0 max-w-xs" wire:model.live="visitDate"', 'data-date-picker-input', 'aria-label="Data"'], escape: false);

        expect(substr_count((string) $view, 'wire:model.live="visitDate"'))->toBe(1);
    });

    it('renders a clear button only when clearable', function (): void {
        $this->blade('<x-ui.date-picker clearable aria-label="Data" />')
            ->assertSee('aria-label="Cancella la data"', escape: false);

        $this->blade('<x-ui.date-picker aria-label="Data" />')
            ->assertDontSee('Cancella la data');
    });

    it('renders the inline variant as a named group with the chosen date', function (): void {
        $this->blade('<x-ui.date-picker inline name="slot" value="2026-11-12" aria-label="Giorno della visita" />')
            ->assertSee('data-ui-date-picker="inline"', escape: false)
            ->assertSee('role="group"', escape: false)
            ->assertSee('aria-label="Giorno della visita"', escape: false)
            ->assertSee('<input type="hidden" name="slot" value="2026-11-12"', escape: false)
            ->assertSee('aria-live="polite"', escape: false)
            ->assertSee('Data scelta: 12 novembre 2026')
            ->assertDontSee('role="dialog"', escape: false)
            ->assertDontSee('aria-haspopup', escape: false);

        $this->blade('<x-ui.date-picker inline aria-label="Giorno" />')
            ->assertSee('Nessuna data scelta');
    });

    it('translates its texts in English', function (): void {
        app()->setLocale('en');

        $this->blade('<x-ui.date-picker value="2026-11-12" clearable />')
            ->assertSee('locale="en"', escape: false)
            ->assertSee('value="November 12, 2026"', escape: false)
            ->assertSee('placeholder="Choose a date"', escape: false)
            ->assertSee('aria-label="Open the calendar"', escape: false)
            ->assertSee('aria-label="Calendar"', escape: false)
            ->assertSee('aria-label="Clear the date"', escape: false)
            ->assertSee('Previous month')
            ->assertSee('Next month')
            ->assertSee('Selected date: :date', escape: false);
    });

    it('loads Cally only through a dynamic import', function (): void {
        $script = (string) file_get_contents(resource_path('js/ui/pickers.js'));

        expect($script)->toContain("import('cally')")
            ->not->toMatch('/^import\s+[\'"]cally[\'"]/m')
            ->not->toMatch('/^import\s+.+\s+from\s+[\'"]cally[\'"]/m');
    });
});

describe('combobox id with server search', function (): void {
    /** Id dell'input role=combobox e il suo aria-controls in un frammento renderizzato. */
    $ids = static function (string $html): array {
        preg_match('/id="([^"]+)"\s+role="combobox"/', $html, $id);
        preg_match('/role="combobox".*?aria-controls="([^"]+)"/s', $html, $controls);

        return ['id' => $id[1] ?? null, 'controls' => $controls[1] ?? null];
    };

    it('does not derive the generated id from the options', function () use ($ids): void {
        $render = fn (array $options): array => $ids((string) $this->blade('<x-ui.combobox search="query" :options="$options" aria-label="Canile" />', ['options' => $options]));

        $first = $render(['faro' => 'Il Faro', 'querce' => 'Le Querce']);
        $second = $render(['coda' => 'Coda Felice']);

        expect($first['id'])->toStartWith('combobox-')
            ->and($second)->toBe($first)
            ->and($first['controls'])->toBe($first['id'].'-listbox');
    });

    it('keeps id, aria-controls and option keys when Livewire changes the options', function () use ($ids): void {
        $component = Livewire::test(new class extends Component
        {
            public string $query = '';

            public ?string $shelterId = null;

            /** @return array<string, string> */
            public function shelters(): array
            {
                $all = ['faro' => 'Il Faro', 'querce' => 'Le Querce', 'coda' => 'Coda Felice'];

                return array_filter($all, fn (string $label): bool => $this->query === '' || str_contains(mb_strtolower($label), mb_strtolower($this->query)));
            }

            public function render(): string
            {
                return <<<'BLADE'
                    <div><x-ui.combobox wire:model="shelterId" search="query" :options="$this->shelters()" aria-label="Canile" /></div>
                    BLADE;
            }
        });

        $before = $ids($component->html());
        $component->set('query', 'faro');
        $after = $ids($component->html());

        expect($before['id'])->not->toBeNull()
            ->and($after)->toBe($before)
            ->and($component->html())->toContain('wire:key="'.$before['id'].'-option-faro"')
            ->not->toContain('Le Querce');
    });
});

describe('field context', function (): void {
    it('links label, control and descriptions to ids that exist', function (string $control, string $tag, string $via): void {
        $links = fieldLinks((string) $this->blade('<x-ui.field name="visit" label="Visita" help="Aiuto" error="Errore" required>'.$control.'</x-ui.field>', ['options' => ['a' => 'A']]));

        expect($links['id'])->toBe('field-visit')
            ->and($links['via'])->toBe($via)
            ->and($links['control'])->toBe($tag)
            ->and($links['described'])->toBe(['field-visit-help', 'field-visit-error'])
            ->and($links['missing'])->toBe([]);
    })->with([
        'combobox' => ['<x-ui.combobox wire:model="visit" :options="$options" />', 'input', 'for'],
        'date-picker' => ['<x-ui.date-picker wire:model="visit" />', 'input', 'for'],
        'inline date-picker' => ['<x-ui.date-picker inline />', 'div', 'aria-labelledby'],
    ]);

    it('names the inline date-picker group with aria-labelledby, without a label for', function (): void {
        $this->blade('<x-ui.field name="slot" label="Giorno della visita"><x-ui.date-picker inline /></x-ui.field>')
            ->assertSee('<label id="field-slot-label" class=', escape: false)
            ->assertSee('role="group"', escape: false)
            ->assertSee('aria-labelledby="field-slot-label"', escape: false)
            ->assertDontSee('for="field-slot"', escape: false)
            ->assertDontSee('aria-label="Giorno della visita"', escape: false);
    });

    it('rejects an own id or name on the control inside a field', function (string $control, string $message): void {
        expect(fn () => $this->blade('<x-ui.field name="visit" label="Visita">'.$control.'</x-ui.field>', ['options' => []]))
            ->toThrow(Exception::class, $message);
    })->with([
        'combobox id' => ['<x-ui.combobox id="mine" :options="$options" />', 'Set the id with `for` on x-ui.field.'],
        'date-picker name' => ['<x-ui.date-picker name="other" />', 'Set `name` on x-ui.field'],
    ]);

    it('does not attach to an ancestor that only has a label', function (): void {
        registerLabelledWrapper();

        $this->blade('<x-uitest::labelled label="Filtri" name="filters"><x-ui.combobox :options="[]" aria-label="Razza" /><x-ui.date-picker aria-label="Data" /><x-ui.date-picker inline aria-label="Giorno" /></x-uitest::labelled>', ['options' => []])
            ->assertSee('id="combobox-', escape: false)
            ->assertSee('id="date-picker-', escape: false)
            ->assertSee('aria-label="Razza"', escape: false)
            ->assertDontSee('id="field-', escape: false)
            ->assertDontSee('aria-label="Filtri"', escape: false)
            ->assertDontSee('name="filters"', escape: false);
    });

    it('reads the standalone error from the wire:model key', function (string $control): void {
        $this->withViewErrors(['visitDate' => 'Data non valida'])
            ->blade($control, ['options' => []])
            ->assertSee('aria-invalid="true"', escape: false)
            ->assertSee('border-danger', escape: false);
    })->with([
        'combobox' => '<x-ui.combobox wire:model.live="visitDate" :options="$options" aria-label="Data" />',
        'date-picker' => '<x-ui.date-picker wire:model="visitDate" aria-label="Data" />',
    ]);
});

describe('styleguide page', function (): void {
    beforeEach(function (): void {
        app()->detectEnvironment(static fn (): string => 'local');
    });

    it('documents every pickers component', function (): void {
        $this->get(route('styleguide.group', 'pickers'))
            ->assertOk()
            ->assertSee('data-styleguide-component="combobox"', escape: false)
            ->assertSee('data-styleguide-component="date-picker"', escape: false)
            ->assertSee('data-demo="combobox-basic"', escape: false)
            ->assertSee('data-demo="date-inline"', escape: false)
            ->assertSee('Selettore di date');
    });

    it('is listed in the styleguide navigation', function (): void {
        $this->get(route('styleguide'))
            ->assertOk()
            ->assertSee(route('styleguide.group', 'pickers'), escape: false);
    });

    it('is translated in English', function (): void {
        $this->withSession(['locale' => 'en'])
            ->get(route('styleguide.group', 'pickers'))
            ->assertOk()
            ->assertSee('Date picker')
            ->assertSee('Open the calendar')
            ->assertSee('Search for a breed');
    });
});
