@props(['size' => 'md', 'onDark' => false, 'tagline' => null])

@php
    $size = in_array($size, ['sm', 'md', 'lg'], true) ? $size : 'md';
    $iconSize = ['sm' => 34, 'md' => 40, 'lg' => 56][$size];
@endphp

<span {{ $attributes->class(['hf-brand', 'hf-brand--'.$size, 'hf-brand--on-dark' => $onDark]) }}>
    <img
        class="hf-brand__icon"
        src="{{ asset('img/hoopflow icon without background.png') }}"
        alt=""
        width="{{ $iconSize }}"
        height="{{ $iconSize }}"
        decoding="async"
    >
    <span class="hf-brand__text">
        <span class="hf-brand__word"><span class="hf-brand__hoop">Hoop</span><span class="hf-brand__flow">Flow</span></span>
        @if ($tagline)
            <span class="hf-brand__tagline">{{ $tagline }}</span>
        @endif
    </span>
</span>
