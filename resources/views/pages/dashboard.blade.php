@extends('layouts.public')

@section('title', 'Dashboard')

@section('meta-description', 'HoopFlow dashboard — matchday status, approvals, payments, brackets, and the next actions for your tournament in one organizer workspace.')

@section('content')
@php
    $tournament = $tournament ?? null;
    $user = $user ?? auth()->user();
    $role = $role ?? 'fan';
    $kpis = is_array($kpis ?? null) ? $kpis : [];
    $paymentSummary = is_array($paymentSummary ?? null) ? $paymentSummary : [];
    $recentResults = $recentResults ?? collect();
    $announcements = $announcements ?? collect();
    $upcomingMatches = $upcomingMatches ?? collect();
    $standings = $standings ?? collect();
    $nextMatch = $nextMatch ?? null;
    $coachTeams = $coachTeams ?? collect();
    $coachTeam = $coachTeam ?? null;
    $coachRegistration = $coachRegistration ?? null;
    $coachTournament = $coachTournament ?? null;
    $coachRecord = $coachRecord ?? ['wins' => 0, 'losses' => 0, 'pointsFor' => 0, 'pointsAgainst' => 0, 'diff' => 0];
    $coachNextMatch = $coachNextMatch ?? null;
    $coachRosterReady = (int) ($coachRosterReady ?? 0);
    $coachRosterTotal = (int) ($coachRosterTotal ?? 0);
    $openTournaments = $openTournaments ?? collect();

    $money = fn ($value) => '₱' . number_format((int) ($value ?? 0));
    $statusLabel = function (?string $status): string {
        return match ($status) {
            'submitted' => 'pending',
            'registration-open' => 'registration open',
            null, '' => 'team',
            default => str_replace('-', ' ', $status),
        };
    };
@endphp

