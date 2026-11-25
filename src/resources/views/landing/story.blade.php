{{-- Blade view for Story. --}}
@php
    $rounds = $bracket['rounds'];
    $quarters = $rounds[0]['matches'] ?? [];
    $semis = $rounds[1]['matches'] ?? [];
    $final = $rounds[2]['matches'][0] ?? null;
    $crest = fn ($team, $label) => $team ?? $label ?? 'TBD';
@endphp

<section id="bracket-story" class="hf-lp-story" data-story aria-labelledby="story-title">
    <div class="hf-lp-shell">
        <header class="hf-lp-story__head hf-lp-wipe">
            <span class="hf-eyebrow hf-lp-eyebrow">From seeds to the final</span>
            <h2 id="story-title">One bracket. <em>Zero hand-offs.</em></h2>
            <p>Scroll the night. Every result moves the same record forward — no sheet to re-type, no group chat to update.</p>
        </header>

        <div class="hf-lp-story__layout">
            <ol class="hf-lp-story__steps">
                <li class="hf-lp-story__step is-active" data-story-step="0">
                    <span class="hf-lp-story__num">01</span>
                    <div>
                        <strong>Approved teams seed themselves</strong>
                        <p>Rosters clear eligibility, then the bracket fills with real seeds instead of names typed twice.</p>
                    </div>
                </li>
                <li class="hf-lp-story__step" data-story-step="1">
                    <span class="hf-lp-story__num">02</span>
                    <div>
                        <strong>The quarters settle on one score</strong>
                        <p>Final scores advance winners, and the semifinal slots fill the moment the buzzer lands.</p>
                    </div>
                </li>
                <li class="hf-lp-story__step" data-story-step="2">
                    <span class="hf-lp-story__num">03</span>
                    <div>
                        <strong>The final is already waiting</strong>
                        <p>Feeder matches resolve into the championship game — and the matchday wire announces it.</p>
                    </div>
                </li>
            </ol>

            <div class="hf-lp-story__stage" data-story-stage data-step="0" aria-hidden="true">
                <div class="hf-lp-story__board">
                    <div class="hf-lp-story__round hf-lp-story__round--qf">
                        <span class="hf-lp-story__label">Quarterfinals · Nov 14</span>
                        <div class="hf-lp-story__grid hf-lp-story__grid--qf">
                            @foreach ($quarters as $match)
                                @php
                                    $home = $match['home'] ? $teams->get($match['home']) : null;
                                    $away = $match['away'] ? $teams->get($match['away']) : null;
                                    $homeWon = $match['winner'] !== null && (int) $match['winner'] === (int) $match['home'];
                                    $awayWon = $match['winner'] !== null && (int) $match['winner'] === (int) $match['away'];
                                @endphp
                                <div class="hf-lp-story__match" data-story-match>
                                    <span class="hf-lp-story__code">{{ $match['code'] }}</span>
                                    <div class="hf-lp-story__side {{ $homeWon ? 'is-winner' : '' }}">
                                        <x-landing.crest :team="$home" :label="$match['home_label']" />
                                        <span>{{ $home['name'] ?? $match['home_label'] ?? 'TBD' }}</span>
                                        <b>{{ $match['home_score'] ?? '–' }}</b>
                                    </div>
                                    <div class="hf-lp-story__side {{ $awayWon ? 'is-winner' : '' }}">
                                        <x-landing.crest :team="$away" :label="$match['away_label']" />
                                        <span>{{ $away['name'] ?? $match['away_label'] ?? 'TBD' }}</span>
                                        <b>{{ $match['away_score'] ?? '–' }}</b>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="hf-lp-story__wire" data-story-wire></div>

                    <div class="hf-lp-story__round hf-lp-story__round--sf">
                        <span class="hf-lp-story__label">Semifinals · Nov 21</span>
                        <div class="hf-lp-story__grid hf-lp-story__grid--sf">
                            @foreach ($semis as $match)
                                @php
                                    $home = $match['home'] ? $teams->get($match['home']) : null;
                                    $away = $match['away'] ? $teams->get($match['away']) : null;
                                @endphp
                                <div class="hf-lp-story__match hf-lp-story__match--pending" data-story-match>
                                    <span class="hf-lp-story__code">{{ $match['code'] }}</span>
                                    <div class="hf-lp-story__side">
                                        <x-landing.crest :team="$home" :label="$match['home_label']" />
                                        <span>{{ $home['name'] ?? $match['home_label'] ?? 'TBD' }}</span>
                                        <b>–</b>
                                    </div>
                                    <div class="hf-lp-story__side">
                                        <x-landing.crest :team="$away" :label="$match['away_label']" />
                                        <span>{{ $away['name'] ?? $match['away_label'] ?? 'TBD' }}</span>
                                        <b>–</b>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="hf-lp-story__wire" data-story-wire></div>

                    <div class="hf-lp-story__round hf-lp-story__round--final">
                        <span class="hf-lp-story__label">Final · Nov 22</span>
                        <div class="hf-lp-story__grid">
                            <div class="hf-lp-story__match hf-lp-story__match--final hf-lp-story__match--pending" data-story-match>
                                <span class="hf-lp-story__code">{{ $final['code'] ?? 'F' }}</span>
                                <div class="hf-lp-story__side">
                                    <x-landing.crest :team="null" :label="$final['home_label'] ?? 'Winner SF1'" />
                                    <span>{{ $final['home_label'] ?? 'Winner SF1' }}</span>
                                    <b>–</b>
                                </div>
                                <div class="hf-lp-story__side">
                                    <x-landing.crest :team="null" :label="$final['away_label'] ?? 'Winner SF2'" />
                                    <span>{{ $final['away_label'] ?? 'Winner SF2' }}</span>
                                    <b>–</b>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <span class="hf-lp-story__ticket">
                    <i class="bi bi-lightning-charge" aria-hidden="true"></i>
                    Winners advance automatically
                </span>
            </div>
        </div>
    </div>
</section>
