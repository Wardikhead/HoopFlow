@props(['match', 'roundLabel', 'mobile' => false, 'rescheduled' => false, 'featured' => false])

@php
    $home = $match->homeTeam;
    $away = $match->awayTeam;
    $hasHome = $match->home_team_id !== null;
    $hasAway = $match->away_team_id !== null;
    $isBye = $match->status === 'final'
        && $hasHome !== $hasAway
        && $match->home_score === null
        && $match->away_score === null;
    $winner1 = $match->winner_team_id !== null && (int) $match->winner_team_id === (int) $match->home_team_id;
    $winner2 = $match->winner_team_id !== null && (int) $match->winner_team_id === (int) $match->away_team_id;
    $decided = in_array($match->status, ['final', 'forfeit'], true);
    $score1 = $isBye ? 'BYE' : ($match->home_score !== null ? (string) $match->home_score : '–');
    $score2 = $match->away_score !== null ? (string) $match->away_score : '–';
    $suffix = $mobile ? '-mobile' : '';
    $slot = $match->matchSlots->first();
    $court = $slot?->court?->name;
    $start = $slot?->start_at;
    $statusLabel = match ($match->status) {
        'final' => 'Final',
        'forfeit' => 'Forfeit',
        'in_progress' => 'In progress',
        default => 'Scheduled',
    };
    $seriesPlayed = (int) $match->series_home_wins + (int) $match->series_away_wins;
    $pipCount = min(9, max(1, (int) $match->best_of));
    $ariaScoreHome = $isBye ? 'bye' : ($match->home_score ?? 'no score');
    $ariaScoreAway = $match->away_score ?? 'no score';
    $ariaLabel = $roundLabel.' slot '.$match->match_number.'. '
        .($home?->name ?? 'TBD').' '.$ariaScoreHome.', '
        .($away?->name ?? 'TBD').' '.$ariaScoreAway.'. '
        .$statusLabel.'. Open match details.';
@endphp

<div
    class="hf-bracket-match hf-bracket-match--{{ $match->status }}{{ $featured ? ' is-featured' : '' }}"
    data-match-id="{{ $match->id }}"
    data-testid="bracket-match-{{ $match->id }}{{ $suffix }}"
    role="button"
    tabindex="0"
    aria-label="{{ $ariaLabel }}"
    wire:key="bracket-match-{{ $match->id }}{{ $suffix }}"
    wire:click="openMatchDetail({{ $match->id }})"
    wire:keydown.enter.prevent="openMatchDetail({{ $match->id }})"
    wire:keydown.space.prevent="openMatchDetail({{ $match->id }})"
>
    <div class="hf-bracket-match__head">
        <span class="hf-bracket-match__slot">
            @if ($featured)
                <i class="bi bi-trophy-fill hf-bracket-match__trophy" aria-hidden="true"></i>
            @endif
            {{ $roundLabel }} · Slot {{ $match->match_number }}
        </span>
        <span class="hf-bracket-match__badges">
            @if ($rescheduled)
                <span class="hf-chip hf-chip--warning hf-bracket-match__status" data-testid="bracket-rescheduled-{{ $match->id }}{{ $suffix }}">
                    <i class="bi bi-arrow-repeat" aria-hidden="true"></i> Rescheduled
                </span>
            @endif
            <x-status-chip
                :status="$match->status"
                class="hf-bracket-match__status"
                data-testid="bracket-status-{{ $match->id }}{{ $suffix }}"
            />
        </span>
    </div>

    <div class="hf-bracket-match__teams">
        <div class="hf-bracket-match__team {{ $winner1 ? 'is-winner' : ($decided ? 'is-loser' : '') }}">
            <x-team-shield :team="$home" />
            <span class="hf-bracket-match__name">{{ $home?->name ?? 'TBD' }}</span>
            <span class="hf-bracket-match__score {{ $match->home_score === null && ! $isBye ? 'is-muted' : '' }}">{{ $score1 }}</span>
        </div>
        <div class="hf-bracket-match__team {{ $winner2 ? 'is-winner' : ($decided ? 'is-loser' : '') }}">
            <x-team-shield :team="$away" />
            <span class="hf-bracket-match__name">{{ $away?->name ?? 'TBD' }}</span>
            <span class="hf-bracket-match__score {{ $match->away_score === null ? 'is-muted' : '' }}">{{ $score2 }}</span>
        </div>
    </div>

    <div class="hf-bracket-match__meta">
        @if ($isBye)
            <i class="bi bi-arrow-right-circle" aria-hidden="true"></i>
            <span>Bye — advances automatically</span>
        @else
            <i class="bi bi-geo-alt" aria-hidden="true"></i>
            <span>{{ $court ?? 'Court TBD' }}</span>
            <span aria-hidden="true">·</span>
            <i class="bi bi-clock" aria-hidden="true"></i>
            <span>{{ $start?->format('M j · g:i A') ?? 'Time TBD' }}</span>
        @endif
    </div>

    @if ($match->best_of > 1)
        <div class="hf-bracket-match__series" data-testid="bracket-series-{{ $match->id }}{{ $suffix }}">
            <span class="hf-bracket-match__series-label">Best of {{ $match->best_of }} · Game {{ $match->game_number }}</span>
            <span class="hf-bracket-match__series-score">{{ $match->series_home_wins }}–{{ $match->series_away_wins }}</span>
            <span class="hf-bracket-match__pips" aria-hidden="true">
                @for ($pip = 1; $pip <= $pipCount; $pip++)
                    <span class="hf-bracket-match__pip{{ $pip <= $seriesPlayed ? ' is-on' : '' }}"></span>
                @endfor
            </span>
        </div>
    @endif
</div>
