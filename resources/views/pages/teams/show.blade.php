@extends('layouts.public')

@section('title', $team->name)

@section('meta-description', $team->name.' · '.$team->city.' · '.($tournament?->name ?? 'HoopFlow').' team profile, roster, results, and bracket position.')

@section('breadcrumbs')
    <x-breadcrumbs :items="array_values(array_filter([
        ['label' => 'Home', 'href' => route('dashboard')],
        ['label' => 'Teams', 'href' => route('teams', $tournament ? ['tournament' => $tournament->id] : [])],
        ['label' => $team->name],
    ]))" />
@endsection

@section('content')
@php
    $money = fn ($value) => '₱' . number_format((int) ($value ?? 0));
    $divisionName = $registration?->division?->name ?? $tournament?->divisions?->first()?->name ?? 'Division';
    $docsComplete = $team->players->every(fn ($player) => $player->eligibility_status === 'verified');
    $consentComplete = $team->players->every(fn ($player) => ! is_null($player->consent_at));
@endphp

<div class="hf-page">
    <header class="hf-page-head hf-mb-3">
        <div>
            <span class="hf-eyebrow">{{ $divisionName }} · {{ $tournament?->name ?? 'Tournament' }}</span>
            <h1 class="hf-page-title">{{ $team->name }}</h1>
            <p class="hf-page-desc">{{ $team->coachUser?->name ?? $team->coach_name ?? 'Coach' }} · {{ $team->city }}</p>
        </div>
        <div class="hf-page-actions">
            <a class="hf-btn" href="{{ route('teams', $tournament ? ['tournament' => $tournament->id] : []) }}" data-testid="team-back"><i class="bi bi-arrow-left"></i> Teams</a>
            @if ($isOwner)
                <a class="hf-btn hf-btn--primary" href="{{ route('myTeam', array_filter(['team' => $team->id, 'tournament' => $tournament?->id])) }}" data-testid="team-manage-roster"><i class="bi bi-person-lines-fill"></i> Manage roster</a>
            @endif
        </div>
    </header>

    <section class="hf-hero hf-team-hero hf-mb-3" data-testid="team-hero-{{ $team->id }}">
        <div class="hf-flex hf-items-center hf-gap-3">
            <x-team-shield :team="$team" size="lg" />
            <div>
                <span class="hf-flex hf-wrap hf-gap-1">
                    @if ($registration)
                        <x-status-chip :status="$registration->status" />
                    @endif
                    @if ($canSeePrivate && $registration?->payment)
                        <x-payment-chip :payment="$registration->payment" />
                    @endif
                    @if ($registration?->seed)
                        <span class="hf-chip hf-chip--slate">Seed {{ $registration->seed }}</span>
                    @endif
                </span>
                <h2 class="hf-hero__name">{{ $team->name }}</h2>
                <p>{{ $team->coachUser?->name ?? $team->coach_name ?? 'Coach' }} · {{ $team->city }} · {{ $tournament?->venueName() ?? '' }}</p>
            </div>
        </div>
        <div class="hf-stat-strip">
            <div><span>Record</span><strong>{{ $record['wins'] }}-{{ $record['losses'] }}</strong></div>
            <div><span>Points for</span><strong>{{ $record['pointsFor'] }}</strong></div>
            <div><span>Point diff</span><strong>{{ $record['diff'] > 0 ? '+' : '' }}{{ $record['diff'] }}</strong></div>
            <div><span>Roster</span><strong>{{ $team->players->count() }}</strong></div>
        </div>
    </section>

    <div class="hf-split">
        <div>
            <div class="hf-section-head">
                <h2 class="hf-section-title">Roster ({{ $team->players->count() }})</h2>
            </div>
            <div class="hf-card hf-mb-3" data-testid="team-roster">
                @if ($team->players->isEmpty())
                    <div class="hf-empty">
                        <i class="bi bi-person-plus" aria-hidden="true"></i>
                        <h3>No players yet</h3>
                        <p>The coach has not added a roster.</p>
                    </div>
                @else
                    <div class="hf-table-wrap">
                        <table class="hf-table hf-table--roster hf-table--stack">
                            <caption class="hf-sr-only">Team roster</caption>
                            <thead>
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col">Player</th>
                                    <th scope="col">Position</th>
                                    @if ($role !== 'fan')
                                        <th scope="col">Age</th>
                                    @endif
                                    @if ($canSeePrivate)
                                        <th scope="col">Consent</th>
                                        <th scope="col">Documents</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($team->players as $player)
                                    <tr>
                                        <td data-label="#">{{ $player->jersey_number }}</td>
                                        <td data-label="Player"><strong>{{ $player->first_name }} {{ $player->last_name }}</strong></td>
                                        <td data-label="Position">{{ $player->position }}</td>
                                        @if ($role !== 'fan')
                                            <td data-label="Age">{{ $player->birthdate?->age ?? '—' }}</td>
                                        @endif
                                        @if ($canSeePrivate)
                                            <td data-label="Consent">
                                                @if ($player->consent_at)
                                                    <span class="hf-chip hf-chip--success">On file</span>
                                                @else
                                                    <span class="hf-chip hf-chip--danger">Missing</span>
                                                @endif
                                            </td>
                                            <td data-label="Documents"><x-status-chip :status="$player->eligibility_status" /></td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <div class="hf-section-head">
                <h2 class="hf-section-title">Results</h2>
                <a class="hf-section-link" href="{{ route('matches', $tournament ? ['id' => $tournament->id] : []) }}">All matchups</a>
            </div>
            <div class="hf-grid hf-grid--2 hf-mb-3">
                @forelse ($played as $match)
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
            <div class="hf-card hf-mb-3">
                <span class="hf-kpi-label">Next game</span>
                @if ($nextMatch)
                    <h3 class="hf-section-title hf-mt-1">{{ $nextMatch->roundLabel() }}</h3>
                    <p class="hf-small hf-muted hf-mb-1">
                        {{ $nextMatch->matchSlots->first()?->start_at?->format('M d, Y') ?? 'Date TBD' }} ·
                        {{ $nextMatch->matchSlots->first()?->start_at?->format('g:i A') ?? 'Time TBD' }}
                    </p>
                    <a class="hf-btn hf-btn--sm" href="{{ route('gameStats', ['match' => $nextMatch->id]) }}" data-testid="team-next-game">Game details</a>
                @else
                    <p class="hf-small hf-muted hf-mb-0">No scheduled game. The bracket or schedule may still be in progress.</p>
                @endif
            </div>

            <div class="hf-card hf-mb-3">
                <span class="hf-kpi-label">Bracket position</span>
                @if ($bracketMatch)
                    <h3 class="hf-section-title hf-mt-1">{{ $bracketMatch->roundLabel() }} · Slot {{ $bracketMatch->match_number }}</h3>
                    <p class="hf-small hf-muted hf-mb-1"><x-status-chip :status="$bracketMatch->status" /></p>
                    <a class="hf-btn hf-btn--sm" href="{{ route('bracket', ['id' => $tournament?->id, 'match' => $bracketMatch->id]) }}" data-testid="team-bracket-link">Open bracket</a>
                @else
                    <p class="hf-small hf-muted hf-mb-0">The bracket has not been generated yet.</p>
                @endif
            </div>

            @if ($canSeePrivate)
                <div class="hf-card hf-mb-3" data-testid="team-private">
                    <span class="hf-kpi-label">Registration</span>
                    <h3 class="hf-section-title hf-mt-1">{{ match ($registration?->status) { 'approved' => 'Approved', 'rejected' => 'Rejected', default => 'Pending' } }}</h3>
                    <p class="hf-small hf-muted">{{ $registration?->rejection_reason ?? 'Documents, consent, and payment are tracked by the organizer.' }}</p>
                    <div class="hf-flex hf-wrap hf-gap-1 hf-mb-2">
                        @if ($docsComplete)
                            <x-status-chip status="verified" label="Documents" />
                        @else
                            <x-status-chip status="missing" label="Documents" />
                        @endif
                        @if ($consentComplete)
                            <x-status-chip status="verified" label="Consent" />
                        @else
                            <x-status-chip status="missing" label="Consent" />
                        @endif
                    </div>
                    <span class="hf-kpi-label">Payment</span>
                    <p class="hf-small hf-mb-0">
                        {{ $registration?->payment?->method ?: 'No method' }} ·
                        {{ $registration?->payment?->reference_no ?: 'No reference' }} ·
                        {{ $money($registration?->payment?->amount ?? 0) }}
                    </p>
                </div>
            @else
                <div class="hf-card hf-mb-3">
                    <span class="hf-kpi-label">Public profile</span>
                    <p class="hf-small hf-muted hf-mb-0">Payment and eligibility details are visible only to the organizer and the team's coach.</p>
                </div>
            @endif

            <div class="hf-card">
                <span class="hf-kpi-label">Tournament</span>
                <h3 class="hf-section-title hf-mt-1">{{ $tournament?->name ?? 'Tournament' }}</h3>
                <p class="hf-small hf-muted hf-mb-1">{{ $divisionName }} · {{ $tournament?->venueName() ?? 'Venue' }}</p>
                @if ($tournament)
                    <a class="hf-btn hf-btn--sm" href="{{ route('tournamentShow', ['tournament' => $tournament->id]) }}" data-testid="team-tournament-link">Tournament desk</a>
                @endif
            </div>
        </aside>
    </div>
</div>
@endsection
