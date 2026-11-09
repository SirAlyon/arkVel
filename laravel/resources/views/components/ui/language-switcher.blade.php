{{--
 | Selettore della lingua: un controllo segmentato di bottoni, uno per lingua
 | (App\Enums\AppLocale). Ogni bottone invia un POST a locale.update (con @csrf), che salva
 | la scelta in sessione e torna alla pagina: funziona anche senza JavaScript.
 |
 | - la lingua attiva ha aria-pressed="true" (navy pieno); ogni opzione ha `lang` con il
 |   suo codice, così lo screen reader pronuncia l'endonimo nella lingua giusta;
 | - `compact`: mostra il codice (IT, EN) invece dell'endonimo, che resta nel nome
 |   accessibile dopo il codice visibile (WCAG 2.5.3, label in name);
 | - target da 44 px; `class` va sul gruppo.
 |
 | <x-ui.language-switcher />
 | <x-ui.language-switcher compact />
--}}
@use('App\Enums\AppLocale')

@props([
    'compact' => false,
])

@php
    $locale = app()->getLocale();
@endphp

<div
    role="group"
    aria-label="{{ __('public.language.label') }}"
    data-language-switcher
    {{ $attributes->class('inline-flex shrink-0 gap-1 rounded-full bg-surface p-1') }}
>
    @foreach (AppLocale::cases() as $option)
        <form method="POST" action="{{ route('locale.update', $option) }}" class="contents">
            @csrf
            <button
                type="submit"
                lang="{{ $option->value }}"
                aria-pressed="{{ $option->value === $locale ? 'true' : 'false' }}"
                data-locale-option="{{ $option->value }}"
                class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-full px-3 text-small font-semibold text-fg transition-colors duration-(--duration-fast) ease-out hover:bg-surface-raised aria-pressed:bg-primary aria-pressed:text-on-primary"
            >
                @if ($compact)
                    {{ mb_strtoupper($option->value) }}<span class="sr-only"> {{ $option->getLabel() }}</span>
                @else
                    {{ $option->getLabel() }}
                @endif
            </button>
        </form>
    @endforeach
</div>
