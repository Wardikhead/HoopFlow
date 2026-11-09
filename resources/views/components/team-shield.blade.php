@props(['team' => null, 'size' => ''])

@php
    $label = $team instanceof \App\Models\Team ? $team->shortLabel() : 'TBD';
    $color = $team instanceof \App\Models\Team ? $team->displayColor() : '#64748b';
@endphp

<span
    class="hf-shield{{ $size !== '' ? ' hf-shield--'.$size : '' }}"
    style="--team-color: {{ $color }}"
    aria-hidden="true"
>{{ $label }}</span>
