{{--
 | Styleguide del design system (direzione "B · Pop affettuoso", piano UI §1-bis).
 | Solo in ambiente local (route `styleguide`, middleware EnsureLocalEnvironment).
 | Usa esclusivamente i token di resources/css/app.css. I componenti x-ui.* sono
 | documentati nelle pagine dei gruppi (/_styleguide/{group}, styleguide/groups/*).
--}}
@php
    use App\Support\DesignTokens\ContrastKind;
    use App\Support\DesignTokens\ContrastPairs;
    use Illuminate\Support\Number;

    $sections = ['preview', 'colors', 'contrast', 'typography', 'shapes', 'spacing', 'motion'];

    $semantic = [
        'backgrounds' => [['canvas', 'fg'], ['surface', 'fg'], ['surface-raised', 'fg'], ['inverse', 'on-inverse'], ['stamp', 'on-stamp']],
        'text' => [['fg', null], ['fg-muted', null], ['link', null], ['line', null], ['line-strong', null], ['focus', null]],
        'actions' => [['primary', 'on-primary'], ['primary-hover', 'on-primary'], ['highlight', 'on-highlight'], ['highlight-hover', 'on-highlight']],
        'blocks' => [['block-sun', 'on-block-sun'], ['block-peach', 'on-block-peach'], ['block-mint', 'on-block-mint'], ['block-leaf', 'on-block-leaf']],
        'status' => [
            ['success-subtle', 'on-success-subtle'], ['warning-subtle', 'on-warning-subtle'],
            ['danger-subtle', 'on-danger-subtle'], ['info-subtle', 'on-info-subtle'],
            ['success', null], ['warning', null], ['danger', 'on-danger'], ['danger-hover', 'on-danger'], ['info', null],
        ],
    ];

    $palette = ['navy', 'dusk', 'sand', 'sun', 'peach', 'leaf', 'cherry'];
    $steps = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950];

    $typeScale = [
        ['text-display', 'clamp(48px → 96px) · 800'],
        ['text-h1', 'clamp(40px → 64px) · 800'],
        ['text-h2', 'clamp(32px → 48px) · 800'],
        ['text-h3', 'clamp(26px → 36px) · 800'],
        ['text-h4', '26px · 800'],
        ['text-h5', '21px · 700'],
        ['text-lead', 'clamp(18px → 20px)'],
        ['text-body', '17px / 1.5'],
        ['text-small', '15px'],
        ['text-caption', '14px'],
    ];

    $radii = [
        ['rounded-panel', '40px'],
        ['rounded-card', '32px'],
        ['rounded-step', '28px'],
        ['rounded-media', '24px'],
        ['rounded-icon-lg', '16px'],
        ['rounded-icon', '14px'],
        ['rounded-full', __('styleguide.shapes.pill')],
    ];

    $spacing = [
        ['w-gutter', '--spacing-gutter', '16 → 24px'],
        ['w-panel', '--spacing-panel', '24 → 56px'],
        ['w-section', '--spacing-section', '40 → 64px'],
    ];

    $easings = [
        ['ease-out', '--ease-out', 'duration-(--duration-base)', '--duration-base · 220ms'],
        ['ease-in-out', '--ease-in-out', 'duration-(--duration-slow)', '--duration-slow · 300ms'],
        ['ease-pop', '--ease-pop', 'duration-(--duration-slow)', '--duration-slow · 300ms'],
    ];


    $locale = app()->getLocale();
@endphp
<x-styleguide.layout :title="__('styleguide.title')">
        <div class="flex flex-col gap-5 pt-4 md:pt-8">
            <h1 class="text-h1">{{ __('styleguide.heading') }}</h1>
            <p class="max-w-2xl text-lead text-fg-muted">{{ __('styleguide.intro') }}</p>

            <nav aria-label="{{ __('styleguide.toc') }}">
                <ul class="flex flex-wrap gap-2">
                    @foreach ($sections as $section)
                        <li>
                            <a href="#{{ $section }}" class="inline-flex min-h-11 items-center rounded-full border-2 border-line-strong px-4 font-semibold text-fg no-underline transition-colors duration-(--duration-fast) ease-out hover:border-fg">
                                {{ __('styleguide.sections.'.$section) }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>
        </div>

        {{-- Anteprima: i token combinati nei blocchi della home (bento). --}}
        <section id="preview" aria-labelledby="preview-title" class="flex scroll-mt-6 flex-col gap-6">
            <div class="flex flex-col gap-2">
                <h2 id="preview-title" class="text-h2">{{ __('styleguide.sections.preview') }}</h2>
                <p class="max-w-2xl text-fg-muted">{{ __('styleguide.preview.intro') }}</p>
            </div>

            <div class="flex flex-wrap gap-4">
                <div class="flex min-w-0 flex-[3_1_34rem] flex-col gap-6 rounded-panel bg-block-sun p-panel text-on-block-sun">
                    <span class="-rotate-2 self-start rounded-full bg-stamp px-4 py-2 text-small font-bold text-on-stamp">{{ __('styleguide.preview.stamp') }}</span>
                    <p class="text-display">{{ __('styleguide.preview.headline') }}</p>
                    <p class="max-w-lg text-lead">{{ __('styleguide.preview.lead') }}</p>
                    <form class="flex max-w-xl gap-2 rounded-full border-2 border-on-block-sun bg-canvas p-1.5 sm:p-2" role="search" x-data x-on:submit.prevent>
                        <label for="sg-search" class="sr-only">{{ __('styleguide.preview.search_label') }}</label>
                        <input id="sg-search" type="search" placeholder="{{ __('styleguide.preview.search_placeholder') }}" class="min-h-12 min-w-0 flex-1 rounded-full bg-transparent px-4 text-fg placeholder:text-fg-muted sm:min-h-13 sm:px-5">
                        <button type="submit" class="min-h-12 shrink-0 rounded-full bg-primary px-5 font-bold sm:min-h-13 sm:px-7 text-on-primary transition-colors duration-(--duration-fast) ease-out hover:bg-primary-hover">{{ __('styleguide.preview.search') }}</button>
                    </form>
                </div>

                <div class="flex min-w-0 flex-[2_1_22rem] flex-col gap-4">
                    <div class="flex min-h-56 flex-1 items-end rounded-panel bg-block-peach p-6 text-small font-semibold text-on-block-peach">{{ __('styleguide.preview.photo') }}</div>
                    <div class="flex gap-4">
                        <div class="flex-1 rounded-card bg-block-leaf p-6 text-on-block-leaf">
                            <strong class="block text-h3">block-leaf</strong>
                            <span>{{ __('styleguide.preview.stat_animals') }}</span>
                        </div>
                        <div class="flex-1 rounded-card bg-surface p-6">
                            <strong class="block text-h3">surface</strong>
                            <span>{{ __('styleguide.preview.stat_organizations') }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-4">
                <div role="group" aria-label="{{ __('styleguide.preview.filters') }}" class="flex flex-wrap gap-2" x-data="{ active: 'all' }">
                    @foreach (['all', 'dogs', 'cats', 'others'] as $chip)
                        <button
                            type="button"
                            x-on:click="active = '{{ $chip }}'"
                            x-bind:aria-pressed="(active === '{{ $chip }}').toString()"
                            aria-pressed="{{ $chip === 'all' ? 'true' : 'false' }}"
                            class="min-h-11 rounded-full border-2 border-line-strong bg-canvas px-5 font-semibold text-fg transition-colors duration-(--duration-fast) ease-out hover:border-fg aria-pressed:border-primary aria-pressed:bg-primary aria-pressed:text-on-primary"
                        >{{ __('styleguide.preview.'.$chip) }}</button>
                    @endforeach
                </div>

                <div class="flex flex-wrap gap-2">
                    <button type="button" class="min-h-12 rounded-full bg-primary px-6 font-bold text-on-primary transition-colors duration-(--duration-fast) ease-out hover:bg-primary-hover">{{ __('styleguide.preview.primary') }}</button>
                    <button type="button" class="min-h-12 rounded-full border-2 border-fg px-6 font-bold text-fg transition-colors duration-(--duration-fast) ease-out hover:bg-surface">{{ __('styleguide.preview.secondary') }}</button>
                    <button type="button" disabled class="min-h-12 cursor-not-allowed rounded-full bg-surface px-6 font-bold text-fg-muted">{{ __('styleguide.preview.disabled') }}</button>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-[1fr_2fr]">
                <div class="flex flex-col justify-between gap-6 rounded-card bg-inverse p-8 text-on-inverse">
                    <p class="text-h3">{{ __('styleguide.preview.cta_card') }}</p>
                    <a href="#preview" class="inline-flex min-h-13 items-center self-start rounded-full bg-highlight px-6 font-bold text-on-highlight no-underline transition-colors duration-(--duration-fast) ease-out hover:bg-highlight-hover">{{ __('styleguide.preview.cta') }}</a>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-6 rounded-panel bg-block-leaf p-panel text-on-block-leaf">
                    <p class="max-w-md flex-[1_1_18rem] text-h3">{{ __('styleguide.preview.donate') }}</p>
                    <a href="#preview" class="inline-flex min-h-14 items-center rounded-full bg-highlight px-7 font-bold text-on-highlight no-underline transition-colors duration-(--duration-fast) ease-out hover:bg-highlight-hover">{{ __('styleguide.preview.donate_cta') }}</a>
                </div>
            </div>
        </section>

        {{-- Colori: token semantici e palette. --}}
        <section id="colors" aria-labelledby="colors-title" class="flex scroll-mt-6 flex-col gap-8">
            <h2 id="colors-title" class="text-h2">{{ __('styleguide.sections.colors') }}</h2>

            <div class="flex flex-col gap-6">
                <div class="flex flex-col gap-2">
                    <h3 class="text-h4">{{ __('styleguide.colors.semantic') }}</h3>
                    <p class="max-w-2xl text-fg-muted">{{ __('styleguide.colors.semantic_intro') }}</p>
                </div>

                @foreach ($semantic as $group => $tokens)
                    <div class="flex flex-col gap-3">
                        <h4 class="text-h5">{{ __('styleguide.colors.groups.'.$group) }}</h4>
                        <ul class="grid grid-cols-[repeat(auto-fill,minmax(10.5rem,1fr))] gap-3">
                            @foreach ($tokens as [$token, $on])
                                <li class="flex flex-col gap-2 rounded-card bg-surface p-2">
                                    <span
                                        data-swatch
                                        aria-hidden="true"
                                        class="flex h-20 items-center justify-center rounded-media border border-line text-h4"
                                        style="background: var(--color-{{ $token }}); color: var(--color-{{ $on ?? 'fg' }})"
                                    >@if ($on) Aa @endif</span>
                                    <span class="px-2 pb-1">
                                        <code class="block text-small font-semibold">{{ $token }}</code>
                                        @if ($on)<code class="block text-caption text-fg-muted">{{ __('styleguide.colors.on', ['token' => $on]) }}</code>@endif
                                        <code class="block text-caption text-fg-muted" data-swatch-value></code>
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>

            <div class="flex flex-col gap-4">
                <div class="flex flex-col gap-2">
                    <h3 class="text-h4">{{ __('styleguide.colors.palette') }}</h3>
                    <p class="max-w-2xl text-fg-muted">{{ __('styleguide.colors.palette_intro') }}</p>
                </div>

                <div class="flex flex-col gap-3 overflow-x-auto pb-2" tabindex="0" role="region" aria-label="{{ __('styleguide.colors.palette') }}">
                    @foreach ($palette as $scale)
                        <div class="grid min-w-[44rem] grid-cols-[5rem_repeat(11,minmax(0,1fr))] items-center gap-1.5">
                            <code class="text-small font-semibold">{{ $scale }}</code>
                            @foreach ($steps as $step)
                                <span class="flex flex-col gap-1">
                                    <span data-swatch aria-hidden="true" class="h-12 rounded-icon border border-line" style="background: var(--color-{{ $scale }}-{{ $step }})" title="--color-{{ $scale }}-{{ $step }}"></span>
                                    <code class="text-center text-caption text-fg-muted">{{ $step }}</code>
                                </span>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- Contrasti: rapporti calcolati sul tema attivo (stesse coppie del test). --}}
        <section id="contrast" aria-labelledby="contrast-title" class="flex scroll-mt-6 flex-col gap-6">
            <div class="flex flex-col gap-2">
                <h2 id="contrast-title" class="text-h2">{{ __('styleguide.sections.contrast') }}</h2>
                <p class="max-w-2xl text-fg-muted">{{ __('styleguide.contrast.intro') }}</p>
                <p class="max-w-2xl rounded-card bg-warning-subtle px-5 py-4 text-on-warning-subtle">{{ __('styleguide.contrast.forbidden') }}</p>
            </div>

            <div class="overflow-x-auto rounded-card bg-surface p-2" tabindex="0" role="region" aria-label="{{ __('styleguide.contrast.table') }}" data-contrast-region>
                <table
                    class="w-full min-w-[40rem] border-separate border-spacing-y-1 text-left text-small"
                    data-contrast-table
                    data-contrast-theme=""
                    data-pass="{{ __('styleguide.contrast.pass') }}"
                    data-fail="{{ __('styleguide.contrast.fail') }}"
                >
                    <thead>
                        <tr class="text-fg-muted">
                            <th scope="col" class="px-3 py-2 font-semibold">{{ __('styleguide.contrast.pair') }}</th>
                            <th scope="col" class="px-3 py-2 font-semibold">{{ __('styleguide.contrast.sample') }}</th>
                            <th scope="col" class="px-3 py-2 font-semibold">{{ __('styleguide.contrast.ratio') }}</th>
                            <th scope="col" class="px-3 py-2 font-semibold">{{ __('styleguide.contrast.minimum') }}</th>
                            <th scope="col" class="px-3 py-2 font-semibold">{{ __('styleguide.contrast.result') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (ContrastPairs::all() as $pair)
                            <tr data-contrast="{{ $pair->minimum() }}" class="bg-surface-raised">
                                <th scope="row" class="rounded-s-media px-3 py-2 font-normal">
                                    <code class="font-semibold">{{ $pair->foreground }}</code>
                                    <span class="text-fg-muted">/</span>
                                    <code>{{ $pair->background }}</code>
                                </th>
                                <td class="px-3 py-2">
                                    <span
                                        data-contrast-sample
                                        aria-hidden="true"
                                        @class([
                                            'inline-flex min-h-10 min-w-24 items-center justify-center rounded-full px-4 font-semibold',
                                            'border-[3px]' => $pair->kind === ContrastKind::NonText,
                                        ])
                                        style="background: var(--color-{{ $pair->background }}); color: var(--color-{{ $pair->foreground }}); border-color: var(--color-{{ $pair->foreground }})"
                                    >{{ $pair->kind === ContrastKind::Text ? 'Aa' : '' }}</span>
                                </td>
                                <td class="px-3 py-2 font-semibold tabular-nums" data-contrast-ratio>—</td>
                                <td class="px-3 py-2 tabular-nums">
                                    {{ Number::format($pair->minimum(), precision: 1, locale: $locale) }}:1
                                    <span class="text-fg-muted">· {{ __('styleguide.contrast.'.$pair->kind->value) }}</span>
                                </td>
                                <td class="rounded-e-media px-3 py-2" data-contrast-result>—</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Tipografia. --}}
        <section id="typography" aria-labelledby="typography-title" class="flex scroll-mt-6 flex-col gap-6">
            <div class="flex flex-col gap-2">
                <h2 id="typography-title" class="text-h2">{{ __('styleguide.sections.typography') }}</h2>
                <p class="max-w-2xl text-fg-muted">{{ __('styleguide.typography.intro') }}</p>
            </div>

            <ul class="flex flex-col divide-y-2 divide-line">
                @foreach ($typeScale as [$class, $spec])
                    <li class="grid gap-2 py-5 md:grid-cols-[12rem_1fr] md:gap-8">
                        <span>
                            <code class="block text-small font-semibold">{{ $class }}</code>
                            <span class="text-caption text-fg-muted">{{ $spec }}</span>
                        </span>
                        <span @class([$class, 'max-w-[70ch]'])>
                            {{ str_starts_with($class, 'text-h') || $class === 'text-display' ? __('styleguide.typography.sample_title') : __('styleguide.typography.sample_text') }}
                        </span>
                    </li>
                @endforeach
            </ul>
        </section>

        {{-- Forme. --}}
        <section id="shapes" aria-labelledby="shapes-title" class="flex scroll-mt-6 flex-col gap-6">
            <div class="flex flex-col gap-2">
                <h2 id="shapes-title" class="text-h2">{{ __('styleguide.sections.shapes') }}</h2>
                <p class="max-w-2xl text-fg-muted">{{ __('styleguide.shapes.intro') }}</p>
            </div>

            <ul class="grid grid-cols-[repeat(auto-fill,minmax(9rem,1fr))] gap-4">
                @foreach ($radii as [$class, $value])
                    <li class="flex flex-col gap-2">
                        <span @class([$class, 'h-28 bg-block-mint'])></span>
                        <code class="text-small font-semibold">{{ $class }}</code>
                        <span class="text-caption text-fg-muted">{{ $value }}</span>
                    </li>
                @endforeach
            </ul>

            <div class="max-w-sm rounded-card bg-surface-raised p-6 shadow-overlay">
                <code class="text-small font-semibold">{{ __('styleguide.shapes.overlay') }}</code>
            </div>
        </section>

        {{-- Spazi. --}}
        <section id="spacing" aria-labelledby="spacing-title" class="flex scroll-mt-6 flex-col gap-6">
            <div class="flex flex-col gap-2">
                <h2 id="spacing-title" class="text-h2">{{ __('styleguide.sections.spacing') }}</h2>
                <p class="max-w-2xl text-fg-muted">{{ __('styleguide.spacing.intro') }}</p>
            </div>

            <ul class="flex flex-col gap-4">
                @foreach ($spacing as [$class, $token, $range])
                    <li class="flex flex-wrap items-center gap-4">
                        <span @class([$class, 'h-8 shrink-0 rounded-full bg-block-peach'])></span>
                        <code class="text-small font-semibold">{{ $token }}</code>
                        <span class="text-caption text-fg-muted">{{ $range }}</span>
                    </li>
                @endforeach
                <li class="flex flex-wrap items-center gap-4">
                    <code class="text-small font-semibold">--container-page</code>
                    <span class="text-caption text-fg-muted">{{ __('styleguide.spacing.container') }}</span>
                </li>
            </ul>
        </section>

        {{-- Movimento. --}}
        <section id="motion" aria-labelledby="motion-title" class="flex scroll-mt-6 flex-col gap-6" x-data="{ played: false }">
            <div class="flex flex-col gap-2">
                <h2 id="motion-title" class="text-h2">{{ __('styleguide.sections.motion') }}</h2>
                <p class="max-w-2xl text-fg-muted">{{ __('styleguide.motion.intro') }}</p>
                <p class="hidden max-w-2xl font-semibold motion-reduce:block">{{ __('styleguide.motion.reduced') }}</p>
            </div>

            <div>
                <button
                    type="button"
                    x-on:click="played = ! played"
                    x-text="played ? @js(__('styleguide.motion.reset')) : @js(__('styleguide.motion.play'))"
                    class="min-h-12 rounded-full bg-primary px-6 font-bold text-on-primary transition-colors duration-(--duration-fast) ease-out hover:bg-primary-hover"
                >{{ __('styleguide.motion.play') }}</button>
            </div>

            <ul class="flex flex-col gap-4">
                @foreach ($easings as [$easeClass, $easeToken, $durationClass, $durationLabel])
                    <li class="grid items-center gap-3 md:grid-cols-[14rem_1fr]">
                        <span>
                            <code class="block text-small font-semibold">{{ $easeToken }}</code>
                            <span class="text-caption text-fg-muted">{{ $durationLabel }}</span>
                        </span>
                        <span class="@container block rounded-full bg-surface p-1.5">
                            <span
                                @class(['block size-10 rounded-full bg-highlight transition-transform', $easeClass, $durationClass])
                                x-bind:class="played && 'translate-x-[calc(100cqw-100%)]'"
                            ></span>
                        </span>
                    </li>
                @endforeach
            </ul>
        </section>

    <x-slot:scripts>
    <script>
        (() => {
            const canvas = document.createElement('canvas');
            canvas.width = canvas.height = 1;
            const context = canvas.getContext('2d', { willReadFrequently: true });
            const number = new Intl.NumberFormat(document.documentElement.lang, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            const rgb = (color) => {
                context.clearRect(0, 0, 1, 1);
                context.fillStyle = color;
                context.fillRect(0, 0, 1, 1);

                return Array.from(context.getImageData(0, 0, 1, 1).data.slice(0, 3));
            };

            const luminance = (channels) => {
                const [r, g, b] = channels.map((value) => {
                    const c = value / 255;

                    return c <= 0.04045 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
                });

                return 0.2126 * r + 0.7152 * g + 0.0722 * b;
            };

            const ratio = (first, second) => {
                const [light, dark] = [luminance(first), luminance(second)].sort((a, b) => b - a);

                return (light + 0.05) / (dark + 0.05);
            };

            const hex = (channels) => '#' + channels.map((value) => value.toString(16).padStart(2, '0')).join('').toUpperCase();

            const update = () => {
                const table = document.querySelector('[data-contrast-table]');

                table.dataset.contrastTheme = document.documentElement.classList.contains('dark') ? 'dark' : 'light';

                document.querySelectorAll('[data-contrast]').forEach((row) => {
                    const style = getComputedStyle(row.querySelector('[data-contrast-sample]'));
                    const value = ratio(rgb(style.color), rgb(style.backgroundColor));
                    const passes = value >= parseFloat(row.dataset.contrast);
                    const result = row.querySelector('[data-contrast-result]');

                    row.querySelector('[data-contrast-ratio]').textContent = `${number.format(value)}:1`;
                    result.textContent = `${passes ? '✓' : '✕'} ${passes ? table.dataset.pass : table.dataset.fail}`;
                    result.className = `rounded-e-media px-3 py-2 font-semibold ${passes ? 'text-success' : 'text-danger'}`;
                });

                document.querySelectorAll('[data-swatch]').forEach((swatch) => {
                    const value = hex(rgb(getComputedStyle(swatch).backgroundColor));
                    const label = swatch.parentElement.querySelector('[data-swatch-value]');

                    swatch.title = `${swatch.title.split(' ')[0]} ${value}`.trim();

                    if (label) {
                        label.textContent = value;
                    }
                });
            };

            update();
            document.addEventListener('arkvel-theme', () => requestAnimationFrame(update));
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => requestAnimationFrame(update));
        })();
    </script>
    </x-slot:scripts>
</x-styleguide.layout>
