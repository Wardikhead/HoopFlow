@props([
    'label',
    'value',
    'hint' => null,
    'tone' => null,
    'testid' => null,
])

@php
    $slug = \Illuminate\Support\Str::slug($label);
    $testid = $testid ?? 'kpi-'.$slug;
    $classes = 'hf-kpi'.($tone ? ' hf-kpi--'.$tone : '');
@endphp

<div class="{{ $classes }}" data-mark="{{ $value }}" data-testid="{{ $testid }}">
    <span class="hf-kpi-label">{{ $label }}</span>
    <span class="hf-kpi-value">{{ $value }}</span>
    @if ($hint)
        <span class="hf-kpi-hint">{{ $hint }}</span>
    @endif
</div>
