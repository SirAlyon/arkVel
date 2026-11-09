{{--
 | Campo di un modulo: label, controllo (nello slot), testo d'aiuto ed errore.
 |
 | - `name`: nome del controllo. Serve a leggere l'errore da $errors e a generare l'id
 |   del controllo (`field-<name>`, punti e parentesi diventano trattini). Il controllo
 |   figlio lo eredita: non serve ripeterlo;
 | - `for`: id personalizzato del controllo (es. lo stesso name due volte nella pagina);
 | - `help`: testo d'aiuto, collegato con aria-describedby;
 | - `error`: errore esplicito; se manca si legge `$errors->first($name)`. Ha icona e
 |   testo, è nel DOM già al render e il controllo riceve aria-invalid e aria-describedby;
 | - `required` / `optional`: indicatore testuale "(obbligatorio)" / "(facoltativo)";
 |   `required` arriva anche al controllo come attributo nativo;
 | - `hide-label`: label solo per gli screen reader (es. ricerca con icona);
 | - `group`: fieldset + legend per più controlli che si descrivono insieme (es. una
 |   fascia oraria "dalle / alle"). Aiuto ed errore sono del gruppo (aria-describedby
 |   sul fieldset). I controlli dentro un gruppo NON ereditano id e name: ognuno ha i
 |   suoi, con un aria-label (la legend dà il contesto).
 |
 | Classe: App\View\Components\Ui\Field (le prop sono le sue proprietà pubbliche). I
 | controlli figli (x-ui.input, x-ui.textarea, x-ui.select, x-ui.combobox,
 | x-ui.date-picker) ritrovano il field più vicino con @aware(['uiField' => null]): la
 | chiave esiste solo nei dati di x-ui.field, quindi un antenato con `label` (come un
 | dropdown) non conta. Il contesto è App\Support\Ui\FieldControl; ogni controllo lo
 | risolve con App\Support\Ui\ResolvedControl::resolve(): stessi id per costruzione.
 | Limite noto: @aware non attraversa un componente Livewire figlio dentro il field
 | (<x-ui.field><livewire:…/></x-ui.field>): il componente Livewire ha un render suo (e si
 | ri-renderizza da solo a ogni richiesta), quindi i controlli nella sua vista non vedono
 | il field e restano "da soli". Il field va messo nella vista del componente Livewire,
 | attorno al controllo.
 | La label ha l'id `<id>-label` e punta al controllo con `for`; un controllo non
 | etichettabile con `for` (il gruppo del date-picker inline) usa aria-labelledby su
 | quell'id e la label resta senza `for` (FieldControl::labelFor()).
 | Dentro un field il controllo NON ha id, name o required propri: se li ha diversi da
 | quelli del field il render lancia un'eccezione che rimanda a `for` / `name` /
 | `required` del field (un id proprio staccherebbe label e descrizioni in silenzio).
 | Niente x-ui.field annidati: conta il field più vicino.
 |
 | <x-ui.field name="email" :label="__('…')" :help="__('…')" required>
 |     <x-ui.input type="email" wire:model.blur="email" autocomplete="email" />
 | </x-ui.field>
 |
 | <x-ui.field group :label="__('…')" :help="__('…')">
 |     <x-ui.input type="time" name="from" :aria-label="__('…')" />
 |     <x-ui.input type="time" name="to" :aria-label="__('…')" />
 | </x-ui.field>
--}}
@use('App\Support\Ui\ControlAttributes')

@php
    $controlId = $context->id;
    $message = $context->message;
    $helpId = $context->helpId;
    $errorId = $context->errorId;
    $describedBy = ControlAttributes::describedBy($helpId, $errorId);
    $labelFor = $context->labelFor((string) $slot);

    $labelClasses = ['flex flex-wrap items-baseline gap-x-2 text-body font-semibold text-fg', 'sr-only' => $hideLabel];
@endphp

@if ($group)
    <fieldset
        id="{{ $controlId }}"
        aria-labelledby="{{ $controlId }}-legend"
        @if ($describedBy !== null) aria-describedby="{{ $describedBy }}" @endif
        data-ui-field="group"
        {{ $attributes->class('min-w-0') }}
    >
        <legend id="{{ $controlId }}-legend" @class([...$labelClasses, 'mb-3 p-0'])>
            <span>{{ $label }}</span>
            <x-ui.field.indicator :required="$required" :optional="$optional" />
        </legend>
        <div class="flex flex-col gap-4">
            @if ($helpId)
                <p id="{{ $helpId }}" class="-mt-2 text-small text-fg-muted">{{ $help }}</p>
            @endif
            {{ $slot }}
            @if ($errorId)
                <x-ui.field.error :id="$errorId" :message="$message" />
            @endif
        </div>
    </fieldset>
@else
    <div data-ui-field {{ $attributes->class('flex min-w-0 flex-col gap-2') }}>
        <label id="{{ $context->labelId }}"@if ($labelFor !== null) for="{{ $labelFor }}"@endif @class($labelClasses)>
            <span>{{ $label }}</span>
            <x-ui.field.indicator :required="$required" :optional="$optional" />
        </label>
        {{ $slot }}
        @if ($helpId)
            <p id="{{ $helpId }}" class="text-small text-fg-muted">{{ $help }}</p>
        @endif
        @if ($errorId)
            <x-ui.field.error :id="$errorId" :message="$message" />
        @endif
    </div>
@endif
