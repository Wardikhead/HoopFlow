@extends('layouts.public')

@section('title', 'Dashboard')

@section('content')
@php
    $tournament = $tournament ?? null;
    $user = $user ?? auth()->user();
    $kpis = is_array($kpis ?? null) ? $kpis : [];
    $paymentSummary = is_array($paymentSummary ?? null) ? $paymentSummary : [];
    $recentResults = $recentResults ?? collect();
    $announcements = $announcements ?? collect();
    $upcomingMatches = $upcomingMatches ?? collect();
    $coachTeams = $coachTeams ?? collect();

    $role = 'fan';
    if ($user && $user->hasRole('organizer')) {
        $role = 'organizer';
    } elseif ($user && $user->hasRole('coach')) {
        $role = 'coach';
    }

    $money = fn ($v) => '₱' . number_format((int) ($v ?? 0));
@endphp

@if ($role === 'organizer')
    @php
        $pending = (int) ($kpis['pending'] ?? 0);
        $uncollected = (int) ($kpis['uncollected'] ?? ($paymentSummary['outstanding'] ?? 0));
        $collected = (int) ($paymentSummary['collected'] ?? ($kpis['collected'] ?? 0));
        $expected = (int) ($paymentSummary['expected'] ?? ($kpis['expected'] ?? 0));
        $unpaidCount = (int) ($paymentSummary['pendingCount'] ?? ($kpis['pendingCount'] ?? 0));
        $approved = (int) ($kpis['approved'] ?? 0);
        $registered = (int) ($kpis['registered'] ?? 0);
        $matchesPlayed = (int) ($kpis['matchesPlayed'] ?? 0);
        $matchesScheduled = (int) ($kpis['matchesScheduled'] ?? 0);
        $totalMatches = (int) ($kpis['totalMatches'] ?? 0);
        $progress = (int) ($kpis['progress'] ?? 0);
        $tournamentsCount = (int) ($kpis['tournaments'] ?? \App\Models\Tournament::count());
        $divisionName = $tournament?->divisions?->first()?->name;
        $standings = $tournament ? app(\App\Services\StandingsService::class)->compute($tournament)->take(4) : collect();
    @endphp

    <header class="hf-page-head hf-mb-3">
        <div>
            <span class="hf-eyebrow">Matchday desk · organizer</span>
            <h1 class="hf-page-title">Run the tournament</h1>
            <p class="hf-page-desc">Approvals, payments, bracket progress, and the next action that keeps the matchday moving.</p>
            @if ($tournament)
                <div class="hf-page-meta">
                    <span><i class="bi bi-calendar3"></i> {{ optional($tournament->start_date)->format('M d, Y') }} – {{ optional($tournament->end_date)->format('M d, Y') }}</span>
                </div>
            @endif
        </div>
        <div class="hf-page-actions">
            <a class="hf-btn hf-btn--primary" href="{{ route('tournamentCreate') }}" data-testid="dashboard-create-tournament"><i class="bi bi-plus-lg"></i> New tournament</a>
            <a class="hf-btn" href="{{ route('submissions') }}" data-testid="dashboard-open-submissions">Review queue</a>
        </div>
    </header>

    <div class="hf-grid hf-grid--4 hf-mb-3">
        <a class="hf-card hf-card--flat" href="{{ route('tournaments') }}" data-testid="kpi-tournaments">
            <span class="hf-kpi-label">Tournaments</span>
            <strong class="hf-kpi-value">{{ $tournamentsCount }}</strong>
            <p class="hf-small hf-muted hf-mb-0">3 seeded venues</p>
        </a>
        <a class="hf-card hf-card--flat" href="{{ route('submissions', ['status' => 'pending']) }}" data-testid="kpi-pending">
            <span class="hf-kpi-label">Pending teams</span>
            <strong class="hf-kpi-value">{{ $pending }}</strong>
            <p class="hf-small hf-muted hf-mb-0">Waiting for review</p>
        </a>
        <a class="hf-card hf-card--flat" href="{{ route('payments', ['status' => 'pending']) }}" data-testid="kpi-uncollected">
            <span class="hf-kpi-label">Uncollected</span>
            <strong class="hf-kpi-value">{{ $money($uncollected) }}</strong>
            <p class="hf-small hf-muted hf-mb-0">{{ $unpaidCount }} teams unpaid</p>
        </a>
        <a class="hf-card hf-card--flat" href="{{ route('teams') }}" data-testid="kpi-approved">
            <span class="hf-kpi-label">Approved teams</span>
            <strong class="hf-kpi-value">{{ $approved }}</strong>
            <p class="hf-small hf-muted hf-mb-0">{{ $registered }} registered</p>
        </a>
    </div>

    <div class="hf-split hf-mb-3">
        <div>
            <div class="hf-between hf-mb-2">
                <h2 class="hf-section-title hf-mb-0">Next actions</h2>
                <a class="hf-section-link" href="{{ route('submissions') }}">Open queue</a>
            </div>
            <div class="hf-grid hf-grid--3 hf-mb-3">
                <a class="hf-card hf-card--flat" href="{{ route('submissions', ['status' => 'pending']) }}" data-testid="dashboard-action-review">
                    <span class="hf-chip hf-chip--warning">Review</span>
                    <h3 class="hf-section-title hf-mt-2">{{ $pending }} pending teams</h3>
                    <p class="hf-muted hf-small hf-mb-0">Roster, consent, and documents are waiting for an organizer decision.</p>
                </a>
                <a class="hf-card hf-card--flat" href="{{ route('payments', ['status' => 'pending']) }}" data-testid="dashboard-action-payments">
                    <span class="hf-chip hf-chip--danger">Verify</span>
                    <h3 class="hf-section-title hf-mt-2">{{ $unpaidCount }} payments</h3>
                    <p class="hf-muted hf-small hf-mb-0">Collected {{ $money($collected) }} of {{ $money($expected) }}.</p>
                </a>
                <a class="hf-card hf-card--flat" href="{{ route('bracket') }}" data-testid="dashboard-action-score">
                    <span class="hf-chip hf-chip--primary">Score</span>
                    <h3 class="hf-section-title hf-mt-2">{{ $matchesScheduled }} games left</h3>
                    <p class="hf-muted hf-small hf-mb-0">Open the quarterfinal console and advance the winner.</p>
                </a>
            </div>

            <div class="hf-between hf-mb-2">
                <h2 class="hf-section-title hf-mb-0">Active tournament</h2>
                @if ($tournament)
                    <a class="hf-section-link" href="{{ route('tournaments') }}">Open tournament</a>
                @endif
            </div>
            @if ($tournament)
                <div class="hf-card hf-mb-3">
                    <div class="hf-between">
                        <div>
                            @if ($divisionName)
                                <span class="hf-chip hf-chip--primary">{{ $divisionName }}</span>
                            @endif
                            <h3 class="hf-page-title hf-mt-1 hf-mb-0" style="font-size: 30px">{{ $tournament->name }}</h3>
                        </div>
                        <div class="hf-text-right">
                            <span class="hf-kpi-label">Bracket progress</span>
                            <strong class="hf-kpi-value" style="font-size: 32px">{{ $matchesPlayed }}/{{ $totalMatches }}</strong>
                        </div>
                    </div>
                    <div class="hf-round-progress hf-mt-2"><span style="width: {{ $progress }}%"></span></div>
                    <div class="hf-flex hf-wrap hf-gap-2 hf-mt-2">
                        <a class="hf-btn hf-btn--sm" href="{{ route('bracket') }}">Bracket</a>
                        <a class="hf-btn hf-btn--sm" href="{{ route('schedule') }}">Schedule</a>
                        <a class="hf-btn hf-btn--sm" href="{{ route('standings') }}">Standings</a>
                        <a class="hf-btn hf-btn--sm" href="{{ route('announcements') }}">Announcements</a>
                    </div>
                </div>
            @endif

            <div class="hf-between hf-mb-2">
                <h2 class="hf-section-title hf-mb-0">Recent results</h2>
                <a class="hf-section-link" href="{{ route('matches', $tournament ? ['id' => $tournament->id] : []) }}">All matchups</a>
            </div>
            <div class="hf-grid hf-grid--2">
                @forelse ($recentResults as $match)
                    <a class="hf-card hf-card--flat" href="{{ route('gameStats', ['match' => $match->id]) }}">
                        <div class="hf-between">
                            <span>{{ $match->homeTeam?->name ?? 'TBD' }}</span>
                            <strong>{{ $match->home_score ?? 0 }} – {{ $match->away_score ?? 0 }}</strong>
                            <span>{{ $match->awayTeam?->name ?? 'TBD' }}</span>
                        </div>
                        <p class="hf-small hf-muted hf-mb-0">{{ $match->status }}</p>
                    </a>
                @empty
                    <div class="hf-card hf-card--flat">
                        <p class="hf-muted hf-small hf-mb-0">No results yet. Final scores will appear here.</p>
                    </div>
                @endforelse
            </div>
        </div>

        <aside>
            <div class="hf-between hf-mb-2">
                <h2 class="hf-section-title hf-mb-0">Standings snapshot</h2>
                <a class="hf-section-link" href="{{ route('standings') }}">Full table</a>
            </div>
            <div class="hf-mb-3">
                @forelse ($standings as $row)
                    <div class="hf-card hf-card--flat hf-mb-2">
                        <div class="hf-between">
                            <span>{{ $row['team_name'] ?? 'Team' }}</span>
                            <strong>{{ $row['wins'] ?? 0 }}-{{ $row['losses'] ?? 0 }}</strong>
                        </div>
                    </div>
                @empty
                    <p class="hf-muted hf-small">No standings yet.</p>
                @endforelse
            </div>

            <div class="hf-between hf-mb-2">
                <h2 class="hf-section-title hf-mb-0">Announcements</h2>
                <a class="hf-section-link" href="{{ route('announcements') }}">All</a>
            </div>
            <div class="hf-grid">
                @forelse ($announcements as $item)
                    <div class="hf-card hf-card--flat">
                        <h3 class="hf-section-title hf-mt-1">{{ $item->title }}</h3>
                        <p class="hf-small hf-muted hf-mb-0">{{ $item->body }}</p>
                    </div>
                @empty
                    <p class="hf-muted hf-small">No announcements.</p>
                @endforelse
            </div>
        </aside>
    </div>

