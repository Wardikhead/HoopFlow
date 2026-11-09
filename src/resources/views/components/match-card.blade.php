@props(['match', 'href', 'testid' => null])

@php
    $home = $match->homeTeam;
    $away = $match->awayTeam;
    $slot = $match->matchSlots->first();
    $court = $slot?->court;
    $isBye = $match->isByeGame();
    $winnerHome = $match->winner_team_id !== null && (int) $match->winner_team_id === (int) $match->home_team_id;
    $winnerAway = $match->winner_team_id !== null && (int) $match->winner_team_id === (int) $match->away_team_id;
    $score1 = $isBye ? 'BYE' : ($match->home_score ?? '–');
    $score2 = $isBye ? '–' : ($match->away_score ?? '–');
    $when = $slot?->start_at !== null
        ? $slot->start_at->format('M d, Y').' · '.$slot->start_at->format('g:i A')
        : 'Time TBD';
@endphp

<a
    class="hf-match-card hf-match-card--{{ $match->status }}"
    data-match-id="{{ $match->id }}"
    data-testid="{{ $testid ?? 'match-card-'.$match->id }}"
    href="{{ $href }}"
>
    <div class="hf-match-head">
        <span>{{ $match->roundLabel() }}{{ $court ? ' · '.$court->name : '' }}</span>
        <x-status-chip :status="$match->status" />
    </div>
    <div class="hf-match-team {{ $winnerHome ? 'is-winner' : ($match->status !== 'scheduled' ? 'is-loser' : '') }}">
        <x-team-shield :team="$home" />
        <span class="hf-match-name">{{ $home?->name ?? 'TBD' }}</span>
        <span class="hf-score {{ $match->home_score === null && ! $isBye ? 'hf-score--muted' : '' }}">{{ $score1 }}</span>
    </div>
    <div class="hf-match-team {{ $winnerAway ? 'is-winner' : ($match->status !== 'scheduled' ? 'is-loser' : '') }}">
        <x-team-shield :team="$away" />
        <span class="hf-match-name">{{ $away?->name ?? 'TBD' }}</span>
        <span class="hf-score {{ $match->away_score === null ? 'hf-score--muted' : '' }}">{{ $score2 }}</span>
    </div>
    <div class="hf-match-head">
        <span>{{ $when }}</span>
        <span>{{ $isBye ? 'Bye' : $match->roundLabel() }}</span>
    </div>
</a>
