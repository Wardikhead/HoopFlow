{{-- Blade view for Matchday strip. --}}
@props([
    'eyebrow',
    'title',
    'status' => null,
    'statusTone' => 'primary',
    'description' => null,
    'meta' => [],
    'nextUp' => null,
])

<section {{ $attributes->merge(['class' => 'hf-matchday']) }}>
    <span class="hf-matchday__court" aria-hidden="true"></span>
    <div class="hf-matchday__body">
        <span class="hf-eyebrow hf-eyebrow--on-dark">{{ $eyebrow }}</span>
        <div class="hf-matchday__title-row">
            <h1 class="hf-matchday__title">{{ $title }}</h1>
            @if ($status)
                <span class="hf-chip hf-chip--{{ $statusTone }}">{{ $status }}</span>
            @endif
        </div>
        @if ($description)
            <p class="hf-matchday__desc">{{ $description }}</p>
        @endif
        @if (count($meta))
            <div class="hf-matchday__meta">
                @foreach ($meta as $item)
                    <span><i class="bi bi-{{ $item['icon'] ?? 'circle' }}" aria-hidden="true"></i>{{ $item['label'] }}</span>
                @endforeach
            </div>
        @endif
        @if ($nextUp)
            <p class="hf-matchday__next"><i class="bi bi-broadcast" aria-hidden="true"></i> Next up · {{ $nextUp }}</p>
        @endif
    </div>
    @if (! $slot->isEmpty())
        <div class="hf-matchday__actions">{{ $slot }}</div>
    @endif
</section>