@elseif ($role === 'coach')
    @php
        $team = $coachTeams->first();
        $record = $team ? app(\App\Services\DashboardService::class)->teamRecord($team) : ['wins' => 0, 'losses' => 0];
        $nextMatch = null;
        if ($team) {
            $nextMatch = \App\Models\MatchModel::where(function ($q) use ($team) {
                $q->where('home_team_id', $team->id)->orWhere('away_team_id', $team->id);
            })->where('status', 'scheduled')->orderBy('id')->first();
        }
        $tournament = $team && $team->registrations?->first()?->tournament
            ? $team->registrations->first()->tournament
            : ($tournament ?? \App\Models\Tournament::where('status', 'active')->first());
        $openTournaments = \App\Models\Tournament::where('status', 'registration-open')->get();
        $rosterTotal = $team ? $team->players->count() : 0;
        $rosterReady = $team ? $team->players->filter(fn ($p) => $p->consent_at)->count() : 0;
    @endphp

    <header class="hf-page-head hf-mb-3">
        <div>
            <span class="hf-eyebrow">Matchday desk · coach</span>
            <h1 class="hf-page-title">Coach corner</h1>
            <p class="hf-page-desc">Your registration status, roster readiness, payment, and next game.</p>
        </div>
        <div class="hf-page-actions">
            <a class="hf-btn hf-btn--primary" href="{{ route('tournaments') }}" data-testid="dashboard-register-cta"><i class="bi bi-trophy"></i> Find open tournament</a>
        </div>
    </header>

    @if (! $team)
        <div class="hf-card">
            <h2 class="hf-section-title">No team attached</h2>
            <p class="hf-muted">Register a team for an open tournament to start the coach journey.</p>
            <a class="hf-btn hf-btn--primary" href="{{ route('tournaments') }}">Browse tournaments</a>
        </div>
    @else
        <section class="hf-hero hf-mb-3" data-testid="coach-hero">
            <span class="hf-eyebrow" style="color: var(--hf-gold)">{{ $tournament?->name ?? 'Tournament' }} · {{ $team->registrations?->first()?->status ?? $team->status ?? 'Team' }}</span>
            <h1>{{ $team->name }}</h1>
            <p>{{ $team->coachUser?->name ?? 'Coach' }}@if ($tournament?->divisions?->first()?->name) · {{ $tournament->divisions->first()->name }}@endif</p>
            <div class="hf-stat-strip">
                <div><span>Record</span><strong>{{ $record['wins'] }}-{{ $record['losses'] }}</strong></div>
                <div><span>Seed</span><strong>{{ $team->registrations?->first()?->seed ?? 'TBD' }}</strong></div>
                <div><span>Roster</span><strong>{{ $rosterTotal }}</strong></div>
                <div><span>Payment</span><strong>{{ $team->registrations?->first()?->payment?->status ?? 'n/a' }}</strong></div>
            </div>
        </section>

        <div class="hf-grid hf-grid--3 hf-mb-3">
            <div class="hf-card">
                <span class="hf-kpi-label">Roster readiness</span>
                <strong class="hf-kpi-value">{{ $rosterReady }}/{{ $rosterTotal }}</strong>
                <p class="hf-small hf-muted hf-mb-0">Guardian consent on file.</p>
                <a class="hf-btn hf-btn--sm hf-mt-2" href="{{ route('myTeam', ['team' => $team->id]) }}">Manage roster</a>
            </div>
            <div class="hf-card">
                <span class="hf-kpi-label">Next game</span>
                <strong class="hf-section-title hf-mt-1">{{ $nextMatch ? 'Scheduled match' : 'No game scheduled' }}</strong>
                <p class="hf-small hf-muted hf-mb-0">{{ $nextMatch ? optional($nextMatch->matchSlots?->first()?->start_at)->format('M d, Y') : 'Waiting for the bracket.' }}</p>
                @if ($nextMatch)
                    <a class="hf-btn hf-btn--sm hf-mt-2" href="{{ route('gameStats', ['match' => $nextMatch->id]) }}">Game details</a>
                @endif
            </div>
            <div class="hf-card">
                <span class="hf-kpi-label">Registration</span>
                <strong class="hf-section-title hf-mt-1">{{ $team->registrations?->first()?->status ?? $team->status ?? '' }}</strong>
                <p class="hf-small hf-muted hf-mb-0">{{ $team->registrations?->first()?->rejection_reason ?? 'Documents and payment are tracked by the organizer.' }}</p>
            </div>
        </div>

        <div class="hf-between hf-mb-2">
            <h2 class="hf-section-title hf-mb-0">Open tournaments</h2>
            <a class="hf-section-link" href="{{ route('tournaments') }}">All tournaments</a>
        </div>
        <div class="hf-grid hf-grid--2">
            @forelse ($openTournaments as $item)
                <div class="hf-card">
                    <span class="hf-chip hf-chip--success">Registration open</span>
                    <h3 class="hf-section-title hf-mt-1">{{ $item->name }}</h3>
                    <p class="hf-small hf-muted">{{ $item->divisions?->first()?->name ?? '' }} · {{ $item->slug }} · {{ $money($item->divisions?->first()?->entry_fee ?? 0) }} entry</p>
                    <a class="hf-btn hf-btn--primary" href="{{ route('teamRegister', ['tournament' => $item->id]) }}" data-testid="coach-register-{{ $item->id }}">Register a team</a>
                </div>
            @empty
                <div class="hf-card">
                    <p class="hf-muted hf-small hf-mb-0">No open registration. Check back after the organizer opens the next tournament.</p>
                </div>
            @endforelse
        </div>
    @endif

