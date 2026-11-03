@props([
    'label',
    'options' => [],
    'placeholder' => null,
])

<label class="block">
    <span class="mb-1.5 block text-sm font-medium text-stone-700">{{ $label }}</span>
    <select {{ $attributes->class('w-full rounded-xl border-stone-200 bg-white py-2.5 text-sm text-stone-700 shadow-sm focus:border-brand-500 focus:ring-brand-500') }}>
        <option value="">{{ $placeholder ?? __('public.filters.all') }}</option>
        @foreach ($options as $option)
            <option value="{{ $option->value }}">{{ $option->getLabel() }}</option>
        @endforeach
    </select>
</label>
