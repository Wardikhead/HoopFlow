@php
    $liveHome = $live['home'] ? $teams->get($live['home']) : null;
    $liveAway = $live['away'] ? $teams->get($live['away']) : null;
    $nextHome = $next['home'] ? $teams->get($next['home']) : null;
    $nextAway = $next['away'] ? $teams->get($next['away']) : null;
@endphp

<section class="hf-lp-hero" data-testid="landing-hero" aria-labelledby="landing-hero-title">
    <div class="hf-lp-hero__art" aria-hidden="true">
        <svg class="hf-lp-court" viewBox="0 0 900 900" fill="none">
            <rect x="40" y="40" width="820" height="820" rx="16" />
            <path d="M450 40v820M40 450h820" />
            <circle cx="450" cy="450" r="118" />
            <circle cx="450" cy="450" r="300" />
            <path d="M40 260h176v380H40M860 260H684v380h176" />
        </svg>
        <span class="hf-lp-ball"></span>
    </div>

    <div class="hf-lp-shell hf-lp-hero__inner">
        <div class="hf-lp-hero__copy">
            <span class="hf-eyebrow hf-lp-eyebrow">
                <span class="hf-lp-ping" aria-hidden="true"></span>
                Barangay &amp; youth basketball
            </span>

            <h1 id="landing-hero-title" class="hf-lp-hero__title">
                <span>Run the league,</span>
                <span>not the spreadsheet.</span>
            </h1>

            <p class="hf-lp-hero__lede">
                Registration, eligibility, brackets, schedules, and standings — one clean flow,
                built for the covered court.
            </p>

            <div class="hf-lp-hero__actions">
                @auth
                    <a class="hf-btn hf-btn--primary hf-lp-btn-lg" href="{{ route('dashboard') }}">
                        Open your dashboard
                        <i class="bi bi-arrow-up-right" aria-hidden="true"></i>
                    </a>
                @else
                    <a class="hf-btn hf-btn--primary hf-lp-btn-lg" href="{{ route('register') }}">
                        Create your account
                        <i class="bi bi-arrow-up-right" aria-hidden="true"></i>
                    </a>
                @endauth
                <a class="hf-btn hf-btn--ghost hf-lp-btn-lg" href="#features">
                    Explore the demo
                    <i class="bi bi-arrow-down" aria-hidden="true"></i>
                </a>
            </div>

            <p class="hf-lp-hero__micro">
                Built for organizers, coaches, and the parents on the bleachers.
            </p>
        </div>

        <div class="hf-lp-hero__scene" aria-label="Sample live scoreboard">
            <article class="hf-lp-scoreboard">
                <div class="hf-lp-scoreboard__top">
                    <span class="hf-lp-scoreboard__tournament">{{ $tournament['name'] }}</span>
                    <span class="hf-lp-live"><span class="hf-lp-live__dot" aria-hidden="true"></span> Live</span>
                </div>
                <p class="hf-lp-scoreboard__meta">
                    {{ $tournament['division'] }} · {{ $live['round'] }} · {{ $live['court'] }}
                </p>
                <div class="hf-lp-scoreboard__clock">
                    <span>Q4</span>
                    <strong>02:14</strong>
                </div>
                <div class="hf-lp-scoreboard__teams">
                    <div class="hf-lp-scoreboard__team is-leading">
                        <x-landing.crest :team="$liveHome" size="md" />
                        <span class="hf-lp-scoreboard__name">{{ $liveHome['name'] ?? 'TBD' }}</span>
                        <strong class="hf-lp-scoreboard__score">{{ $live['home_score'] ?? '–' }}</strong>
                    </div>
                    <div class="hf-lp-scoreboard__team">
                        <x-landing.crest :team="$liveAway" size="md" />
                        <span class="hf-lp-scoreboard__name">{{ $liveAway['name'] ?? 'TBD' }}</span>
                        <strong class="hf-lp-scoreboard__score">{{ $live['away_score'] ?? '–' }}</strong>
                    </div>
                </div>
                <div class="hf-lp-scoreboard__foot">
                    <span>{{ $live['code'] }} · {{ $live['day_label'] }}</span>
                    <span>Game 4 of 7</span>
                </div>
            </article>

            <article class="hf-lp-nextup">
                <span class="hf-lp-nextup__eyebrow">Next up · {{ $next['round'] }}</span>
                <strong>{{ $next['day_label'] }} · {{ $next['time'] }}</strong>
                <span class="hf-lp-nextup__teams">
                    {{ $nextHome['name'] ?? 'TBD' }} <span>vs</span> {{ $nextAway['name'] ?? $next['away_label'] ?? 'TBD' }}
                </span>
                <span class="hf-lp-nextup__court">{{ $next['court'] }}</span>
            </article>

            <span class="hf-lp-stamp" aria-hidden="true">
                <span>Matchday</span>
                <strong>10 teams · 2 courts</strong>
            </span>
        </div>
    </div>

    <a class="hf-lp-scroll" href="#features">
        See it in motion
        <i class="bi bi-arrow-down" aria-hidden="true"></i>
    </a>
</section>
