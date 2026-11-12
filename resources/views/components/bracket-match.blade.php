@props(['match', 'roundLabel'])

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
@endphp

<div
    class="hf-bracket-match hf-bracket-match--{{ $match->status }}"
    data-match-id="{{ $match->id }}"
    data-testid="bracket-match-{{ $match->id }}"
    role="button"
    tabindex="0"
    aria-label="Open {{ $roundLabel }} slot {{ $match->match_number }}"
    wire:key="bracket-match-{{ $match->id }}"
    wire:click="openMatchDetail({{ $match->id }})"
    wire:keydown.enter.prevent="openMatchDetail({{ $match->id }})"
    wire:keydown.space.prevent="openMatchDetail({{ $match->id }})"
>
    <div class="hf-bracket-match__slot">{{ $roundLabel }} · Slot {{ $match->match_number }}</div>
    @if ($match->best_of > 1)
        <div class="hf-bracket-match__series hf-small hf-muted" data-testid="bracket-series-{{ $match->id }}">
            Best of {{ $match->best_of }} · Game {{ $match->game_number }}
            <span>{{ $match->series_home_wins }}–{{ $match->series_away_wins }}</span>
        </div>
    @endif
    <div class="hf-match-team {{ $winner1 ? 'is-winner' : ($decided ? 'is-loser' : '') }}">
        <x-team-shield :team="$home" />
        <span class="hf-match-name">{{ $home?->name ?? 'TBD' }}</span>
        <span class="hf-score {{ $match->home_score === null && ! $isBye ? 'hf-score--muted' : '' }}">{{ $score1 }}</span>
    </div>
    <div class="hf-match-team {{ $winner2 ? 'is-winner' : ($decided ? 'is-loser' : '') }}">
        <x-team-shield :team="$away" />
        <span class="hf-match-name">{{ $away?->name ?? 'TBD' }}</span>
        <span class="hf-score {{ $match->away_score === null ? 'hf-score--muted' : '' }}">{{ $score2 }}</span>
    </div>
</div>
