<div>
@php
    $match = $this->match;
    $home = $match?->homeTeam;
    $away = $match?->awayTeam;
    $stats = $match?->playerStats ?? collect();
    $homeStats = $match !== null ? $stats->where('team_id', $match->home_team_id)->values() : collect();
    $awayStats = $match !== null ? $stats->where('team_id', $match->away_team_id)->values() : collect();
    $canScore = $match !== null && $match->status === 'scheduled' && $home !== null && $away !== null;
    $canCorrect = $match !== null && in_array($match->status, ['final', 'forfeit'], true);
@endphp

@if ($match === null)
    <div class="hf-empty">
        <i class="bi bi-graph-up" aria-hidden="true"></i>
        <h3>Game not found</h3>
        <p>Open a game from the matchups list.</p>
        <a class="hf-btn hf-btn--primary" href="{{ url('/matches') }}">Browse matchups</a>
    </div>
@else
    <header class="hf-page-head hf-mb-3">
        <div>
            <span class="hf-eyebrow">{{ $match->tournament?->name ?? 'Tournament' }}</span>
            <h1 class="hf-page-title">Game stats</h1>
            <p class="hf-page-desc">
                {{ $home?->name ?? 'TBD' }} vs {{ $away?->name ?? 'TBD' }}
                @if ($match->home_score !== null && $match->away_score !== null)
                    · {{ $match->home_score }}–{{ $match->away_score }}
                @endif
            </p>
        </div>
    </header>

    <div class="hf-card hf-card--flat hf-mb-3">
        <div class="hf-between hf-wrap">
            <div>
                <span class="hf-kpi-label">Official result</span>
                <p class="hf-small hf-muted hf-mb-0">
                    @if ($match->status === 'scheduled')
                        {{ ($home && $away) ? 'Both teams are set. Enter the final score to record the result.' : 'Waiting for both teams before this game can be played.' }}
                    @else
                        Recorded as {{ ucfirst($match->status) }}. Corrections require a reason and are written to the audit trail.
                    @endif
                </p>
            </div>
            <div class="hf-btn-row">
                @if ($canScore)
                    <button type="button" class="hf-btn hf-btn--primary" wire:click="openScoreConsole" data-testid="stats-enter-score">
                        <i class="bi bi-pencil-square"></i> Enter final score
                    </button>
                @endif
                @if ($canCorrect)
                    <button type="button" class="hf-btn hf-btn--danger" wire:click="openCorrection" data-testid="stats-correct-score">
                        <i class="bi bi-arrow-repeat"></i> Correct score
                    </button>
                @endif
                @if (! $canScore && ! $canCorrect)
                    <span class="hf-chip hf-chip--slate"><i class="bi bi-eye"></i> Read-only view</span>
                @endif
            </div>
        </div>
    </div>

    @if ($this->showScoreConsole)
        <div class="hf-card hf-mb-3" data-testid="score-console">
            <h2 class="hf-section-title">Score console</h2>
            <div class="hf-form-grid">
                <div class="hf-field">
                    <label class="hf-label" for="score1">Home score</label>
                    <input id="score1" type="number" min="0" class="hf-input" wire:model="score1" data-testid="score-input-1">
                </div>
                <div class="hf-field">
                    <label class="hf-label" for="score2">Away score</label>
                    <input id="score2" type="number" min="0" class="hf-input" wire:model="score2" data-testid="score-input-2">
                </div>
                <div class="hf-field hf-field--full">
                    <label class="hf-label" for="scoreReason">Reason (optional)</label>
                    <textarea id="scoreReason" class="hf-textarea" rows="2" wire:model="reason" data-testid="score-reason"></textarea>
                </div>
            </div>
            <div class="hf-btn-row hf-mt-2">
                <button type="button" class="hf-btn hf-btn--primary" wire:click="submitScore" data-testid="score-finalize">Finalize score</button>
                <button type="button" class="hf-btn" wire:click="$set('showScoreConsole', false)" data-testid="score-cancel">Cancel</button>
            </div>
        </div>
    @endif

    @if ($this->showCorrection)
        <div class="hf-card hf-mb-3" data-testid="correction-dialog">
            <h2 class="hf-section-title">Correct score</h2>
            <div class="hf-form-grid">
                <div class="hf-field">
                    <label class="hf-label" for="corrScore1">{{ $home?->name ?? 'Home' }}</label>
                    <input id="corrScore1" type="number" min="0" class="hf-input" wire:model="score1" data-testid="correction-score-1">
                </div>
                <div class="hf-field">
                    <label class="hf-label" for="corrScore2">{{ $away?->name ?? 'Away' }}</label>
                    <input id="corrScore2" type="number" min="0" class="hf-input" wire:model="score2" data-testid="correction-score-2">
                </div>
                <div class="hf-field hf-field--full">
                    <label class="hf-label" for="corrReason">Reason</label>
                    <textarea id="corrReason" class="hf-textarea" rows="3" wire:model="reason" data-testid="correction-reason"></textarea>
                </div>
            </div>
            <div class="hf-btn-row hf-mt-2">
                <button type="button" class="hf-btn hf-btn--danger" wire:click="submitCorrection" data-testid="correction-save">Save correction</button>
                <button type="button" class="hf-btn" wire:click="$set('showCorrection', false)" data-testid="correction-cancel">Cancel</button>
            </div>
        </div>
    @endif

    <section class="hf-card hf-mb-3" data-testid="stats-box-score">
        <div class="hf-between hf-wrap hf-mb-2">
            <div>
                <span class="hf-eyebrow">Official box score</span>
                <h2 class="hf-section-title hf-mb-0">Player stats</h2>
            </div>
        </div>

        @foreach ([[$home, $homeStats], [$away, $awayStats]] as [$team, $rows])
            @if ($team)
                <section class="hf-mb-3" data-testid="stats-team-{{ $team->id }}">
                    <div class="hf-between hf-mb-1">
                        <span class="hf-flex hf-items-center hf-gap-2">
                            <h3 class="hf-section-title hf-mb-0">{{ $team->name }}</h3>
                        </span>
                        <span class="hf-chip hf-chip--slate">{{ $rows->count() }} players</span>
                    </div>
                    <div class="hf-table-wrap">
                        <table class="hf-table">
                            <thead>
                                <tr>
                                    <th scope="col" class="is-num">#</th>
                                    <th scope="col">Player</th>
                                    <th scope="col" class="is-num">PTS</th>
                                    <th scope="col" class="is-num">AST</th>
                                    <th scope="col" class="is-num">REB</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($rows as $stat)
                                    <tr data-testid="stats-row-{{ $stat->player_id }}">
                                        <td class="is-num">{{ $stat->player?->jersey_number ?? '–' }}</td>
                                        <td>
                                            <strong>{{ trim(($stat->player?->first_name ?? '') . ' ' . ($stat->player?->last_name ?? '')) ?: 'Player' }}</strong>
                                            <span class="hf-small hf-muted" style="display:block">{{ $stat->player?->position ?? 'Player' }}</span>
                                        </td>
                                        <td class="is-num">{{ $stat->points ?? 0 }}</td>
                                        <td class="is-num">{{ $stat->assists ?? 0 }}</td>
                                        <td class="is-num">{{ $stat->rebounds ?? 0 }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="hf-muted">No player stats recorded.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="2"><strong>Team totals</strong></td>
                                    <td class="is-num"><strong>{{ $rows->sum('points') }}</strong></td>
                                    <td class="is-num"><strong>{{ $rows->sum('assists') }}</strong></td>
                                    <td class="is-num"><strong>{{ $rows->sum('rebounds') }}</strong></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </section>
            @endif
        @endforeach
    </section>
@endif
</div>