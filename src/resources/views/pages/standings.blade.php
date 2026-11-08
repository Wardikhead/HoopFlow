@extends('layouts.public')

@section('title', 'Standings')

@section('content')
<div class="hf-page">
    <header class="hf-page-head hf-mb-3">
        <div>
            <span class="hf-eyebrow">{{ $tournament?->divisions?->first()?->name ?? 'Division' }} · {{ $tournament?->name ?? 'Standings' }}</span>
            <h1 class="hf-page-title">Standings</h1>
            <p class="hf-page-desc">Wins, losses, point differential, and the playoff cut — computed from every recorded result.</p>
        </div>
        @if ($tournament)
            <div class="hf-page-actions">
                <a class="hf-btn" href="{{ route('bracket', ['id' => $tournament->id]) }}"><i class="bi bi-diagram-3"></i> Bracket</a>
                <a class="hf-btn" href="{{ route('matches', ['id' => $tournament->id]) }}"><i class="bi bi-list-ul"></i> Matchups</a>
            </div>
        @endif
    </header>

    @if ($tournament)
        <div class="hf-card">
            <div class="hf-table-wrap">
                <table class="hf-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Team</th>
                            <th>Played</th>
                            <th>W</th>
                            <th>L</th>
                            <th>PF</th>
                            <th>PA</th>
                            <th>Diff</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($standings as $i => $row)
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td>{{ $row['team_name'] ?? 'Team' }}</td>
                                <td>{{ $row['played'] ?? 0 }}</td>
                                <td>{{ $row['wins'] ?? 0 }}</td>
                                <td>{{ $row['losses'] ?? 0 }}</td>
                                <td>{{ $row['points_for'] ?? 0 }}</td>
                                <td>{{ $row['points_against'] ?? 0 }}</td>
                                <td>{{ $row['point_diff'] ?? 0 }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="hf-muted">No standings yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <div class="hf-card">
            <p class="hf-muted hf-mb-0">Tournament not found.</p>
        </div>
    @endif
</div>
@endsection