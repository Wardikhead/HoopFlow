<section id="developers" class="hf-lp-builders" data-testid="landing-developers" aria-labelledby="landing-developers-title">
    <div class="hf-lp-shell hf-lp-builders__inner">
        <header class="hf-lp-builders__head">
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
                <article
                    class="hf-lp-builder"
                    data-testid="landing-developer-{{ \Illuminate\Support\Str::slug($developer['name']) }}"
                    style="--builder-accent: {{ $developer['accent'] }}; --builder-focal: {{ $developer['focal'] }}"
                >
                    <span class="hf-lp-builder__index" aria-hidden="true">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>

                    <div class="hf-lp-builder__photo">
                        <span class="hf-lp-builder__initials" aria-hidden="true">{{ $developer['initials'] }}</span>
                        <img
                            src="{{ asset($developer['photo']) }}"
                            alt=""
                            width="200"
                            height="200"
                            loading="lazy"
                            decoding="async"
                        >
                    </div>

                    <div class="hf-lp-builder__body">
                        <span class="hf-lp-builder__role">{{ $developer['role'] }}</span>
                        <h3>{{ $developer['name'] }}</h3>
                        <span class="hf-lp-builder__line">HoopFlow · 2026</span>
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</section>
