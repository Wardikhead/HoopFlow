<section id="features" class="hf-lp-lab" data-testid="landing-lab" data-landing-lab aria-labelledby="landing-lab-title">
    <div class="hf-lp-lab__glow" aria-hidden="true"></div>

    <div class="hf-lp-shell">
        <header class="hf-lp-section-head hf-lp-section-head--dark">
            <span class="hf-eyebrow hf-lp-eyebrow">The feature lab · read-only preview</span>
            <h2 id="landing-lab-title">Poke around. <em>Nothing breaks.</em></h2>
            <p>
                The real interface, loaded with a sample barangay league. Switch between the bracket,
                schedule, and standings. Editing stays off.
            </p>
            <span class="hf-lp-badge"><i class="bi bi-lock" aria-hidden="true"></i> Preview only · changes disabled</span>
        </header>

        <div class="hf-lp-lab__body">
            <div class="hf-lp-lab__rail">
                <div class="hf-lp-tabs" role="tablist" aria-label="Product preview">
                    <button type="button" class="hf-lp-tab is-active" id="tab-bracket" role="tab" aria-selected="true" aria-controls="panel-bracket" tabindex="0" data-demo-tab="bracket" data-testid="demo-tab-bracket">
                        <span class="hf-lp-tab__icon"><i class="bi bi-diagram-3" aria-hidden="true"></i></span>
                        <span><strong>Bracket</strong><small>Every path to the final, settled.</small></span>
                    </button>
                    <button type="button" class="hf-lp-tab" id="tab-schedule" role="tab" aria-selected="false" aria-controls="panel-schedule" tabindex="-1" data-demo-tab="schedule" data-testid="demo-tab-schedule">
                        <span class="hf-lp-tab__icon"><i class="bi bi-calendar3" aria-hidden="true"></i></span>
                        <span><strong>Schedule</strong><small>Courts, rest, and tip-off times in one view.</small></span>
                    </button>
                    <button type="button" class="hf-lp-tab" id="tab-standings" role="tab" aria-selected="false" aria-controls="panel-standings" tabindex="-1" data-demo-tab="standings" data-testid="demo-tab-standings">
                        <span class="hf-lp-tab__icon"><i class="bi bi-bar-chart" aria-hidden="true"></i></span>
                        <span><strong>Standings</strong><small>The cut line, without the manual math.</small></span>
                    </button>
                </div>

                <p class="hf-lp-lab__note">This is a read-only sample. Sign in to run your own league.</p>
            </div>

            <div class="hf-lp-lab__stage">
                <div class="hf-lp-lab__bar">
                    <span><i class="bi bi-lock" aria-hidden="true"></i> Read-only sample</span>
                    <span>{{ $tournament['name'] }} · {{ $tournament['division'] }}</span>
                </div>

                <div id="panel-bracket" class="hf-lp-panel is-active" role="tabpanel" aria-labelledby="tab-bracket" tabindex="0" data-demo-panel="bracket" data-testid="demo-panel-bracket">
                    @include('landing.bracket-panel', ['bracket' => $bracket, 'teams' => $teams])
                </div>

                <div id="panel-schedule" class="hf-lp-panel" role="tabpanel" aria-labelledby="tab-schedule" tabindex="0" data-demo-panel="schedule" data-testid="demo-panel-schedule">
                    @include('landing.schedule-panel', ['schedule' => $schedule, 'teams' => $teams])
                </div>

                <div id="panel-standings" class="hf-lp-panel" role="tabpanel" aria-labelledby="tab-standings" tabindex="0" data-demo-panel="standings" data-testid="demo-panel-standings">
                    @include('landing.standings-panel', ['standings' => $standings, 'teams' => $teams])
                </div>
            </div>
        </div>

        <p class="hf-lp-lab__cta">
            @auth
                <a href="{{ route('dashboard') }}">Open your dashboard</a>
                <span>to run your own league.</span>
            @else
                <a href="{{ route('login') }}">Sign in to manage</a>
                <span>or</span>
                <a href="{{ route('register') }}">create an account</a>
            @endauth
        </p>
    </div>
</section>
