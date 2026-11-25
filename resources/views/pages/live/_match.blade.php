{{-- Blade view for Match. --}}
@php
    $homeScore = $match['home_score'];
    $awayScore = $match['away_score'];
    $hasScore = $homeScore !== null && $awayScore !== null;
    $homeLeading = $hasScore && $homeScore > $awayScore;
    $awayLeading = $hasScore && $awayScore > $homeScore;
    $isLive = $match['status'] === 'in_progress';
@endphp

<article class="hf-live-match{{ $isLive ? ' is-live' : '' }}" data-live-match="{{ $match['id'] }}">
    <div class="hf-live-match__meta">
        <span class="hf-chip {{ $isLive ? 'hf-chip--primary' : 'hf-chip--slate' }}">{{ $match['status_label'] }}</span>
        <span class="hf-live-match__round">{{ $match['round'] }} · Game {{ $match['match_number'] }}</span>
    </div>

    <div class="hf-live-match__board">
        <div class="hf-live-match__team{{ $homeLeading ? ' is-leading' : '' }}">
            <span class="hf-live-match__name">{{ $match['home']['name'] ?? 'TBD' }}</span>
            <strong class="hf-live-match__score">{{ $homeScore ?? '–' }}</strong>
        </div>

        <span class="hf-live-match__dash" aria-hidden="true">–</span>

        <div class="hf-live-match__team hf-live-match__team--away{{ $awayLeading ? ' is-leading' : '' }}">
            <span class="hf-live-match__name">{{ $match['away']['name'] ?? 'TBD' }}</span>
            <strong class="hf-live-match__score">{{ $awayScore ?? '–' }}</strong>
        </div>
    </div>
</article>
