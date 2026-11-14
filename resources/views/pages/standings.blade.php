@extends('layouts.public')

@section('title', 'Standings')
@section('meta-description', ($tournament?->name ?? 'HoopFlow').' standings · wins, losses, point differential, and the playoff cut, computed from every recorded result.')

@section('content')
<div class="hf-page">
    <x-matchday-strip
        eyebrow="{{ $tournament?->divisions?->first()?->name ?? 'Division' }} · {{ $tournament?->name ?? 'Standings' }}"
        title="Standings"
        description="Wins, losses, point differential, and the playoff cut — computed from every recorded result."
        class="hf-mb-3"
    >
        @if ($tournament)
            <a class="hf-btn" href="{{ route('bracket', ['id' => $tournament->id]) }}"><i class="bi bi-diagram-3" aria-hidden="true"></i> Bracket</a>
            <a class="hf-btn" href="{{ route('matches', ['id' => $tournament->id]) }}"><i class="bi bi-list-ul" aria-hidden="true"></i> Matchups</a>
        @endif
    </x-matchday-strip>

    @if ($tournament)
        <div class="hf-card">
            <div class="hf-table-wrap">
                <table class="hf-table hf-table--standings">
                    <caption class="hf-sr-only">Standings</caption>
                    <thead>
                        <tr>
                            <th scope="col">#</th>
                            <th scope="col">Team</th>
                            <th scope="col">Played</th>
                            <th scope="col">W</th>
                            <th scope="col">L</th>
                            <th scope="col">PF</th>
                            <th scope="col">PA</th>
                            <th scope="col">Diff</th>
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