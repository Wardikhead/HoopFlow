@extends('layouts.public')

@section('title', $team->name . ' — HoopFlow')

@section('content')
<div class="hf-page">
    <section class="hf-hero hf-mb-3">
        <span class="hf-eyebrow" style="color: var(--hf-gold)">{{ $registration?->division?->name ?? 'Division' }} · {{ $registration?->status ?? 'Team' }}</span>
        <h1>{{ $team->name }}</h1>
        <p>{{ $team->coachUser?->name ?? 'Coach' }} · {{ $registration?->tournament?->name ?? 'Tournament' }}</p>
        <div class="hf-stat-strip">
            <div><span>Roster</span><strong>{{ $team->players->count() }}</strong></div>
            <div><span>Status</span><strong>{{ $registration?->status ?? '—' }}</strong></div>
            <div><span>Payment</span><strong>{{ $registration?->payment?->status ?? '—' }}</strong></div>
            <div><span>Seed</span><strong>{{ $registration?->seed ?? 'TBD' }}</strong></div>
        </div>
    </section>

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
        </div>

        <aside>
            <div class="hf-card hf-mb-3">
                <span class="hf-kpi-label">Registration</span>
                <h3 class="hf-section-title hf-mt-1">{{ $registration?->status ?? '—' }}</h3>
                <p class="hf-small hf-muted">{{ $registration?->rejection_reason ?? 'Documents, consent, and payment are tracked by the organizer.' }}</p>
                @if ($registration)
                    <div class="hf-flex hf-wrap hf-gap-1 hf-mb-2">
                        <span class="hf-chip {{ $team->players->every(fn ($p) => $p->eligibility_status === 'verified') ? 'hf-chip--success' : 'hf-chip--danger' }}">
                            {{ $team->players->every(fn ($p) => $p->eligibility_status === 'verified') ? 'Documents' : 'Missing docs' }}
                        </span>
                        <span class="hf-chip {{ $team->players->every(fn ($p) => ! is_null($p->consent_at)) ? 'hf-chip--success' : 'hf-chip--danger' }}">
                            {{ $team->players->every(fn ($p) => ! is_null($p->consent_at)) ? 'Consent' : 'Missing consent' }}
                        </span>
                    </div>
                @endif
            </div>

            <div class="hf-card hf-mb-3">
                <span class="hf-kpi-label">Payment</span>
                <h3 class="hf-section-title hf-mt-1">{{ $registration?->payment?->method ?? 'No method' }}</h3>
                <p class="hf-small hf-muted hf-mb-1">{{ $registration?->payment?->reference_no ?? 'No reference' }} · ₱{{ number_format((float) ($registration?->payment?->amount ?? 0), 2) }}</p>
                <p class="hf-small hf-muted hf-mb-0">Status: {{ $registration?->payment?->status ?? '—' }}</p>
            </div>

            <div class="hf-card">
                <span class="hf-kpi-label">Tournament</span>
                <h3 class="hf-section-title hf-mt-1">{{ $registration?->tournament?->name ?? '—' }}</h3>
                <p class="hf-small hf-muted hf-mb-1">{{ $registration?->division?->name ?? '' }}</p>
                @if ($registration?->tournament)
                <a class="hf-btn hf-btn--sm" href="{{ route('tournamentShow', ['tournament' => $registration->tournament->id]) }}">Tournament desk</a>
                @endif
            </div>
        </aside>
    </div>
</div>
@endsection
