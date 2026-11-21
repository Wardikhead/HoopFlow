<section class="hf-lp-developers" data-testid="landing-developers" aria-labelledby="landing-developers-title">
    <div class="hf-lp-shell">
        <header class="hf-lp-section-head">
            <span class="hf-eyebrow hf-lp-eyebrow">Built by two developers</span>
            <h2 id="landing-developers-title">Made for the covered court.</h2>
            <p>HoopFlow is hand-built by two developers who love grassroots basketball. Profile pictures coming soon.</p>
        </header>

        <div class="hf-lp-developers__grid">
            @foreach ([
                ['name' => 'Jeward Mahinay', 'role' => 'Full-stack developer', 'initials' => 'JM', 'color' => '#ea580c'],
                ['name' => 'Rusty Japos', 'role' => 'Full-stack developer', 'initials' => 'RJ', 'color' => '#0f766e'],
            ] as $dev)
                <article class="hf-lp-developer" data-testid="landing-developer-{{ \Illuminate\Support\Str::slug($dev['name']) }}">
                    <span class="hf-lp-developer__avatar" style="--dev-color: {{ $dev['color'] }}" aria-hidden="true">
                        {{ $dev['initials'] }}
                    </span>
                    <div class="hf-lp-developer__body">
                        <h3>{{ $dev['name'] }}</h3>
                        <span class="hf-lp-developer__role">{{ $dev['role'] }}</span>
                        <div class="hf-lp-developer__links">
                            <a href="#" class="hf-lp-developer__link" aria-label="{{ $dev['name'] }} on GitHub"><i class="bi bi-github" aria-hidden="true"></i></a>
                            <a href="#" class="hf-lp-developer__link" aria-label="{{ $dev['name'] }} portfolio"><i class="bi bi-globe" aria-hidden="true"></i></a>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</section>