<?php

declare(strict_types=1);

/*
 | App\Support\Ui\FieldControl: contesto di un x-ui.field (id, label, errore, descrizioni).
 | Il rendering vero dei componenti è in tests/Feature/Ui/Components.
 */

use App\Support\Ui\FieldControl;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

function fieldControlErrors(array $messages): ViewErrorBag
{
    return (new ViewErrorBag)->put('default', new MessageBag($messages));
}

describe('fromField', function (): void {
    it('builds the context with help and error ids', function (): void {
        $context = FieldControl::fromField(null, 'email', 'Email', 'Aiuto', null, true, fieldControlErrors(['email' => 'Obbligatorio']));

        expect($context->id)->toBe('field-email')
            ->and($context->name)->toBe('email')
            ->and($context->label)->toBe('Email')
            ->and($context->labelId)->toBe('field-email-label')
            ->and($context->required)->toBeTrue()
            ->and($context->message)->toBe('Obbligatorio')
            ->and($context->helpId)->toBe('field-email-help')
            ->and($context->errorId)->toBe('field-email-error');
    });

    it('has no description ids without help and error', function (): void {
        $context = FieldControl::fromField('custom', null, 'Nome', null, null, false, null);

        expect($context->id)->toBe('custom')
            ->and($context->name)->toBeNull()
            ->and($context->message)->toBeNull()
            ->and($context->helpId)->toBeNull()
            ->and($context->errorId)->toBeNull();
    });
});

describe('labelFor', function (): void {
    $context = FieldControl::fromField(null, 'slot', 'Giorno', null, null, false, null);

    it('points the label at a labelable control with for', function () use ($context): void {
        expect($context->labelFor('<input id="field-slot" />'))->toBe('field-slot');
    });

    it('drops for when the control references the label with aria-labelledby', function () use ($context): void {
        expect($context->labelFor('<div id="field-slot" role="group" aria-labelledby="field-slot-label"></div>'))->toBeNull();
    });
});

describe('ensureCompatible', function (): void {
    $context = FieldControl::fromField(null, 'email', 'Email', null, null, false, null);

    it('accepts no own values or the same values as the field', function () use ($context): void {
        $context->ensureCompatible(null, null, false);
        $context->ensureCompatible('field-email', 'email', false);
        FieldControl::fromField(null, 'email', 'Email', null, null, true, null)->ensureCompatible(null, null, true);

        expect(true)->toBeTrue();
    });

    it('rejects an own id that would detach label and descriptions', function () use ($context): void {
        expect(fn () => $context->ensureCompatible('my-email', null, false))
            ->toThrow(InvalidArgumentException::class, 'Set the id with `for` on x-ui.field.');
    });

    it('rejects an own name', function () use ($context): void {
        expect(fn () => $context->ensureCompatible(null, 'email_confirmation', false))
            ->toThrow(InvalidArgumentException::class, 'Set `name` on x-ui.field');
    });

    it('rejects required on the control of an optional field', function () use ($context): void {
        expect(fn () => $context->ensureCompatible(null, null, true))
            ->toThrow(InvalidArgumentException::class, 'Set `required` on x-ui.field.');
    });
});
