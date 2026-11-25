{{-- Blade view for Eligibility. --}}
@php
    $cleared = collect($eligibility['players'])->where('status', 'verified')->count();
    $total = count($eligibility['players']);
@endphp

<section id="eligibility" class="hf-lp-elig-section" data-testid="landing-eligibility" aria-labelledby="landing-elig-title">
    <div class="hf-lp-shell hf-lp-elig">
        <div class="hf-lp-elig__copy">
            <span class="hf-eyebrow hf-lp-eyebrow">The unglamorous part, handled</span>
            <h2 id="landing-elig-title">The paperwork is part of the tournament.</h2>
            <p>
                Birthdate eligibility, guardian consent, and document review are first-class steps in
                HoopFlow — alongside GCash, Maya, bank, and cash payments. Review every player in one
                queue, with a written reason on each rejection.
            </p>

            <ul class="hf-lp-elig__checklist">
                <li><i class="bi bi-check2-circle" aria-hidden="true"></i> Birthdate eligibility</li>
                <li><i class="bi bi-check2-circle" aria-hidden="true"></i> Guardian consent</li>
                <li><i class="bi bi-check2-circle" aria-hidden="true"></i> Birth certificate &amp; document review</li>
                <li><i class="bi bi-check2-circle" aria-hidden="true"></i> GCash, Maya, bank &amp; cash</li>
                <li><i class="bi bi-check2-circle" aria-hidden="true"></i> A written reason on every rejection</li>
            </ul>
        </div>

        <div class="hf-lp-elig__scene">
            <article class="hf-lp-review hf-lp-tilt" data-hf-card style="--card-rotate: 1.5deg; --card-accent: #c2410c">
                <header class="hf-lp-review__head">
                    <div>
                        <span class="hf-lp-review__eyebrow">Roster check</span>
                        <strong>San Isidro Vipers · U16</strong>
                    </div>
                    <span class="hf-lp-review__count">{{ $cleared }} of {{ $total }} cleared</span>
                </header>

                <div class="hf-lp-review__rows">
                    @foreach ($eligibility['players'] as $player)
                        <div class="hf-lp-review__row">
                            <span class="hf-lp-review__player">
                                <strong>{{ $player['name'] }}</strong>
                                <small>Age {{ $player['age'] }}</small>
                            </span>
                            <x-status-chip :status="$player['status']" :label="$player['label']" />
                        </div>
                    @endforeach
                </div>

                <div class="hf-lp-review__progress" aria-hidden="true">
                    <span style="width: {{ ($cleared / max($total, 1)) * 100 }}%"></span>
                </div>
            </article>

            <article class="hf-lp-payment hf-lp-tilt" data-hf-card style="--card-rotate: -2deg; --card-accent: #fb7a1e">
                <span class="hf-lp-payment__eyebrow">Payment record</span>
                @foreach ($eligibility['payments'] as $payment)
                    <div class="hf-lp-payment__row">
                        <div>
                            <strong>{{ $payment['label'] }} · {{ $payment['amount'] }}</strong>
                            <span>{{ $payment['method'] }} · {{ $payment['reference'] }}</span>
                        </div>
                        <x-status-chip :status="$payment['status']" :label="$payment['status_label']" />
                    </div>
                @endforeach
            </article>
        </div>
    </div>
</section>
