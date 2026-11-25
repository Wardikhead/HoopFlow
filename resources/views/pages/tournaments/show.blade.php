{{-- Blade view for Show. --}}
@extends('layouts.public')

@section('title', $tournament->name)

@section('meta-description', $tournament->name.' · '.$tournament->venueName().' · '.($tournament->start_date?->format('M j, Y') ?? 'TBD').' to '.($tournament->end_date?->format('M j, Y') ?? 'TBD').'. Bracket, schedule, standings, and announcements.')

@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Home', 'href' => route('dashboard')],
        ['label' => 'Tournaments', 'href' => route('tournaments')],
        ['label' => $tournament->name],
    ]" />
@endsection

@section('content')
<div class="hf-page">
    <x-matchday-strip
        eyebrow="{{ $tournament->divisions?->first()?->name ?? 'Division' }} · {{ $tournament->statusLabel() }}"
        :title="$tournament->name"
        :description="$tournament->description"
        :meta="[
            ['icon' => 'calendar3', 'label' => ($tournament->start_date?->format('M j, Y') ?? 'TBD').' – '.($tournament->end_date?->format('M j, Y') ?? 'TBD')],
            ['icon' => 'cash', 'label' => number_format($tournament->divisions?->first()?->entry_fee ?? 0, 2).' entry'],
            ['icon' => 'diagram-3', 'label' => 'Single elimination'],
            ['icon' => 'people', 'label' => ($tournament->divisions?->first()?->max_teams ?? ($tournament->rules_json['slots'] ?? 0)).' slots'],
        ]"
        class="hf-mb-3"
    >
        <a class="hf-btn hf-btn--primary" href="{{ route('bracket', ['id' => $tournament->id]) }}">Bracket</a>
        <a class="hf-btn" href="{{ route('schedule', ['id' => $tournament->id]) }}">Schedule</a>
        <a class="hf-btn" href="{{ route('standings', ['id' => $tournament->id]) }}">Standings</a>
        @if(auth()->user()?->hasRole('organizer'))
            <a class="hf-btn" href="{{ route('announcementCreate', ['id' => $tournament->id]) }}">Announce</a>
        @endif
    </x-matchday-strip>

    <div class="hf-grid hf-grid--4 hf-mb-3">
        <div class="hf-card">
            <span class="hf-kpi-label">Registered</span>
            <strong class="hf-kpi-value">{{ $tournament->registrations->count() }}</strong>
        </div>
        <div class="hf-card">
            <span class="hf-kpi-label">Approved</span>
            <strong class="hf-kpi-value">{{ $tournament->registrations->where('status','approved')->count() }}</strong>
        </div>
        <div class="hf-card">
            <span class="hf-kpi-label">Matches</span>
            <strong class="hf-kpi-value">{{ $tournament->matches->count() }}</strong>
        </div>
        <div class="hf-card">
            <span class="hf-kpi-label">Announcements</span>
            <strong class="hf-kpi-value">{{ $tournament->announcements->count() }}</strong>
        </div>
    </div>

    <div class="hf-split">
        <div>
            <h2 class="hf-section-title">Team list</h2>
            <div class="hf-grid">
                @forelse($tournament->registrations as $reg)
                    <a class="hf-card hf-card--flat" href="{{ route('teamShow', ['team' => $reg->team_id, 'tournament' => $reg->tournament_id]) }}">
                        <div class="hf-between">
                            <span><strong>{{ $reg->team?->name }}</strong></span>
                            <span class="hf-chip">{{ $reg->status }}</span>
                        </div>
                    </a>
                @empty
                    <p class="hf-muted">No teams yet.</p>
                @endforelse
            </div>

            <h2 class="hf-section-title hf-mt-3">Announcements</h2>
            <div class="hf-grid">
                @forelse($tournament->announcements as $ann)
                    <div class="hf-card">
                        <h3 class="hf-section-title">{{ $ann->title }}</h3>
                        <p class="hf-small hf-muted">{{ $ann->body }}</p>
                    </div>
                @empty
                    <p class="hf-muted">No announcements.</p>
                @endforelse
            </div>
        </div>
        <aside>
            <div class="hf-card hf-mb-3">
                <span class="hf-kpi-label">Bracket</span>
                <p class="hf-small hf-muted">Open the bracket view for full details.</p>
                <a class="hf-btn hf-btn--sm" href="{{ route('bracket', ['id' => $tournament->id]) }}">Open bracket</a>
            </div>
            <div class="hf-card">
                <span class="hf-kpi-label">Schedule</span>
                <p class="hf-small hf-muted">Open the schedule view.</p>
                <a class="hf-btn hf-btn--sm" href="{{ route('schedule', ['id' => $tournament->id]) }}">Open schedule</a>
            </div>
        </aside>
    </div>
</div>
@endsection
