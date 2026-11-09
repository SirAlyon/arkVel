<?php

declare(strict_types=1);

/*
 | Componenti x-ui del gruppo "forms": field, input, textarea, select, checkbox,
 | checkbox-group, radio, radio-group, switch. Test di rendering (markup, ARIA, id
 | collegati, errori, traduzioni). Comportamento nel browser:
 | tests/Browser/Components/FormsComponentsTest.php. Le regole di id ed errore sono in
 | App\Support\Ui\FieldControl (test unit in tests/Unit/Support/Ui).
 */

use Illuminate\Support\Facades\Blade;

// Helper condivisi con PickersComponentsTest (stessa definizione, chi carica prima la dichiara).
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

describe('field', function (): void {
    it('links the label to the control through a generated id', function (): void {
        $this->blade('<x-ui.field name="form.email" label="Email"><x-ui.input type="email" wire:model="form.email" /></x-ui.field>')
            ->assertSee('<label id="field-form-email-label" for="field-form-email"', escape: false)
            ->assertSee('id="field-form-email"', escape: false)
            ->assertSee('name="form.email"', escape: false)
            ->assertSee('wire:model="form.email"', escape: false)
            ->assertDontSee('aria-describedby', escape: false)
            ->assertDontSee('aria-invalid', escape: false);
    });

    it('describes the control with its help text', function (): void {
        $this->blade('<x-ui.field name="name" label="Nome" help="Come sui documenti"><x-ui.input /></x-ui.field>')
            ->assertSee('<p id="field-name-help"', escape: false)
            ->assertSee('aria-describedby="field-name-help"', escape: false)
            ->assertSee('Come sui documenti');
    });

    it('shows an explicit error with icon, prefix and aria-invalid', function (): void {
        $this->blade('<x-ui.field name="email" label="Email" help="Aiuto" error="Email non valida"><x-ui.input /></x-ui.field>')
            ->assertSee('aria-describedby="field-email-help field-email-error"', escape: false)
            ->assertSee('aria-invalid="true"', escape: false)
            ->assertSee('border-danger', escape: false)
            ->assertSeeInOrder(['id="field-email-error"', 'data-icon="circle-alert"', 'Errore:', 'Email non valida'], escape: false);
    });

    it('reads the error from the validation bag by name', function (): void {
        $this->withViewErrors(['email' => 'Il campo email è obbligatorio.'])
            ->blade('<x-ui.field name="email" label="Email"><x-ui.input /></x-ui.field>')
            ->assertSee('aria-describedby="field-email-error"', escape: false)
            ->assertSee('aria-invalid="true"', escape: false)
            ->assertSee('Il campo email è obbligatorio.');
    });

    it('spells out required and optional and makes the control required', function (): void {
        $this->blade('<x-ui.field name="email" label="Email" required><x-ui.input /></x-ui.field>')
            ->assertSee('data-field-indicator="required"', escape: false)
            ->assertSee('(obbligatorio)')
            ->assertSee(' required', escape: false);

        $this->blade('<x-ui.field name="phone" label="Telefono" optional><x-ui.input /></x-ui.field>')
            ->assertSee('(facoltativo)')
            ->assertDontSee(' required ', escape: false);
    });

    it('uses a custom control id with for', function (): void {
        $this->blade('<x-ui.field name="email" for="apply-email" label="Email" error="No"><x-ui.input /></x-ui.field>')
            ->assertSee('<label id="apply-email-label" for="apply-email"', escape: false)
            ->assertSee('id="apply-email"', escape: false)
            ->assertSee('aria-describedby="apply-email-error"', escape: false);
    });

    it('keeps a hidden label for screen readers', function (): void {
        $this->blade('<x-ui.field name="q" label="Cerca" hide-label><x-ui.input type="search" /></x-ui.field>')
            ->assertSee('sr-only', escape: false)
            ->assertSee('Cerca');
    });

    it('renders a group as fieldset and legend without passing id and name down', function (): void {
        $this->blade('<x-ui.field group name="slot" label="Fascia oraria" help="Dalle 9 alle 18" error="Orario non valido">
            <x-ui.input type="time" name="from" aria-label="Dalle" />
        </x-ui.field>')
            ->assertSee('<fieldset', escape: false)
            ->assertSee('id="field-slot"', escape: false)
            ->assertSee('aria-labelledby="field-slot-legend"', escape: false)
            ->assertSee('aria-describedby="field-slot-help field-slot-error"', escape: false)
            ->assertSee('<legend id="field-slot-legend"', escape: false)
            ->assertSee('name="from"', escape: false)
            ->assertDontSee('name="slot"', escape: false)
            ->assertDontSee('aria-invalid', escape: false);
    });

    it('translates indicators and the error prefix', function (): void {
        app()->setLocale('en');

        $this->blade('<x-ui.field name="email" label="Email" required error="Wrong"><x-ui.input /></x-ui.field>')
            ->assertSee('(required)')
            ->assertSee('Error:');
    });
});

describe('field context', function (): void {
    it('links label, control and descriptions to ids that exist', function (string $control, string $tag): void {
        $links = fieldLinks((string) $this->blade('<x-ui.field name="form.notes" label="Note" help="Aiuto" error="Errore" required>'.$control.'</x-ui.field>', ['options' => ['a' => 'A']]));

        expect($links['for'])->toBe('field-form-notes')
            ->and($links['control'])->toBe($tag)
            ->and($links['described'])->toBe(['field-form-notes-help', 'field-form-notes-error'])
            ->and($links['missing'])->toBe([]);
    })->with([
        'input' => ['<x-ui.input wire:model="form.notes" />', 'input'],
        'textarea' => ['<x-ui.textarea wire:model="form.notes" />', 'textarea'],
        'select' => ['<x-ui.select :options="$options" />', 'select'],
    ]);

    it('accepts an own id equal to the one given with for', function (): void {
        $links = fieldLinks((string) $this->blade('<x-ui.field name="email" for="my-email" label="Email" help="Aiuto"><x-ui.input id="my-email" name="email" /></x-ui.field>'));

        expect($links['for'])->toBe('my-email')
            ->and($links['control'])->toBe('input')
            ->and($links['missing'])->toBe([]);
    });

    it('rejects an own id on the control, which would detach the label', function (string $control): void {
        expect(fn () => $this->blade('<x-ui.field name="email" label="Email">'.$control.'</x-ui.field>', ['options' => []]))
            ->toThrow(Exception::class, 'Set the id with `for` on x-ui.field.');
    })->with([
        'input' => '<x-ui.input id="my-email" />',
        'textarea' => '<x-ui.textarea id="my-email" />',
        'select' => '<x-ui.select id="my-email" :options="$options" />',
    ]);

    it('rejects an own name or an own required on the control', function (): void {
        expect(fn () => $this->blade('<x-ui.field name="email" label="Email"><x-ui.input name="email_confirmation" /></x-ui.field>'))
            ->toThrow(Exception::class, 'Set `name` on x-ui.field');

        expect(fn () => $this->blade('<x-ui.field name="email" label="Email"><x-ui.input required /></x-ui.field>'))
            ->toThrow(Exception::class, 'Set `required` on x-ui.field.');
    });

    it('does not attach to an ancestor that only has a label', function (): void {
        registerLabelledWrapper();

        $this->blade('<x-uitest::labelled label="Menu" name="menu"><x-ui.input aria-label="Cerca" /><x-ui.select aria-label="Ordina" /><x-ui.textarea aria-label="Note" /></x-uitest::labelled>')
            ->assertSee('data-labelled="Menu"', escape: false)
            ->assertDontSee('id="field-', escape: false)
            ->assertDontSee('name="menu"', escape: false)
            ->assertDontSee('aria-describedby', escape: false);
    });

    it('still attaches to a field around that ancestor', function (): void {
        registerLabelledWrapper();

        $links = fieldLinks((string) $this->blade('<x-ui.field name="q" label="Cerca" help="Aiuto"><x-uitest::labelled label="Menu"><x-ui.input /></x-uitest::labelled></x-ui.field>'));

        expect($links['for'])->toBe('field-q')
            ->and($links['control'])->toBe('input')
            ->and($links['missing'])->toBe([]);
    });
});

describe('input', function (): void {
    it('renders a 48 px pill with the class on the box and the rest on the input', function (): void {
        $this->blade('<x-ui.input aria-label="Cerca" wire:model.live="q" placeholder="Nome" class="max-w-md" />')
            ->assertSee('data-ui-input="text"', escape: false)
            ->assertSee('min-h-12', escape: false)
            ->assertSee('border-line-strong', escape: false)
            ->assertSee('max-w-md', escape: false)
            ->assertSeeInOrder(['max-w-md', '<input', 'aria-label="Cerca"', 'wire:model.live="q"'], escape: false)
            ->assertDontSee('id="', escape: false);
    });

    it('does not take the context of a field when used alone', function (): void {
        $this->blade('<x-ui.input name="q" aria-label="Cerca" />')
            ->assertSee('name="q"', escape: false)
            ->assertDontSee('id="field-q"', escape: false);
    });

    it('marks a standalone input invalid from the validation bag', function (): void {
        $this->withViewErrors(['q' => 'Troppo corto'])
            ->blade('<x-ui.input name="q" aria-label="Cerca" />')
            ->assertSee('aria-invalid="true"', escape: false)
            ->assertDontSee('Troppo corto');
    });

    it('reads the standalone error from the wire:model key', function (string $control): void {
        $this->withViewErrors(['q' => 'Troppo corto'])
            ->blade($control, ['options' => []])
            ->assertSee('aria-invalid="true"', escape: false)
            ->assertSee('border-danger', escape: false)
            ->assertDontSee('Troppo corto');
    })->with([
        'input' => '<x-ui.input wire:model="q" aria-label="Cerca" />',
        'input live' => '<x-ui.input wire:model.live.debounce="q" aria-label="Cerca" />',
        'textarea' => '<x-ui.textarea wire:model="q" aria-label="Cerca" />',
        'select' => '<x-ui.select wire:model="q" aria-label="Cerca" :options="$options" />',
    ]);

    it('renders leading and trailing icons', function (): void {
        $this->blade('<x-ui.input type="email" icon="mail" icon-trailing="check" aria-label="Email" />')
            ->assertSeeInOrder(['data-icon="mail"', '<input', 'data-icon="check"'], escape: false)
            ->assertSee('ps-2 pe-2', escape: false);
    });

    it('adds prefix and suffix to the description', function (): void {
        $this->blade('<x-ui.field name="amount" label="Importo" help="Minimo 1"><x-ui.input type="number" prefix="€" suffix="al mese" /></x-ui.field>')
            ->assertSee('id="field-amount-prefix"', escape: false)
            ->assertSee('id="field-amount-suffix"', escape: false)
            ->assertSee('aria-describedby="field-amount-prefix field-amount-suffix field-amount-help"', escape: false);
    });

    it('adds a show password toggle', function (): void {
        $this->blade('<x-ui.field name="password" label="Password"><x-ui.input type="password" /></x-ui.field>')
            ->assertSee('type="password"', escape: false)
            ->assertSee('x-bind:type="revealed ? \'text\' : \'password\'"', escape: false)
            ->assertSee('data-password-toggle', escape: false)
            ->assertSee('aria-pressed="false"', escape: false)
            ->assertSee('aria-label="Mostra password"', escape: false)
            ->assertSee('aria-controls="field-password"', escape: false)
            ->assertSee('size-11', escape: false)
            ->assertSeeInOrder(['data-icon="eye"', 'data-icon="eye-off"'], escape: false);
    });

    it('translates the password toggle', function (): void {
        app()->setLocale('en');

        $this->blade('<x-ui.input type="password" aria-label="Password" />')->assertSee('aria-label="Show password"', escape: false);
    });

    it('styles disabled and readonly without relying on colour', function (): void {
        $this->blade('<x-ui.input aria-label="A" disabled />')
            ->assertSee('disabled', escape: false)
            ->assertSee('has-[input:disabled]:bg-surface', escape: false)
            ->assertDontSee('hover:border-fg', escape: false);

        $this->blade('<x-ui.input aria-label="B" readonly value="ARK-1" />')
            ->assertSee('readonly', escape: false)
            ->assertSee('border-dashed bg-surface', escape: false)
            ->assertDontSee('bg-canvas', escape: false);

        $this->blade('<x-ui.textarea aria-label="C" readonly>ARK-1</x-ui.textarea>')
            ->assertSee('border-dashed bg-surface', escape: false)
            ->assertDontSee('bg-canvas', escape: false);
    });
});

describe('textarea', function (): void {
    it('renders inside a field with the slot as value', function (): void {
        $this->blade('<x-ui.field name="notes" label="Note" required><x-ui.textarea rows="3" wire:model="notes">{{ $value }}</x-ui.textarea></x-ui.field>', ['value' => 'Cani & gatti'])
            ->assertSee('id="field-notes"', escape: false)
            ->assertSee('rows="3"', escape: false)
            ->assertSee('rounded-media', escape: false)
            ->assertSee('wire:model="notes"', escape: false)
            ->assertSee('>Cani &amp; gatti</textarea>', escape: false);
    });

    it('counts characters with an accessible limit and a polite live region', function (): void {
        $this->blade('<x-ui.field name="story" label="Storia"><x-ui.textarea maxlength="120" counter>Ciao</x-ui.textarea></x-ui.field>')
            ->assertSee('x-data="uiCharCounter({ max: 120', escape: false)
            ->assertSee('aria-describedby="field-story-limit"', escape: false)
            ->assertSee('Massimo 120 caratteri.')
            ->assertSee('4 / 120')
            ->assertSee('aria-live="polite"', escape: false)
            ->assertSee('maxlength="120"', escape: false);
    });

    it('ignores the counter without maxlength and grows with autoresize', function (): void {
        $this->blade('<x-ui.textarea counter autoresize aria-label="Note" />')
            ->assertDontSee('uiCharCounter', escape: false)
            ->assertSee('field-sizing-content', escape: false);
    });

    it('translates the counter messages', function (): void {
        app()->setLocale('en');

        $this->blade('<x-ui.textarea maxlength="50" counter aria-label="Story" />')
            ->assertSee('Up to 50 characters.')
            ->assertSee('characters left', escape: false);
    });
});

describe('select', function (): void {
    it('renders options, groups, placeholder and the selected value', function (): void {
        $this->blade('<x-ui.field name="province" label="Provincia"><x-ui.select :options="$options" placeholder="Scegli" value="RM" /></x-ui.field>', [
            'options' => ['MI' => 'Milano', 'Centro' => ['RM' => 'Roma', 'FI' => 'Firenze']],
        ])
            ->assertSee('id="field-province"', escape: false)
            ->assertSee('appearance-none', escape: false)
            ->assertSee('data-icon="chevron-down"', escape: false)
            ->assertSeeInOrder(['<option value="" >Scegli</option>', '<option value="MI" >Milano</option>', '<optgroup label="Centro">', '<option value="RM" selected>Roma</option>'], escape: false);
    });

    it('accepts options in the slot and a translated default placeholder', function (): void {
        app()->setLocale('en');

        $this->blade('<x-ui.select aria-label="Size" placeholder><option value="s">Small</option></x-ui.select>')
            ->assertSee('Choose an option')
            ->assertSee('<option value="s">Small</option>', escape: false);
    });

    it('shows the error state', function (): void {
        $this->blade('<x-ui.field name="species" label="Specie" error="Scegli una specie" required><x-ui.select :options="[\'dog\' => \'Cane\']" placeholder /></x-ui.field>')
            ->assertSee('aria-invalid="true"', escape: false)
            ->assertSee('border-danger', escape: false)
            ->assertSee(' required', escape: false);
    });
});

describe('checkbox', function (): void {
    it('wraps a native checkbox in its label', function (): void {
        $this->blade('<x-ui.checkbox name="updates" wire:model="updates" description="Una a settimana" class="mt-4">Avvisami</x-ui.checkbox>')
            ->assertSeeInOrder(['mt-4', '<label', 'type="checkbox"', 'Avvisami', '</label>', 'Una a settimana'], escape: false)
            ->assertSee('id="field-updates"', escape: false)
            ->assertSee('name="updates"', escape: false)
            ->assertSee('aria-describedby="field-updates-description"', escape: false)
            ->assertSee('appearance-none', escape: false)
            ->assertSee('min-h-11', escape: false)
            ->assertSee('data-icon="check"', escape: false);
    });

    it('shows its own error, read from the validation bag through wire:model', function (): void {
        $this->withViewErrors(['form.privacy' => 'Devi accettare.'])
            ->blade('<x-ui.checkbox wire:model="form.privacy" required>Accetto</x-ui.checkbox>')
            ->assertSee('id="field-form-privacy"', escape: false)
            ->assertSee('aria-invalid="true"', escape: false)
            ->assertSee('aria-describedby="field-form-privacy-error"', escape: false)
            ->assertSee('(obbligatorio)')
            ->assertSee('Devi accettare.');
    });

    it('sets the indeterminate state as a DOM property', function (): void {
        $this->blade('<x-ui.checkbox name="all" indeterminate checked>Tutti</x-ui.checkbox>')
            ->assertSee('x-init="$el.indeterminate = true"', escape: false)
            ->assertSee('checked', escape: false)
            ->assertSee('data-icon="minus"', escape: false);
    });
});

describe('checkbox group', function (): void {
    it('renders a fieldset whose boxes share the name as an array', function (): void {
        $this->blade('<x-ui.checkbox-group name="species" legend="Specie" help="Più di una" :options="$options" :value="[\'cats\']" wire:model="species" orientation="horizontal" />', [
            'options' => ['dogs' => 'Cani', 'cats' => ['label' => 'Gatti', 'description' => 'Anche anziani']],
        ])
            ->assertSee('<fieldset', escape: false)
            ->assertSee('data-ui-checkbox-group', escape: false)
            ->assertSee('<legend id="field-species-legend"', escape: false)
            ->assertSee('aria-describedby="field-species-help"', escape: false)
            ->assertSee('flex-row flex-wrap', escape: false)
            ->assertSee('id="field-species-dogs"', escape: false)
            ->assertSee('name="species[]"', escape: false)
            ->assertSee('wire:model="species"', escape: false)
            ->assertSee('aria-describedby="field-species-cats-description"', escape: false)
            ->assertSeeInOrder(['value="dogs"', 'checked', 'value="cats"'], escape: false);
    });

    it('shows the error once, on the group', function (): void {
        $this->withViewErrors(['days' => 'Scegli almeno un giorno.'])
            ->blade('<x-ui.checkbox-group name="days" legend="Giorni"><x-ui.checkbox value="sat">Sabato</x-ui.checkbox></x-ui.checkbox-group>')
            ->assertSee('aria-describedby="field-days-error"', escape: false)
            ->assertSee('name="days[]"', escape: false)
            ->assertSee('border-danger', escape: false)
            ->assertSee('aria-invalid="true"', escape: false)
            ->assertDontSee('field-days-sat-error', escape: false);
    });
});

describe('radio group', function (): void {
    it('renders a radiogroup whose options inherit name and required', function (): void {
        $this->blade('<x-ui.radio-group name="home" legend="Casa" :options="$options" value="flat" wire:model="home" required />', [
            'options' => ['house' => 'Casa', 'flat' => ['label' => 'Appartamento', 'description' => 'Con balcone']],
        ])
            ->assertSee('role="radiogroup"', escape: false)
            ->assertSee('aria-labelledby="field-home-legend"', escape: false)
            ->assertSee('aria-required="true"', escape: false)
            ->assertSee('(obbligatorio)')
            ->assertSee('id="field-home-house"', escape: false)
            ->assertSee('name="home"', escape: false)
            ->assertSee('wire:model="home"', escape: false)
            ->assertSee('aria-describedby="field-home-flat-description"', escape: false)
            ->assertSeeInOrder(['value="flat"', 'checked'], escape: false)
            ->assertDontSee('aria-invalid', escape: false);
    });

    it('accepts radios in the slot and marks the group invalid', function (): void {
        $this->blade('<x-ui.radio-group name="size" legend="Taglia" error="Scegli una taglia"><x-ui.radio value="s">Piccola</x-ui.radio><x-ui.radio value="m" disabled>Media</x-ui.radio></x-ui.radio-group>')
            ->assertSee('aria-invalid="true"', escape: false)
            ->assertSee('aria-describedby="field-size-error"', escape: false)
            ->assertSee('id="field-size-s"', escape: false)
            ->assertSee('name="size"', escape: false)
            ->assertSee('border-danger', escape: false)
            ->assertSee('disabled', escape: false)
            ->assertSee('Scegli una taglia');
    });
});

describe('switch', function (): void {
    it('renders a native checkbox with the switch role', function (): void {
        $this->blade('<x-ui.switch name="newsletter" wire:model.live="newsletter" description="Una al mese" checked>Newsletter</x-ui.switch>')
            ->assertSee('type="checkbox"', escape: false)
            ->assertSee('role="switch"', escape: false)
            ->assertSee('id="field-newsletter"', escape: false)
            ->assertSee('aria-describedby="field-newsletter-description"', escape: false)
            ->assertSee('checked', escape: false)
            ->assertSee('ease-pop', escape: false)
            ->assertSee('peer-checked:translate-x-5', escape: false)
            ->assertDontSee('aria-checked', escape: false);
    });

    it('can be required, with the textual indicator', function (): void {
        $html = (string) $this->blade('<x-ui.switch name="terms" required>Accetto</x-ui.switch>');

        expect($html)->toMatch('/role="switch"[^>]*\srequired[\s>]/')
            ->toContain('data-field-indicator="required"')
            ->toContain('(obbligatorio)');
    });

    it('shows an error', function (): void {
        $this->blade('<x-ui.switch name="share" error="Serve per inviare">Condividi</x-ui.switch>')
            ->assertSee('aria-invalid="true"', escape: false)
            ->assertSee('aria-describedby="field-share-error"', escape: false)
            ->assertSee('Serve per inviare');
    });
});

describe('styleguide page', function (): void {
    beforeEach(function (): void {
        app()->detectEnvironment(static fn (): string => 'local');
    });

    it('documents every component of the group', function (): void {
        $response = $this->get(route('styleguide.group', 'forms'))->assertOk();

        foreach (['field', 'input', 'textarea', 'select', 'checkbox', 'checkbox-group', 'radio', 'radio-group', 'switch', 'example-form'] as $component) {
            $response->assertSee('data-styleguide-component="'.$component.'"', escape: false);
        }

        $response->assertDontSee('data-demo="error-summary"', escape: false)
            ->assertSee('#example-form-errors" novalidate', escape: false);
    });

    it('shows the example form errors with a summary after submit', function (): void {
        $this->get(route('styleguide.group', ['forms', 'demo' => 'errors']))
            ->assertOk()
            ->assertSee('data-demo="error-summary"', escape: false)
            ->assertSee('id="example-form-errors"', escape: false)
            ->assertSee('href="#apply-email"', escape: false)
            ->assertSee('id="apply-email-error"', escape: false)
            ->assertSee('Inserisci nome e cognome.');
    });

    it('is translated in English', function (): void {
        $this->withSession(['locale' => 'en'])
            ->get(route('styleguide.group', 'forms'))
            ->assertOk()
            ->assertSee('Example form: application')
            ->assertSee('Show password');
    });
});
