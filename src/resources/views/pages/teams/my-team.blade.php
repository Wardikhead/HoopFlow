@extends('layouts.public')

@section('title', 'My team')

@section('content')
<div class="hf-page">
    @if (! $team)
        <div class="hf-card">
            <h2 class="hf-section-title">No team attached</h2>
            <p class="hf-muted">Register a team for an open tournament to start the coach journey.</p>
            <a class="hf-btn hf-btn--primary" href="{{ route('tournaments') }}">Browse tournaments</a>
        </div>
    @else
        @php
            $record = app(\App\Services\DashboardService::class)->teamRecord($team);
            $locked = (bool) ($registration?->roster_locked_at);
            $consentCount = $team->players->filter(fn ($p) => ! is_null($p->consent_at))->count();
            $docsCount = $team->players->filter(fn ($p) => $p->eligibility_status === 'verified')->count();
        @endphp

        <header class="hf-page-head hf-mb-3">
            <div>
                <span class="hf-eyebrow">{{ $registration?->tournament?->name ?? 'Tournament' }} · {{ $registration?->status ?? '' }}</span>
                <h1 class="hf-page-title">{{ $team->name }}</h1>
                <p class="hf-page-desc">{{ $team->coachUser?->name ?? ($user?->name ?? 'Coach') }}@if ($registration?->division?->name) · {{ $registration->division->name }}@endif</p>
                <div class="hf-page-meta">
                    <span><i class="bi bi-shield-check"></i> {{ $registration?->status ?? 'Pending' }}</span>
                    <span><i class="bi bi-cash-coin"></i> {{ $registration?->payment?->status ?? 'No payment' }}</span>
                    <span><i class="bi bi-people"></i> {{ $team->players->count() }} players</span>
                </div>
            </div>
            <div class="hf-page-actions">
                <a class="hf-btn" href="{{ route('teamShow', ['team' => $team->id]) }}">Public profile</a>
                <a class="hf-btn" href="{{ route('tournaments') }}">Register another team</a>
            </div>
        </header>

        <div class="hf-grid hf-grid--4 hf-mb-3">
            <div class="hf-card">
                <span class="hf-kpi-label">Record</span>
                <strong class="hf-kpi-value">{{ $record['wins'] }}-{{ $record['losses'] }}</strong>
            </div>
            <div class="hf-card">
                <span class="hf-kpi-label">Roster</span>
                <strong class="hf-kpi-value">{{ $team->players->count() }}</strong>
                <p class="hf-small hf-muted hf-mb-0">{{ $consentCount }} consents · {{ $docsCount }} verified</p>
            </div>
            <div class="hf-card">
                <span class="hf-kpi-label">Seed</span>
                <strong class="hf-kpi-value">{{ $registration?->seed ?? 'TBD' }}</strong>
            </div>
            <div class="hf-card">
                <span class="hf-kpi-label">Entry fee</span>
                <strong class="hf-kpi-value">₱{{ number_format((float) ($registration?->payment?->amount ?? $registration?->division?->entry_fee ?? 0), 2) }}</strong>
            </div>
        </div>

        <div class="hf-split">
            <div>
                <h2 class="hf-section-title">Roster ({{ $team->players->count() }})</h2>
                <div class="hf-card hf-mb-3">
                    @if ($team->players->isEmpty())
                        <p class="hf-muted">No players yet.</p>
                    @else
                        <div class="hf-table-wrap">
                            <table class="hf-table">
                                <thead>
                                    <tr>
                                        <th scope="col">#</th>
                                        <th scope="col">Player</th>
                                        <th scope="col">Position</th>
                                        <th scope="col">Age</th>
                                        <th scope="col">Consent</th>
                                        <th scope="col">Documents</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($team->players as $player)
                                        <tr>
                                            <td>{{ $player->jersey_number }}</td>
                                            <td><strong>{{ $player->first_name }} {{ $player->last_name }}</strong></td>
                                            <td>{{ $player->position }}</td>
                                            <td>{{ optional($player->birthdate)->age ?? '—' }}</td>
                                            <td>
                                                @if ($player->consent_at)
                                                    <span class="hf-chip hf-chip--success">On file</span>
                                                @else
                                                    <span class="hf-chip hf-chip--danger">Missing</span>
                                                @endif
                                            </td>
                                            <td>{{ $player->eligibility_status }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                <h2 class="hf-section-title">Registration status</h2>
                <div class="hf-card hf-mb-3">
                    <p class="hf-small hf-muted hf-mb-1">Status: {{ $registration?->status ?? '—' }}</p>
                    @if ($registration?->rejection_reason)
                        <p class="hf-small hf-muted hf-mb-1">{{ $registration->rejection_reason }}</p>
                    @endif
                    <p class="hf-small hf-muted hf-mb-0">Submitted {{ optional($registration?->submitted_at)->format('M d, Y') }}</p>
                </div>
            </div>

            <aside>
                <div class="hf-card hf-mb-3">
                    <span class="hf-kpi-label">Eligibility</span>
                    <h3 class="hf-section-title hf-mt-1">{{ $locked ? 'Roster locked' : 'Roster editable' }}</h3>
                    <div class="hf-flex hf-wrap hf-gap-1 hf-mt-1">
                        <span class="hf-chip {{ $consentCount === $team->players->count() ? 'hf-chip--success' : 'hf-chip--danger' }}">
                            {{ $consentCount === $team->players->count() ? 'Consent complete' : ($team->players->count() - $consentCount) . ' consent missing' }}
                        </span>
                        <span class="hf-chip {{ $docsCount === $team->players->count() ? 'hf-chip--success' : 'hf-chip--danger' }}">
                            {{ $docsCount === $team->players->count() ? 'Documents complete' : ($team->players->count() - $docsCount) . ' documents pending' }}
                        </span>
                    </div>
                </div>

                <div class="hf-card hf-mb-3">
                    <span class="hf-kpi-label">Payment</span>
                    <span class="hf-chip {{ $registration?->payment?->status === 'verified' ? 'hf-chip--success' : 'hf-chip--warning' }}">{{ $registration?->payment?->status ?? '—' }}</span>
                    <h3 class="hf-section-title hf-mt-1">{{ $registration?->payment?->method ?? 'No method' }}</h3>
                    <p class="hf-small hf-muted hf-mb-1">{{ $registration?->payment?->reference_no ?? 'No reference' }} · ₱{{ number_format((float) ($registration?->payment?->amount ?? 0), 2) }}</p>
                    @if ($registration?->payment?->verified_at)
                        <p class="hf-small hf-muted hf-mb-0">Verified {{ $registration->payment->verified_at->format('M d, Y') }}</p>
                    @endif
                </div>

                <div class="hf-card">
                    <span class="hf-kpi-label">Tournament</span>
                    <h3 class="hf-section-title hf-mt-1">{{ $registration?->tournament?->name ?? '—' }}</h3>
                    <p class="hf-small hf-muted hf-mb-1">@if ($registration?->division?->name){{ $registration->division->name }}@endif</p>
                    @if ($registration?->tournament)
                    <a class="hf-btn hf-btn--sm" href="{{ route('tournamentShow', ['tournament' => $registration->tournament->id]) }}">Tournament desk</a>
                    @endif
                </div>
            </aside>
        </div>
    @endif
</div>
@endsection
