<section class="hf-lp-closing" data-testid="landing-cta" aria-labelledby="landing-closing-title">
    <div class="hf-lp-closing__court" aria-hidden="true">
        <span></span>
        <span></span>
        <span></span>
    </div>

    <span class="hf-lp-mesh" aria-hidden="true"><span></span><span></span></span>

    <div class="hf-lp-shell hf-lp-closing__inner">
        <span class="hf-eyebrow hf-lp-eyebrow">Ready when you are</span>
        <h2 id="landing-closing-title">Your league deserves a better scoreboard.</h2>
        <p>Set up the tournament once. Let matchday flow.</p>
        <div class="hf-lp-closing__actions">
            @auth
                <a class="hf-btn hf-btn--primary hf-lp-btn-lg" href="{{ route('dashboard') }}" data-magnetic>
                    Open your dashboard
                    <i class="bi bi-arrow-up-right" aria-hidden="true"></i>
                </a>
            @else
                <a class="hf-btn hf-btn--primary hf-lp-btn-lg" href="{{ route('register') }}" data-magnetic>
                    Create your account
                    <i class="bi bi-arrow-up-right" aria-hidden="true"></i>
                </a>
            @endauth
            <a class="hf-btn hf-btn--ghost hf-lp-btn-lg" href="#features" data-magnetic>See how it runs</a>
        </div>
    </div>
</section>