@else
    @php
        $upcoming = $upcomingMatches;
        $recent = $recentResults;
        $fanAnnouncements = $announcements->filter(fn ($a) => ($a->audience ?? 'all') === 'all')->values();
        $standings = $tournament ? app(\App\Services\StandingsService::class)->compute($tournament)->take(5) : collect();
    @endphp

    <header class="hf-page-head hf-mb-3">
        <div>
            <span class="hf-eyebrow">Courtside pass · fan</span>
            <h1 class="hf-page-title">Courtside pass</h1>
            <p class="hf-page-desc">Follow the active tournament: bracket, schedule, results, standings, and announcements.</p>
        </div>
        <div class="hf-page-actions">
            <a class="hf-btn hf-btn--primary" href="{{ route('bracket') }}" data-testid="dashboard-open-bracket"><i class="bi bi-diagram-3"></i> Open bracket</a>
        </div>
    </header>

    <section class="hf-hero hf-mb-3" data-testid="fan-hero">
        <span class="hf-eyebrow" style="color: var(--hf-gold)">Courtside pass · {{ $tournament?->status ?? '' }}</span>
        <h1>{{ $tournament?->name ?? 'HoopFlow' }}</h1>
        <p>{{ $tournament?->divisions?->first()?->name ?? '' }} · {{ $tournament?->slug ?? '' }} · {{ optional($tournament?->start_date)->format('M d, Y') }} to {{ optional($tournament?->end_date)->format('M d, Y') }}</p>
        <div class="hf-btn-row hf-mt-3">
            <a class="hf-btn hf-btn--primary" href="{{ route('bracket') }}">Bracket</a>
            <a class="hf-btn" href="{{ route('schedule') }}">Schedule</a>
            <a class="hf-btn" href="{{ route('standings') }}">Standings</a>
        </div>
    </section>

    <div class="hf-split">
        <div>
            <div class="hf-between hf-mb-2">
                <h2 class="hf-section-title hf-mb-0">Up next</h2>
                <a class="hf-section-link" href="{{ route('schedule') }}">Full schedule</a>
            </div>
            <div class="hf-grid hf-grid--2 hf-mb-3">
                @forelse ($upcoming as $match)
                    <a class="hf-card hf-card--flat" href="{{ route('gameStats', ['match' => $match->id]) }}">
                        <div class="hf-between">
                            <span>{{ $match->homeTeam?->name ?? 'TBD' }}</span>
                            <strong>vs</strong>
                            <span>{{ $match->awayTeam?->name ?? 'TBD' }}</span>
                        </div>
                    </a>
                @empty
                    <div class="hf-card hf-card--flat">
                        <p class="hf-muted hf-small hf-mb-0">No games scheduled. The next tip-off will be posted here.</p>
                    </div>
                @endforelse
            </div>

            <div class="hf-between hf-mb-2">
                <h2 class="hf-section-title hf-mb-0">Recent results</h2>
            </div>
            <div class="hf-grid hf-grid--2">
                @forelse ($recent as $match)
                    <a class="hf-card hf-card--flat" href="{{ route('gameStats', ['match' => $match->id]) }}">
                        <div class="hf-between">
                            <span>{{ $match->homeTeam?->name ?? 'TBD' }}</span>
                            <strong>{{ $match->home_score ?? 0 }} – {{ $match->away_score ?? 0 }}</strong>
                            <span>{{ $match->awayTeam?->name ?? 'TBD' }}</span>
                        </div>
                    </a>
                @empty
                    <p class="hf-muted hf-small">No results yet.</p>
                @endforelse
            </div>
        </div>

        <aside>
            <div class="hf-between hf-mb-2">
                <h2 class="hf-section-title hf-mb-0">Top of the table</h2>
                <a class="hf-section-link" href="{{ route('standings') }}">Full table</a>
            </div>
            <div class="hf-mb-3">
                @forelse ($standings as $row)
                    <div class="hf-card hf-card--flat hf-mb-2">
                        <div class="hf-between">
                            <span>{{ $row['team_name'] ?? 'Team' }}</span>
                            <strong>{{ $row['wins'] ?? 0 }}-{{ $row['losses'] ?? 0 }}</strong>
                        </div>
                    </div>
                @empty
                    <p class="hf-muted hf-small">No standings yet.</p>
                @endforelse
            </div>

            <div class="hf-between hf-mb-2">
                <h2 class="hf-section-title hf-mb-0">Announcements</h2>
            </div>
            <div class="hf-grid">
                @forelse ($fanAnnouncements as $item)
                    <div class="hf-card hf-card--flat">
                        <h3 class="hf-section-title hf-mt-1">{{ $item->title }}</h3>
                        <p class="hf-small hf-muted hf-mb-0">{{ $item->body }}</p>
                    </div>
                @empty
                    <p class="hf-muted hf-small">No announcements.</p>
                @endforelse
            </div>
        </aside>
    </div>
@endif
@endsection
