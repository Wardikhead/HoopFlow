@props(['team' => null, 'label' => null, 'size' => 'sm'])

@php
    $short = $team['short'] ?? $label ?? 'TBD';
    $color = $team['color'] ?? '#64748b';
@endphp

<span
    {{ $attributes->class(['hf-lp-crest', 'hf-lp-crest--'.$size]) }}
    style="--hf-crest: {{ $color }}"
    aria-hidden="true"
>{{ $short }}</span>
