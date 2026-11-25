{{-- Blade view for Standings table. --}}
@props(['rows' => collect(), 'limit' => null, 'qualificationLine' => null, 'tournamentId' => null])

@php
    $allRows = collect($rows);
    $visible = $limit !== null ? $allRows->take((int) $limit) : $allRows;
    $showQualificationLine = $qualificationLine ?? ($limit === null);
    $lastQualified = $showQualificationLine
        ? $visible->filter(fn (array $row): bool => (bool) ($row['qualified'] ?? false))->count()
        : 0;
@endphp

@if ($allRows->isEmpty())
    <div class="hf-empty">
        <i class="bi bi-bar-chart" aria-hidden="true"></i>
        <h3>No standings yet</h3>
        <p>Standings appear as soon as approved teams have recorded results.</p>
    </div>
@else
    <div class="hf-table-wrap">
        <table class="hf-table hf-table--standings">
            <caption class="hf-sr-only">Tournament standings</caption>
            <thead>
                <tr>
                    <th scope="col">#</th>
                    <th scope="col">Team</th>
                    <th scope="col" class="is-num">W</th>
                    <th scope="col" class="is-num">L</th>
                    <th scope="col" class="is-num">PF</th>
                    <th scope="col" class="is-num">PA</th>
                    <th scope="col" class="is-num">+/-</th>
                    <th scope="col" class="is-num">Seed</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($visible as $index => $row)
                    <tr class="{{ ($row['qualified'] ?? false) ? 'is-qualified' : '' }}" data-testid="standings-row-{{ $row['team_id'] }}">
                        <td class="is-num">{{ $row['rank'] }}</td>
                        <td>
                            <a class="hf-flex hf-items-center hf-gap-1" href="{{ route('teamShow', array_filter(['team' => $row['team_id'], 'tournament' => $tournamentId])) }}">
                                <x-team-shield :team="$row['team'] ?? null" />
                                <strong>{{ $row['team_name'] ?? 'Team' }}</strong>
                            </a>
                        </td>
                        <td class="is-num">{{ $row['wins'] }}</td>
                        <td class="is-num">{{ $row['losses'] }}</td>
                        <td class="is-num">{{ $row['points_for'] }}</td>
                        <td class="is-num">{{ $row['points_against'] }}</td>
                        <td class="is-num">{{ $row['point_diff'] > 0 ? '+' : '' }}{{ $row['point_diff'] }}</td>
                        <td class="is-num">{{ $row['seed'] ?? '–' }}</td>
                    </tr>
                    @if ($lastQualified === $index + 1)
                        <tr class="hf-qual-line"><td colspan="8"></td></tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="hf-standings-cards">
        @foreach ($visible as $row)
            <a class="hf-card hf-card--flat" href="{{ route('teamShow', array_filter(['team' => $row['team_id'], 'tournament' => $tournamentId])) }}" data-testid="standings-card-{{ $row['team_id'] }}">
                <div class="hf-between">
                    <span class="hf-flex hf-items-center hf-gap-1">
                        <span class="hf-kpi-label">#{{ $row['rank'] }}</span>
                        <x-team-shield :team="$row['team'] ?? null" />
                        <strong>{{ $row['team_name'] ?? 'Team' }}</strong>
                    </span>
                    @if ($row['qualified'] ?? false)
                        <span class="hf-chip hf-chip--gold">Playoff cut</span>
                    @endif
                </div>
                <div class="hf-flex hf-gap-3 hf-mt-1 hf-small">
                    <span><strong>{{ $row['wins'] }}</strong> W</span>
                    <span><strong>{{ $row['losses'] }}</strong> L</span>
                    <span><strong>{{ $row['point_diff'] > 0 ? '+' : '' }}{{ $row['point_diff'] }}</strong> DIFF</span>
                </div>
            </a>
        @endforeach
    </div>
@endif
