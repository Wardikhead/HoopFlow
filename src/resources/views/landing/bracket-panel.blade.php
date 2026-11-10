<div class="hf-lp-bracket" data-bracket>
    @foreach ($bracket['rounds'] as $round)
        <div class="hf-lp-bracket__round hf-lp-bracket__round--{{ $round['key'] }}">
            <h3 class="hf-lp-bracket__title">{{ $round['label'] }}</h3>

            <div class="hf-lp-bracket__matches">
                @foreach ($round['matches'] as $match)
                    @php
                        $home = $match['home'] ? $teams->get($match['home']) : null;
                        $away = $match['away'] ? $teams->get($match['away']) : null;
                    @endphp

                    <article class="hf-lp-bracket__match hf-lp-bracket__match--{{ $match['status'] }}" data-bracket-match="{{ $match['id'] }}">
                        <header class="hf-lp-bracket__head">
                            <span class="hf-lp-bracket__code">{{ $match['code'] }}</span>
                            @if ($match['status'] === 'live')
                                <span class="hf-lp-live hf-lp-live--sm"><span class="hf-lp-live__dot" aria-hidden="true"></span> Live</span>
                            @else
                                <x-status-chip :status="$match['status']" />
                            @endif
                        </header>

                        <div class="hf-lp-bracket__team {{ $match['winner'] !== null && (int) $match['winner'] === (int) $match['home'] ? 'is-winner' : '' }}">
                            <x-landing.crest :team="$home" :label="$match['home_label']" />
                            <span class="hf-lp-bracket__name">{{ $home['name'] ?? $match['home_label'] ?? 'TBD' }}</span>
                            <strong class="hf-lp-bracket__score">{{ $match['home_score'] ?? '–' }}</strong>
                        </div>

                        <div class="hf-lp-bracket__team {{ $match['winner'] !== null && (int) $match['winner'] === (int) $match['away'] ? 'is-winner' : '' }}">
                            <x-landing.crest :team="$away" :label="$match['away_label']" />
                            <span class="hf-lp-bracket__name">{{ $away['name'] ?? $match['away_label'] ?? 'TBD' }}</span>
                            <strong class="hf-lp-bracket__score">{{ $match['away_score'] ?? '–' }}</strong>
                        </div>

                        <footer class="hf-lp-bracket__foot">
                            <span>{{ $match['day_label'] }} · {{ $match['time'] }}</span>
                            <span>{{ $match['court'] }}</span>
                        </footer>
                    </article>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
