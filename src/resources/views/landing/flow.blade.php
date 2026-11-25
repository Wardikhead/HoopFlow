{{-- Blade view for Flow. --}}
<section id="flow" class="hf-lp-flow-section" data-testid="landing-flow" aria-labelledby="landing-flow-title">
    <div class="hf-lp-shell">
        <header class="hf-lp-section-head">
            <span class="hf-eyebrow hf-lp-eyebrow">From sign-up to final buzzer</span>
            <h2 id="landing-flow-title">One flow, from registration to the final buzzer.</h2>
            <p>Every step keeps the same tournament record moving forward — no re-typing, no lost sheets.</p>
        </header>

        <ol class="hf-lp-flow">
            @foreach ($steps as $step)
                <li class="hf-lp-flow__step hf-lp-reveal hf-lp-tilt" data-hf-card style="--i: {{ $loop->index }}; --card-accent: var(--hf-lp-orange)">
                    <span class="hf-lp-flow__num">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                    <span class="hf-lp-flow__dot"><i class="bi bi-{{ $step['icon'] }}" aria-hidden="true"></i></span>
                    <h3>{{ $step['title'] }}</h3>
                    <p>{{ $step['copy'] }}</p>
                    <span class="hf-lp-flow__tag">{{ $step['tag'] }}</span>
                </li>
            @endforeach
        </ol>
    </div>
</section>
