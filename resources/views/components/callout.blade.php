@props(['tone' => 'info', 'icon' => null, 'title' => null])

@php
    $icons = ['info' => 'info-circle-fill', 'warning' => 'exclamation-triangle-fill', 'danger' => 'exclamation-octagon-fill', 'success' => 'check-circle-fill'];
@endphp

<div {{ $attributes->merge(['class' => 'hf-callout hf-callout--'.$tone]) }} role="{{ $tone === 'danger' ? 'alert' : 'status' }}">
    <i class="bi bi-{{ $icon ?? ($icons[$tone] ?? 'info-circle-fill') }}" aria-hidden="true"></i>
    <div>
        @if ($title)<strong>{{ $title }}</strong>@endif
        {{ $slot }}
    </div>
</div>
