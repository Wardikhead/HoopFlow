{{-- Blade view for Match card. --}}
@props(['match', 'href', 'compact' => false, 'testid' => null, 'rescheduled' => false])

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
        <span class="hf-match-head__statuses">
            @if ($rescheduled)
                <span class="hf-chip hf-chip--warning" data-testid="match-rescheduled-{{ $match->id }}">
                    <i class="bi bi-arrow-repeat" aria-hidden="true"></i> Rescheduled
                </span>
            @endif
            <x-status-chip :status="$match->status" />
        </span>
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
    @unless ($compact)
        <div class="hf-match-head">
            <span
                class="hf-match-date{{ $rescheduled ? ' is-rescheduled' : '' }}"
                data-rescheduled="{{ $rescheduled ? 'true' : 'false' }}"
                data-testid="match-date-{{ $match->id }}"
            >{{ $when }}</span>
            <span>{{ $isBye ? 'Bye' : $match->roundLabel() }}</span>
        </div>
    @endunless
</a>