@if ($role === 'organizer')
    @php
        $pending = (int) ($kpis['pending'] ?? 0);
        $uncollected = (int) ($kpis['uncollected'] ?? ($paymentSummary['outstanding'] ?? 0));
        $collected = (int) ($paymentSummary['collected'] ?? ($kpis['collected'] ?? 0));
        $expected = (int) ($paymentSummary['expected'] ?? ($kpis['expected'] ?? 0));
        $unpaidCount = (int) ($paymentSummary['pendingCount'] ?? ($kpis['pendingCount'] ?? 0));
        $unpaidTeams = (int) ($kpis['pendingCount'] ?? ($paymentSummary['pendingCount'] ?? 0));
        $approved = (int) ($kpis['approved'] ?? 0);
        $registered = (int) ($kpis['registered'] ?? 0);
        $matchesPlayed = (int) ($kpis['matchesPlayed'] ?? 0);
        $matchesScheduled = (int) ($kpis['matchesScheduled'] ?? 0);
        $totalMatches = (int) ($kpis['totalMatches'] ?? 0);
        $progress = (int) ($kpis['progress'] ?? 0);
        $tournamentsCount = (int) ($kpis['tournaments'] ?? \App\Models\Tournament::count());
        $divisionName = $tournament?->divisions?->first()?->name;
        $scoreHref = $nextMatch
            ? route('bracket', ['id' => $tournament?->id, 'match' => $nextMatch->id])
            : route('bracket', $tournament ? ['id' => $tournament->id] : []);
        $nextUp = $nextMatch
            ? trim(($nextMatch->roundLabel() ?? 'Next game')
                .' · '.($nextMatch->matchSlots->first()?->start_at?->format('M j, g:i A') ?? 'Time TBD')
                .' · '.($nextMatch->homeTeam?->shortLabel() ?? 'TBD').' vs '.($nextMatch->awayTeam?->shortLabel() ?? 'TBD'))
            : null;
    @endphp

    <x-matchday-strip
        data-reveal
        eyebrow="Matchday desk · organizer"
        title="Run the tournament"
        :status="$tournament?->statusLabel()"
        :description="$tournament ? $tournament->name : 'No active tournament yet. Create one to open the matchday desk.'"
        :meta="$tournament ? [
            ['icon' => 'calendar3', 'label' => $tournament->start_date?->format('M d, Y').' – '.$tournament->end_date?->format('M d, Y')],
            ['icon' => 'geo-alt', 'label' => $tournament->venueName()],
            ['icon' => 'people', 'label' => $tournament->slotCount().' slots · '.$tournament->courtCount().' courts'],
        ] : []"
        :next-up="$nextUp"
    >
        <a class="hf-btn hf-btn--primary" href="{{ route('tournamentCreate') }}" data-testid="dashboard-create-tournament"><i class="bi bi-plus-lg" aria-hidden="true"></i> New tournament</a>
        <a class="hf-btn" href="{{ route('submissions') }}" data-testid="dashboard-open-submissions">Review queue</a>
    </x-matchday-strip>

    <div class="hf-grid hf-grid--4 hf-mb-3" data-reveal>
        <x-kpi-tile label="Tournaments" :value="$tournamentsCount" :hint="$tournamentsCount.' in the workspace'" tone="primary" :href="route('tournaments')" testid="kpi-tournaments" :countup="true" />
        <x-kpi-tile label="Pending teams" :value="$pending" hint="Waiting for review" tone="gold" :href="route('submissions', ['status' => 'pending'])" testid="kpi-pending" :countup="true" />
        <x-kpi-tile label="Uncollected" :value="$money($uncollected)" :hint="$unpaidTeams.' teams unpaid'" tone="alert" :href="route('payments', ['status' => 'pending'])" testid="kpi-uncollected" :countup="true" />
        <x-kpi-tile label="Approved teams" :value="$approved" :hint="$registered.' registered'" tone="success" :href="route('teams')" testid="kpi-approved" :countup="true" />
    </div>

    <div class="hf-split hf-mb-3">
        <div>
            <div class="hf-section-head">
                <h2 class="hf-section-title">Next actions</h2>
                <a class="hf-section-link" href="{{ route('submissions') }}">Open queue</a>
            </div>
            <div class="hf-grid hf-grid--3 hf-mb-3" data-reveal>
                <a class="hf-card hf-card--flat hf-card--link" href="{{ route('submissions', ['status' => 'pending']) }}" data-testid="dashboard-action-review">
                    <span class="hf-chip hf-chip--warning">Review</span>
                    <h3 class="hf-section-title hf-mt-2">{{ $pending }} pending teams</h3>
                    <p class="hf-muted hf-small hf-mb-0">Roster, consent, and documents are waiting for an organizer decision.</p>
                    <span class="hf-card__arrow" aria-hidden="true"><i class="bi bi-arrow-right" aria-hidden="true"></i></span>
                </a>
                <a class="hf-card hf-card--flat hf-card--link" href="{{ route('payments', ['status' => 'pending']) }}" data-testid="dashboard-action-payments">
                    <span class="hf-chip hf-chip--danger">Verify</span>
                    <h3 class="hf-section-title hf-mt-2">{{ $unpaidCount }} payments</h3>
                    <p class="hf-muted hf-small hf-mb-0">Collected {{ $money($collected) }} of {{ $money($expected) }}.</p>
                    <span class="hf-card__arrow" aria-hidden="true"><i class="bi bi-arrow-right" aria-hidden="true"></i></span>
                </a>
                <a class="hf-card hf-card--flat hf-card--link" href="{{ $scoreHref }}" data-testid="dashboard-action-score">
                    <span class="hf-chip hf-chip--primary">Score</span>
                    <h3 class="hf-section-title hf-mt-2">{{ $matchesScheduled }} games left</h3>
                    <p class="hf-muted hf-small hf-mb-0">Open the quarterfinal console and advance the winner.</p>
                    <span class="hf-card__arrow" aria-hidden="true"><i class="bi bi-arrow-right" aria-hidden="true"></i></span>
                </a>
            </div>

            <div class="hf-section-head">
                <h2 class="hf-section-title">Active tournament</h2>
                @if ($tournament)
                    <a class="hf-section-link" href="{{ route('tournamentShow', ['tournament' => $tournament->id]) }}">Open tournament</a>
                @endif
            </div>
            @if ($tournament)
                <div class="hf-card">
                    <div class="hf-between">
                        <div>
                            @if ($divisionName)
                                <span class="hf-chip hf-chip--primary">{{ $divisionName }}</span>
                            @endif
                            <h3 class="hf-entity-title hf-mt-1 hf-mb-0">{{ $tournament->name }}</h3>
                        </div>
                        <div class="hf-text-right">
                            <span class="hf-kpi-label">Bracket progress</span>
                            <strong class="hf-kpi-value hf-kpi-value--md">{{ $matchesPlayed }}/{{ $totalMatches }}</strong>
                        </div>
                    </div>
                    <div class="hf-milestones hf-mt-2" aria-label="Tournament progress">
                        @foreach (['Round of 16', 'Quarterfinals', 'Semifinals', 'Final'] as $index => $milestone)
                            @php
                                $threshold = ($index + 1) * 25;
                                $state = $progress >= $threshold ? 'is-done' : ($progress >= $threshold - 25 ? 'is-active' : '');
                            @endphp
                            <span class="hf-milestone {{ $state }}">
                                <i class="bi bi-{{ $state === 'is-done' ? 'check-circle-fill' : 'circle' }}" aria-hidden="true"></i>
                                {{ $milestone }}
                            </span>
                        @endforeach
                    </div>
                    <div class="hf-flex hf-wrap hf-gap-2 hf-mt-2">
                        <a class="hf-btn hf-btn--sm" href="{{ route('bracket', ['id' => $tournament->id]) }}">Bracket</a>
                        <a class="hf-btn hf-btn--sm" href="{{ route('schedule', ['id' => $tournament->id]) }}">Schedule</a>
                        <a class="hf-btn hf-btn--sm" href="{{ route('standings', ['id' => $tournament->id]) }}">Standings</a>
                        <a class="hf-btn hf-btn--sm" href="{{ route('announcements', ['id' => $tournament->id]) }}">Announcements</a>
                    </div>
                </div>
            @endif

            <div class="hf-section-head hf-mt-3">
                <h2 class="hf-section-title">Recent results</h2>
                <a class="hf-section-link" href="{{ route('matches', $tournament ? ['id' => $tournament->id] : []) }}">All matchups</a>
            </div>
            <div class="hf-grid hf-grid--2">
                @forelse ($recentResults as $match)
                    <x-match-card :match="$match" :href="route('gameStats', ['match' => $match->id])" :compact="true" />
                @empty
                    <div class="hf-empty">
                        <i class="bi bi-flag" aria-hidden="true"></i>
                        <h3>No results yet</h3>
                        <p>Final scores will appear here.</p>
                    </div>
                @endforelse
            </div>
        </div>

        <aside>
            <div class="hf-section-head">
                <h2 class="hf-section-title">Standings snapshot</h2>
                <a class="hf-section-link" href="{{ route('standings', $tournament ? ['id' => $tournament->id] : []) }}">Full table</a>
            </div>
            <div class="hf-mb-3">
                <x-standings-table :rows="$standings" :limit="4" :tournament-id="$tournament?->id" />
            </div>

            <div class="hf-section-head">
                <h2 class="hf-section-title">Announcements</h2>
                <a class="hf-section-link" href="{{ route('announcements', $tournament ? ['id' => $tournament->id] : []) }}">All announcements</a>
            </div>
            <div class="hf-grid">
                @forelse ($announcements as $announcement)
                    <x-announcement-card :announcement="$announcement" :compact="true" />
                @empty
                    <div class="hf-empty hf-empty--compact">
                        <i class="bi bi-megaphone" aria-hidden="true"></i>
                        <h3>No announcements yet</h3>
                        <p>Post a venue, schedule, or eligibility notice for the tournament.</p>
                        <a class="hf-btn hf-btn--sm" href="{{ route('announcementCreate') }}">New announcement</a>
                    </div>
                @endforelse
            </div>
        </aside>
    </div>

