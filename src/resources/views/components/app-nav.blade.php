@props([
    'groups' => [],
    'items' => [],
    'activeKeys' => [],
    'variant' => 'rail',
])

@php
    $isSheet = $variant === 'sheet';
@endphp

@foreach ($groups as $group)
    @php
        $keys = array_values(array_filter($group['keys'] ?? [], fn ($key) => isset($items[$key])));
    @endphp

    @if (count($keys))
        @if ($isSheet)
            <div class="hf-sheet__group">
                <span class="hf-rail-label">{{ $group['label'] }}</span>
        @else
            <span class="hf-rail-label">{{ $group['label'] }}</span>
        @endif

        @foreach ($keys as $key)
            @php
                $item = $items[$key];
                $active = in_array($key, $activeKeys, true);
            @endphp

            @if ($isSheet)
                <a href="{{ route($item['route']) }}"
                   wire:navigate
                   data-more-link="{{ $key }}"
                   @if ($active) aria-current="page" @endif>
                    <i class="bi bi-{{ $item['icon'] }}" aria-hidden="true"></i>{{ $item['label'] }}
                </a>
            @else
                <a class="hf-rail-link{{ $active ? ' is-active' : '' }}"
                   href="{{ route($item['route']) }}"
                   wire:navigate
                   data-testid="nav-{{ $key }}"
                   data-label="{{ $item['label'] }}"
                   data-nav-link
                   @if ($active) aria-current="page" @endif>
                    <i class="bi bi-{{ $item['icon'] }}" aria-hidden="true"></i>
                    <span class="hf-nav-link__label">{{ $item['label'] }}</span>
                </a>
            @endif
        @endforeach

        @if ($isSheet)
            </div>
        @endif
    @endif
@endforeach
