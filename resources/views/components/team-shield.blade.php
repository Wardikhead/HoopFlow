{{-- Blade view for Team shield. --}}
@props(['team' => null, 'size' => ''])

@php
    $label = $team instanceof \App\Models\Team ? $team->shortLabel() : 'TBD';
    $color = $team instanceof \App\Models\Team ? $team->displayColor() : '#64748b';

    // Pick white or black ink so the shield label keeps at least 4.5:1
    // against any saturated team color (threshold is relative luminance 0.179).
    $ink = '#ffffff';
    $hex = ltrim($color, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    }
    if (preg_match('/^[0-9a-fA-F]{6}$/', $hex) === 1) {
        $channel = static function (string $pair): float {
            $value = hexdec($pair) / 255;

            return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        };
        $luminance = 0.2126 * $channel(substr($hex, 0, 2))
            + 0.7152 * $channel(substr($hex, 2, 2))
            + 0.0722 * $channel(substr($hex, 4, 2));
        $ink = $luminance > 0.179 ? '#000000' : '#ffffff';
    }
@endphp

<span
    class="hf-shield{{ $size !== '' ? ' hf-shield--'.$size : '' }}"
    style="--team-color: {{ $color }}; --team-ink: {{ $ink }}"
    aria-hidden="true"
>{{ $label }}</span>
