{{-- Blade view for Numbers. --}}
<section id="numbers" class="hf-lp-numbers" aria-labelledby="numbers-title">
    <div class="hf-lp-shell">
        <div class="hf-lp-numbers__head hf-lp-wipe">
            <span class="hf-eyebrow hf-lp-eyebrow">The sample league, in numbers</span>
            <h2 id="numbers-title">Small league. <em>Real record.</em></h2>
        </div>

        <ul class="hf-lp-numbers__grid">
            @foreach ($stats as $stat)
                <li class="hf-lp-numbers__item hf-lp-reveal" style="--i: {{ $loop->index }}">
                    <span class="hf-lp-numbers__icon"><i class="bi bi-{{ $stat['icon'] }}" aria-hidden="true"></i></span>
                    <strong class="hf-lp-numbers__value" data-count="{{ $stat['value'] }}">0</strong>
                    <span class="hf-lp-numbers__label" data-scramble>{{ $stat['label'] }}</span>
                </li>
            @endforeach
        </ul>
    </div>
</section>
