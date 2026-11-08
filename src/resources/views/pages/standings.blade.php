@extends('layouts.public')

@section('title', 'Standings — HoopFlow')

@section('content')
<div class="hf-page">
    <section class="hf-hero hf-mb-3">
        <span class="hf-eyebrow" style="color: var(--hf-gold)">{{ $tournament?->division?->name ?? 'Division' }} · {{ $tournament?->name ?? 'Standings' }}</span>
        <h1>Standings</h1>
        <p>Wins, losses, point differential — computed from every recorded result.</p>
    </section>

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