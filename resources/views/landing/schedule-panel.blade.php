<div class="hf-lp-schedule" data-schedule>
    <div class="hf-lp-schedule__controls">
        <div class="hf-lp-chip-group" role="group" aria-label="Schedule day">
            @foreach ($schedule as $day)
                <button
                    type="button"
                    class="hf-lp-chip {{ $loop->first ? 'is-active' : '' }}"
                    data-schedule-day="{{ $day['key'] }}"
                    aria-pressed="{{ $loop->first ? 'true' : 'false' }}"
                >
                    <strong>{{ $day['short'] }}</strong>
                    <span>{{ $day['subtitle'] }}</span>
                </button>
            @endforeach
        </div>

        <div class="hf-lp-chip-group" role="group" aria-label="Court filter">
            <button type="button" class="hf-lp-chip is-active" data-schedule-court="all" aria-pressed="true">All courts</button>
            <button type="button" class="hf-lp-chip" data-schedule-court="Court A" aria-pressed="false">Court A</button>
            <button type="button" class="hf-lp-chip" data-schedule-court="Court B" aria-pressed="false">Court B</button>
        </div>
    </div>

    <div class="hf-lp-schedule__list">
        @foreach ($schedule as $day)
            <div class="hf-lp-schedule__group" data-schedule-group="{{ $day['key'] }}">
                <h4 class="hf-lp-schedule__day">{{ $day['label'] }} <span>{{ $day['subtitle'] }}</span></h4>

                @foreach ($day['matches'] as $match)
                    @php
                        $home = $match['home'] ? $teams->get($match['home']) : null;
                        $away = $match['away'] ? $teams->get($match['away']) : null;
                    @endphp

                    <article
                        class="hf-lp-schedule__match"
                        data-schedule-match
                        data-day="{{ $day['key'] }}"
                        data-court="{{ $match['court'] }}"
                    >
                        <div class="hf-lp-schedule__time">
                            <strong>{{ $match['time'] }}</strong>
                            <span>{{ $match['court'] }}</span>
                        </div>

                        <div class="hf-lp-schedule__matchup">
                            <span class="hf-lp-schedule__round">{{ $match['code'] }} · {{ $match['round'] }}</span>
                            <span class="hf-lp-schedule__teams">
                                <span>{{ $home['name'] ?? $match['home_label'] ?? 'TBD' }}</span>
                                <span class="hf-lp-schedule__vs">vs</span>
                                <span>{{ $away['name'] ?? $match['away_label'] ?? 'TBD' }}</span>
                            </span>
                        </div>

                        <div class="hf-lp-schedule__status">
                            @if ($match['status'] === 'live')
                                <span class="hf-lp-live hf-lp-live--sm"><span class="hf-lp-live__dot" aria-hidden="true"></span> Live</span>
                            @else
                                <x-status-chip :status="$match['status']" />
                            @endif
                            @if ($match['home_score'] !== null)
                                <strong class="hf-lp-schedule__score">{{ $match['home_score'] }}–{{ $match['away_score'] }}</strong>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @endforeach

        <p class="hf-lp-schedule__empty" data-schedule-empty hidden>
            No games on this court for this day.
        </p>
    </div>
</div>
