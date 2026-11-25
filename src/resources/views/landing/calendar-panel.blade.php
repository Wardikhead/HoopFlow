{{-- Blade view for Calendar panel. --}}
<div class="hf-lp-calendar" data-calendar>
    <div class="hf-lp-calendar__head">
        <strong class="hf-lp-calendar__month">{{ $calendar['month'] }}</strong>
        <span class="hf-lp-calendar__hint">Match days highlighted. Tap a day to see its games.</span>
    </div>

    <div class="hf-lp-calendar__grid">
        @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $weekday)
            <div class="hf-lp-calendar__weekday">{{ $weekday }}</div>
        @endforeach

        @foreach ($calendar['weeks'] as $week)
            @foreach ($week as $cell)
                @if ($cell['day'] === null)
                    <div class="hf-lp-calendar__cell is-blank" aria-hidden="true"></div>
                @else
                    @php
                        $gameCount = count($cell['matches']);
                    @endphp
                    <button
                        type="button"
                        class="hf-lp-calendar__cell{{ $cell['hasGames'] ? ' has-events' : '' }}"
                        data-calendar-day="{{ $cell['day'] }}"
                        aria-pressed="false"
                        aria-label="November {{ $cell['day'] }}, {{ $gameCount }} {{ \Illuminate\Support\Str::plural('game', $gameCount) }}"
                        @if ($cell['hasGames']) data-calendar-has-games="true" @endif
                    >
                        <span>{{ $cell['day'] }}</span>
                        @if ($cell['hasGames'])
                            <span class="hf-lp-calendar__dot" aria-hidden="true"></span>
                        @endif
                    </button>
                @endif
            @endforeach
        @endforeach
    </div>

    <div class="hf-lp-calendar__detail" data-calendar-detail>
        @foreach ($calendar['weeks'] as $week)
            @foreach ($week as $cell)
                @if ($cell['hasGames'])
                    <div class="hf-lp-calendar__day-games" data-calendar-day-games="{{ $cell['day'] }}" hidden>
                        <h4 class="hf-lp-calendar__day-title">Nov {{ $cell['day'] }} · Match day</h4>
                        @foreach ($cell['matches'] as $match)
                            @php
                                $home = $match['home'] ? $teams->get($match['home']) : null;
                                $away = $match['away'] ? $teams->get($match['away']) : null;
                            @endphp
                            <div class="hf-lp-calendar__game">
                                <span class="hf-lp-calendar__time">{{ $match['time'] }} · {{ $match['court'] }}</span>
                                <span class="hf-lp-calendar__matchup">
                                    {{ $home['name'] ?? $match['home_label'] ?? 'TBD' }}
                                    <span class="hf-lp-calendar__vs">vs</span>
                                    {{ $away['name'] ?? $match['away_label'] ?? 'TBD' }}
                                </span>
                                <span class="hf-lp-calendar__round">{{ $match['code'] }} · {{ $match['round'] }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            @endforeach
        @endforeach
        </div>

        <p class="hf-lp-calendar__empty" data-calendar-empty hidden>No games on this day.</p>
    </div>