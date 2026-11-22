<section id="developers" class="hf-lp-builders" data-testid="landing-developers" aria-labelledby="landing-developers-title">
    <div class="hf-lp-shell">
        <header class="hf-lp-builders__head hf-lp-reveal">
            <span class="hf-eyebrow hf-lp-eyebrow">Built by two students</span>
            <h2 id="landing-developers-title">Made for the covered court.</h2>
            <p>
                Two students built HoopFlow for the barangay leagues they grew up watching — every
                bracket, box score, and matchday.
            </p>
            <span class="hf-lp-builders__note">
                <i class="bi bi-mortarboard" aria-hidden="true"></i>
                Student project · 2026
            </span>
        </header>

        <div class="hf-lp-builders__grid">
            @foreach ($developers as $developer)
                <div class="hf-lp-builder-slot hf-lp-reveal" style="--i: {{ $loop->index }}">
                    <article
                        class="hf-lp-builder"
                        data-testid="landing-developer-{{ \Illuminate\Support\Str::slug($developer['name']) }}"
                        data-builder-card
                        style="--builder-accent: {{ $developer['accent'] }}; --builder-focal: {{ $developer['focal'] }}"
                    >
                        <div class="hf-lp-builder__photo">
                            <span class="hf-lp-builder__index" aria-hidden="true">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                            <span class="hf-lp-builder__initials" aria-hidden="true">{{ $developer['initials'] }}</span>
                            <img
                                src="{{ asset($developer['photo']) }}"
                                alt=""
                                width="320"
                                height="320"
                                loading="lazy"
                                decoding="async"
                            >
                            <span class="hf-lp-builder__glare" aria-hidden="true"></span>
                        </div>

                        <div class="hf-lp-builder__body">
                            <span class="hf-lp-builder__role">
                                <span class="hf-lp-builder__dot" aria-hidden="true"></span>
                                {{ $developer['role'] }}
                            </span>
                            <h3>{{ $developer['name'] }}</h3>
                            <div class="hf-lp-builder__foot">
                                <span class="hf-lp-builder__line">HoopFlow · 2026</span>
                                <span class="hf-lp-builder__arrow" aria-hidden="true">
                                    <i class="bi bi-arrow-up-right" aria-hidden="true"></i>
                                </span>
                            </div>
                        </div>
                    </article>
                </div>
            @endforeach
        </div>
    </div>
</section>
