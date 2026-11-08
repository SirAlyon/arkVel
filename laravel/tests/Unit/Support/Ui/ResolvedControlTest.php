<?php

declare(strict_types=1);

/*
 | App\Support\Ui\ResolvedControl::resolve(): id, name, obbligo ed errore di un controllo,
 | dentro un x-ui.field o da solo. Il rendering vero è in tests/Feature/Ui/Components.
 */

use App\Support\Ui\ControlAttributes;
use App\Support\Ui\FieldControl;
use App\Support\Ui\ResolvedControl;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Illuminate\View\ComponentAttributeBag;

function resolvedControlErrors(array $messages): ViewErrorBag
{
    return (new ViewErrorBag)->put('default', new MessageBag($messages));
}

describe('inside a field', function (): void {
    $field = FieldControl::fromField(null, 'email', 'Email', 'Aiuto', 'Obbligatoria', true, null);

    it('takes id, name, required, error and label id from the field', function () use ($field): void {
        $control = ResolvedControl::resolve($field, new ComponentAttributeBag(['wire:model' => 'form.email']), resolvedControlErrors(['form.email' => 'Ignorato']), prefix: 'combobox');

        expect($control->id)->toBe('field-email')
            ->and($control->name)->toBe('email')
            ->and($control->required)->toBeTrue()
            ->and($control->message)->toBe('Obbligatoria')
            ->and($control->invalid)->toBeTrue()
            ->and($control->labelId)->toBe('field-email-label');
    });

    it('accepts own values equal to the field ones', function () use ($field): void {
        $control = ResolvedControl::resolve($field, new ComponentAttributeBag(['id' => 'field-email', 'name' => 'email', 'required' => true]), null);

        expect($control->id)->toBe('field-email');
    });

    it('rejects an own id, name or required that differ from the field', function (array $attributes, string $message): void {
        $optional = FieldControl::fromField(null, 'email', 'Email', null, null, false, null);

        expect(fn () => ResolvedControl::resolve($optional, new ComponentAttributeBag($attributes), null))
            ->toThrow(InvalidArgumentException::class, $message);
    })->with([
        'id' => [['id' => 'mine'], 'Set the id with `for` on x-ui.field.'],
        'name' => [['name' => 'other'], 'Set `name` on x-ui.field'],
        'required' => [['required' => true], 'Set `required` on x-ui.field.'],
    ]);

    it('joins own description, extra ids, help and error in this order', function () use ($field): void {
        $control = ResolvedControl::resolve($field, new ComponentAttributeBag(['aria-describedby' => 'own']), null);

        expect($control->describedBy('field-email-prefix', null, ''))->toBe('own field-email-prefix field-email-help field-email-error');
    });

    it('lets the control force the invalid state', function () use ($field): void {
        expect(ResolvedControl::resolve($field, new ComponentAttributeBag, null, invalid: false)->invalid)->toBeFalse();
    });
});

describe('standalone', function (): void {
    it('uses own id, name and required, without label id', function (): void {
        $control = ResolvedControl::resolve(null, new ComponentAttributeBag(['id' => 'q', 'name' => 'query', 'required' => true]), null, prefix: 'combobox');

        expect($control->id)->toBe('q')
            ->and($control->name)->toBe('query')
            ->and($control->required)->toBeTrue()
            ->and($control->labelId)->toBeNull()
            ->and($control->message)->toBeNull()
            ->and($control->invalid)->toBeFalse()
            ->and($control->describedBy())->toBeNull();
    });

    it('reads the error with the name, then with the wire:model key', function (): void {
        $errors = resolvedControlErrors(['query' => 'Dal name', 'form.q' => 'Da wire:model']);

        expect(ResolvedControl::resolve(null, new ComponentAttributeBag(['name' => 'query', 'wire:model' => 'form.q']), $errors)->message)->toBe('Dal name')
            ->and(ResolvedControl::resolve(null, new ComponentAttributeBag(['wire:model.live' => 'form.q']), $errors)->message)->toBe('Da wire:model')
            ->and(ResolvedControl::resolve(null, new ComponentAttributeBag(['wire:model' => 'form.q']), $errors)->invalid)->toBeTrue()
            ->and(ResolvedControl::resolve(null, new ComponentAttributeBag(['wire:model' => 'form.q']), $errors, invalid: false)->invalid)->toBeFalse()
            ->and(ResolvedControl::resolve(null, new ComponentAttributeBag, null, invalid: true)->invalid)->toBeTrue();
    });

    it('has no generated id without a prefix', function (): void {
        expect(ResolvedControl::resolve(null, new ComponentAttributeBag(['name' => 'q', 'aria-label' => 'Cerca']), null)->id)->toBeNull();
    });

    it('generates the id from the name slug with a prefix', function (): void {
        expect(ResolvedControl::resolve(null, new ComponentAttributeBag(['name' => 'form.town']), null, prefix: 'combobox', seed: 'ignored')->id)->toBe('combobox-form-town');
    });

    it('hashes a stable seed: wire:model, then the seed, then aria-label, then aria-labelledby', function (array $attributes, ?string $seed, string $expected): void {
        expect(ResolvedControl::resolve(null, new ComponentAttributeBag($attributes), null, prefix: 'combobox', seed: $seed)->id)
            ->toBe(ControlAttributes::hashedId('combobox', $expected));
    })->with([
        'wire:model' => [['wire:model.live' => 'shelterId', 'aria-label' => 'Canile'], 'query', 'shelterId'],
        'seed' => [['aria-label' => 'Canile'], 'query', 'query'],
        'empty seed skipped' => [['aria-label' => 'Canile'], '', 'Canile'],
        'aria-label' => [['aria-label' => 'Canile'], null, 'Canile'],
        'aria-labelledby' => [['aria-labelledby' => 'title'], null, 'title'],
        'nothing' => [[], null, ''],
    ]);
});
