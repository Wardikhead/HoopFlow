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
                    <div
                        class="hf-lp-hang"
                        data-builder-hang
                        style="--builder-accent: {{ $developer['accent'] }}; --builder-focal: {{ $developer['focal'] }}"
                    >
                        <span class="hf-lp-hang__ribbon" aria-hidden="true"></span>
                        <span class="hf-lp-hang__clip" aria-hidden="true"></span>

                        <article
                            class="hf-lp-builder"
                            data-testid="landing-developer-{{ \Illuminate\Support\Str::slug($developer['name']) }}"
                            data-builder-card
                        >
                            <span class="hf-lp-builder__hole" aria-hidden="true"></span>
                            <span class="hf-lp-builder__foil" aria-hidden="true"></span>
                            <span class="hf-lp-builder__holo" aria-hidden="true"></span>
                            <span class="hf-lp-builder__sheen" aria-hidden="true"></span>

                            <header class="hf-lp-builder__top">
                                <span class="hf-lp-builder__brand">
                                    <i class="bi bi-dribbble" aria-hidden="true"></i>
                                    HoopFlow
                                </span>
                                <span class="hf-lp-builder__kind">Student ID</span>
                            </header>

                            <div class="hf-lp-builder__photo">
                                <span class="hf-lp-builder__initials" aria-hidden="true">{{ $developer['initials'] }}</span>
                                <img
                                    src="{{ asset($developer['photo']) }}"
                                    alt=""
                                    width="320"
                                    height="320"
                                    loading="lazy"
                                    decoding="async"
                                >
                                <span class="hf-lp-builder__scan" aria-hidden="true"></span>
                                <span class="hf-lp-builder__index" aria-hidden="true">
                                    No. {{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}
                                </span>
                            </div>

                            <div class="hf-lp-builder__body">
                                <h3>{{ $developer['name'] }}</h3>
                                <span class="hf-lp-builder__role">
                                    <span class="hf-lp-builder__dot" aria-hidden="true"></span>
                                    {{ $developer['role'] }}
                                </span>

                                <dl class="hf-lp-builder__meta">
                                    <div>
                                        <dt>ID no.</dt>
                                        <dd>HF-26-{{ str_pad((string) $loop->iteration, 3, '0', STR_PAD_LEFT) }}</dd>
                                    </div>
                                    <div>
                                        <dt>Valid thru</dt>
                                        <dd>06 / 2026</dd>
                                    </div>
                                </dl>

                                <div class="hf-lp-builder__foot">
                                    <svg class="hf-lp-builder__sign" viewBox="0 0 120 28" aria-hidden="true" focusable="false">
                                        <path
                                            d="M4 22c7-13 13-17 17-12s-3 12 3 10 9-9 13-7 4 7 10 4 8-6 11-3 2 6 8 4"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="1.8"
                                            stroke-linecap="round"
                                        />
                                    </svg>
                                    <span class="hf-lp-builder__barcode" aria-hidden="true"></span>
                                </div>
                            </div>
                        </article>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