@elseif ($role === 'coach')
    @php
        $team = $coachTeam;
        $registration = $coachRegistration;
        $registrationStatus = $registration?->status ?? $team?->status;
        $registrationLabel = ucfirst($statusLabel($registrationStatus));
        $divisionName = $coachTournament?->divisions?->first()?->name;
        $paymentStatus = match ($registration?->payment?->status) {
            'verified', 'paid' => 'paid',
            null, '' => 'n/a',
            default => $registration->payment->status,
        };
    @endphp

    <x-matchday-strip
        data-reveal
        eyebrow="Matchday desk · coach"
        title="Coach corner"
        :status="$team ? $registrationLabel : null"
        :description="$team ? $team->name.' · '.($divisionName ?? 'Division').' · '.$team->city : 'Register a team to start the coach journey.'"
        :meta="$tournament ? [
            ['icon' => 'calendar3', 'label' => $tournament->start_date?->format('M d, Y').' – '.$tournament->end_date?->format('M d, Y')],
            ['icon' => 'geo-alt', 'label' => $tournament->venueName()],
            ['icon' => 'people', 'label' => $tournament->slotCount().' slots · '.$tournament->courtCount().' courts'],
        ] : []"
        :next-up="$coachNextMatch?->roundLabel()"
    >
        <a class="hf-btn hf-btn--primary" href="{{ route('tournaments') }}" data-testid="dashboard-register-cta"><i class="bi bi-trophy" aria-hidden="true"></i> Find open tournament</a>
    </x-matchday-strip>

    @if (! $team)
        <div class="hf-empty">
            <i class="bi bi-person-plus" aria-hidden="true"></i>
            <h3>No team attached</h3>
            <p>Register a team for an open tournament to start the coach journey.</p>
            <a class="hf-btn hf-btn--primary" href="{{ route('tournaments') }}">Browse tournaments</a>
        </div>
    @else
        <section class="hf-hero hf-mb-3" data-testid="coach-hero">
            <span class="hf-eyebrow hf-text-gold">{{ $coachTournament?->name ?? 'Tournament' }} · {{ $registrationLabel }}</span>
            <h2 class="hf-hero__name">{{ $team->name }}</h2>
            <p>{{ $team->coachUser?->name ?? $team->coach_name ?? 'Coach' }} · {{ $team->city }} · {{ $divisionName ?? '' }}</p>
            <div class="hf-stat-strip">
                <div><span>Record</span><strong>{{ $coachRecord['wins'] }}-{{ $coachRecord['losses'] }}</strong></div>
                <div><span>Seed</span><strong>{{ $registration?->seed ?? 'TBD' }}</strong></div>
                <div><span>Roster</span><strong>{{ $coachRosterTotal }}</strong></div>
                <div><span>Payment</span><strong>{{ $paymentStatus ?? 'n/a' }}</strong></div>
            </div>
        </section>

        <div class="hf-grid hf-grid--3 hf-mb-3" data-reveal>
            <div class="hf-card">
                <span class="hf-kpi-label">Roster readiness</span>
                <strong class="hf-kpi-value">{{ $coachRosterReady }}/{{ $coachRosterTotal }}</strong>
                <p class="hf-small hf-muted hf-mb-0">Guardian consent on file.</p>
                <a class="hf-btn hf-btn--sm hf-mt-2" href="{{ route('myTeam', array_filter(['team' => $team->id, 'tournament' => $coachTournament?->id])) }}">Manage roster</a>
            </div>
            <div class="hf-card">
                <span class="hf-kpi-label">Next game</span>
                <strong class="hf-section-title hf-mt-1">{{ $coachNextMatch?->roundLabel() ?? 'No game scheduled' }}</strong>
                <p class="hf-small hf-muted hf-mb-0">{{ $coachNextMatch?->matchSlots?->first()?->start_at?->format('M d, Y') ?? 'Waiting for the bracket.' }}</p>
                @if ($coachNextMatch)
                    <a class="hf-btn hf-btn--sm hf-mt-2" href="{{ route('gameStats', ['match' => $coachNextMatch->id]) }}">Game details</a>
                @endif
            </div>
            <div class="hf-card">
                <span class="hf-kpi-label">Registration</span>
                <strong class="hf-section-title hf-mt-1">{{ $registrationLabel }}</strong>
                <p class="hf-small hf-muted hf-mb-0">{{ $registration?->rejection_reason ?? 'Documents and payment are tracked by the organizer.' }}</p>
            </div>
        </div>

        <div class="hf-section-head">
            <h2 class="hf-section-title">Open tournaments</h2>
            <a class="hf-section-link" href="{{ route('tournaments') }}">All tournaments</a>
        </div>
        <div class="hf-grid hf-grid--2" data-reveal>
            @forelse ($openTournaments as $item)
                @php
                    $joinedTeam = $item->registrations->first()?->team;
                @endphp
                <div class="hf-card">
                    <span class="hf-chip hf-chip--success">Registration open</span>
                    @if ($joinedTeam)
                        <span class="hf-chip hf-chip--primary">Registered</span>
                    @endif
                    <h3 class="hf-section-title hf-mt-1">{{ $item->name }}</h3>
                    <p class="hf-small hf-muted">{{ $item->divisions->first()?->name ?? 'Division' }} · {{ $item->venueName() }} · {{ $money($item->entryFee()) }} entry</p>
                    @if ($joinedTeam)
                        <a class="hf-btn" href="{{ route('myTeam', ['team' => $joinedTeam->id, 'tournament' => $item->id]) }}" data-testid="coach-my-team-{{ $item->id }}">My team status</a>
                    @else
                        <a class="hf-btn hf-btn--primary" href="{{ route('teamRegister', ['tournament' => $item->id]) }}" data-testid="coach-register-{{ $item->id }}">Register a team</a>
                    @endif
                </div>
            @empty
                <div class="hf-empty">
                    <i class="bi bi-trophy" aria-hidden="true"></i>
                    <h3>No open registration</h3>
                    <p>Check back after the organizer opens the next tournament.</p>
                </div>
            @endforelse
        </div>
    @endif

