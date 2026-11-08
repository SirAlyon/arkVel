<?php

declare(strict_types=1);

/*
 | App\Support\Ui\ControlAttributes: regole pure di id, errore e aria-describedby dei
 | controlli di modulo. Il rendering vero dei componenti è in tests/Feature/Ui/Components.
 */

use App\Support\Ui\ControlAttributes;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Illuminate\View\ComponentAttributeBag;

function controlAttributesErrors(array $messages): ViewErrorBag
{
    return (new ViewErrorBag)->put('default', new MessageBag($messages));
}

describe('fieldId', function (): void {
    it('prefers for, then the name, then a hash of the label', function (): void {
        expect(ControlAttributes::fieldId('apply-email', 'email', 'Email'))->toBe('apply-email')
            ->and(ControlAttributes::fieldId(null, 'form.items[0]', 'Email'))->toBe('field-form-items-0')
            ->and(ControlAttributes::fieldId('', '', 'Email'))->toBe('field-'.substr(md5('Email'), 0, 8))
            ->and(ControlAttributes::fieldId(null, null, null))->toBe('field-'.substr(md5(''), 0, 8));
    });

    it('slugs names and hashes seeds', function (): void {
        expect(ControlAttributes::slug('form.privacy'))->toBe('form-privacy')
            ->and(ControlAttributes::slug('Giorni[Sabato]'))->toBe('giorni-sabato')
            ->and(ControlAttributes::hashedId('switch', 'seed'))->toBe('switch-'.substr(md5('seed'), 0, 8));
    });
});

describe('errors', function (): void {
    it('uses the own name, then the first wire:model as error key', function (): void {
        $bag = new ComponentAttributeBag(['wire:model.live' => 'form.q', 'wire:model' => 'other']);

        expect(ControlAttributes::errorKey('q', $bag))->toBe('q')
            ->and(ControlAttributes::errorKey('', $bag))->toBe('form.q')
            ->and(ControlAttributes::errorKey(null, new ComponentAttributeBag(['aria-label' => 'Cerca'])))->toBeNull();
    });

    it('prefers the explicit error, then reads the bag', function (): void {
        $errors = controlAttributesErrors(['email' => ['Email non valida', 'Seconda']]);

        expect(ControlAttributes::message('Esplicito', 'email', $errors))->toBe('Esplicito')
            ->and(ControlAttributes::message('', 'email', $errors))->toBe('Email non valida')
            ->and(ControlAttributes::message(null, 'phone', $errors))->toBeNull()
            ->and(ControlAttributes::message(null, null, $errors))->toBeNull()
            ->and(ControlAttributes::message(null, 'email', null))->toBeNull();
    });

    it('joins the filled ids for aria-describedby', function (): void {
        expect(ControlAttributes::describedBy('own', null, '', 'field-a-help', 'field-a-error'))->toBe('own field-a-help field-a-error')
            ->and(ControlAttributes::describedBy(null, ''))->toBeNull()
            ->and(ControlAttributes::describedBy())->toBeNull();
    });
});

describe('text', function (): void {
    it('keeps textual attribute values and drops empty or non-textual ones', function (): void {
        expect(ControlAttributes::text('Cerca'))->toBe('Cerca')
            ->and(ControlAttributes::text(3))->toBe('3')
            ->and(ControlAttributes::text(str('Nome')))->toBe('Nome')
            ->and(ControlAttributes::text(''))->toBeNull()
            ->and(ControlAttributes::text(true))->toBeNull()
            ->and(ControlAttributes::text(null))->toBeNull();
    });
});