@else
    @php
        $fanAnnouncements = $announcements
            ->filter(fn ($announcement) => ($announcement->audience ?? 'all') === 'all')
            ->take(3)
            ->values();
    @endphp

    <x-matchday-strip
        data-reveal
        eyebrow="Courtside pass · fan"
        title="Courtside pass"
        :status="$tournament ? $statusLabel($tournament->status) : null"
        :description="$tournament?->name ?? 'HoopFlow'"
        :meta="$tournament ? [
            ['icon' => 'calendar3', 'label' => $tournament->start_date?->format('M d, Y').' – '.$tournament->end_date?->format('M d, Y')],
            ['icon' => 'geo-alt', 'label' => $tournament->venueName()],
            ['icon' => 'people', 'label' => $tournament->slotCount().' slots · '.$tournament->courtCount().' courts'],
        ] : []"
        :next-up="$upcomingMatches->first()?->roundLabel()"
    >
        <a class="hf-btn hf-btn--primary" href="{{ route('bracket', $tournament ? ['id' => $tournament->id] : []) }}" data-testid="dashboard-open-bracket"><i class="bi bi-diagram-3" aria-hidden="true"></i> Open bracket</a>
    </x-matchday-strip>

    <section class="hf-hero hf-mb-3" data-testid="fan-hero">
        <span class="hf-eyebrow hf-text-gold">Courtside pass · {{ $statusLabel($tournament?->status) }}</span>
        <h2 class="hf-hero__name">{{ $tournament?->name ?? 'HoopFlow' }}</h2>
        <p>{{ $tournament?->divisions?->first()?->name ?? '' }} · {{ $tournament?->venueName() ?? '' }} · {{ $tournament?->start_date?->format('M d, Y') ?? 'TBD' }} to {{ $tournament?->end_date?->format('M d, Y') ?? 'TBD' }}</p>
        <div class="hf-btn-row hf-mt-3">
            <a class="hf-btn hf-btn--primary" href="{{ route('bracket', $tournament ? ['id' => $tournament->id] : []) }}">Bracket</a>
            <a class="hf-btn" href="{{ route('schedule', $tournament ? ['id' => $tournament->id] : []) }}">Schedule</a>
            <a class="hf-btn" href="{{ route('standings', $tournament ? ['id' => $tournament->id] : []) }}">Standings</a>
        </div>
    </section>

    <div class="hf-split">
        <div>
            <div class="hf-section-head">
                <h2 class="hf-section-title">Up next</h2>
                <a class="hf-section-link" href="{{ route('schedule', $tournament ? ['id' => $tournament->id] : []) }}">Full schedule</a>
            </div>
            <div class="hf-grid hf-grid--2 hf-mb-3" data-reveal>
                @forelse ($upcomingMatches as $match)
                    <x-match-card :match="$match" :href="route('gameStats', ['match' => $match->id])" :compact="true" />
                @empty
                    <div class="hf-empty">
                        <i class="bi bi-calendar3" aria-hidden="true"></i>
                        <h3>No games scheduled</h3>
                        <p>The next tip-off will be posted here.</p>
                    </div>
                @endforelse
            </div>

            <div class="hf-section-head">
                <h2 class="hf-section-title">Recent results</h2>
            </div>
            <div class="hf-grid hf-grid--2">
                @forelse ($recentResults as $match)
                    <x-match-card :match="$match" :href="route('gameStats', ['match' => $match->id])" :compact="true" />
                @empty
                    <div class="hf-empty">
                        <i class="bi bi-flag" aria-hidden="true"></i>
                        <h3>No results yet</h3>
                        <p>Final scores will appear here.</p>
                    </div>
                @endforelse
            </div>
        </div>

        <aside>
            <div class="hf-section-head">
                <h2 class="hf-section-title">Top of the table</h2>
                <a class="hf-section-link" href="{{ route('standings', $tournament ? ['id' => $tournament->id] : []) }}">Full table</a>
            </div>
            <div class="hf-mb-3">
                <x-standings-table :rows="$standings" :limit="5" :tournament-id="$tournament?->id" />
            </div>

            <div class="hf-section-head">
                <h2 class="hf-section-title">Announcements</h2>
            </div>
            <div class="hf-grid">
                @forelse ($fanAnnouncements as $announcement)
                    <x-announcement-card :announcement="$announcement" :compact="true" />
                @empty
                    <div class="hf-empty hf-empty--compact">
                        <i class="bi bi-megaphone" aria-hidden="true"></i>
                        <h3>No announcements yet</h3>
                        <p>Venue, schedule, and eligibility notices will appear here.</p>
                    </div>
                @endforelse
            </div>
        </aside>
    </div>
@endif
@endsection
