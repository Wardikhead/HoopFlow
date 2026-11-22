@php
    $rows = collect($standings)->take(5);
@endphp

<section id="compare" class="hf-lp-compare" data-compare aria-labelledby="compare-title">
    <div class="hf-lp-shell">
        <header class="hf-lp-compare__head hf-lp-wipe">
            <span class="hf-eyebrow hf-lp-eyebrow">Still on a spreadsheet?</span>
            <h2 id="compare-title">Drag the line. <em>See the difference.</em></h2>
            <p>Same league, same night. One side is five tabs and a VLOOKUP; the other is a single record everyone can read.</p>
        </header>

        <div class="hf-lp-compare__frame">
            <div class="hf-lp-compare__layer hf-lp-compare__layer--before">
                <span class="hf-lp-compare__sheet-tab">
                    <i class="bi bi-file-earmark-spreadsheet" aria-hidden="true"></i>
                    U16_FINAL_v7 (copy).xlsx
                </span>

                <div class="hf-lp-compare__sheet" aria-hidden="true">
                    <div class="hf-lp-compare__sheet-row hf-lp-compare__sheet-row--head">
                        <span>Team</span><span>W</span><span>L</span><span>PF</span><span>PA</span><span>DIFF</span>
                    </div>
                    <div class="hf-lp-compare__sheet-row"><span>Vipers</span><span>4</span><span>0</span><span>302</span><span>224</span><span>#REF!</span></div>
                    <div class="hf-lp-compare__sheet-row is-broken"><span>Panthers</span><span>3</span><span>1</span><span>288</span><span>=SUM(</span><span>###</span></div>
                    <div class="hf-lp-compare__sheet-row"><span>Saints</span><span>3</span><span>1</span><span>274</span><span>243</span><span>31</span></div>
                    <div class="hf-lp-compare__sheet-row is-stale"><span>Lions</span><span>2</span><span>2</span><span>OLD</span><span>243</span><span>?</span></div>
                    <div class="hf-lp-compare__sheet-row"><span>Mavericks</span><span>2</span><span>2</span><span>249</span><span>247</span><span>2</span></div>
                    <div class="hf-lp-compare__sheet-row is-broken"><span>Bolts</span><span>?</span><span>?</span><span>238</span><span>245</span><span>#DIV/0!</span></div>
                </div>

                <ul class="hf-lp-compare__notes">
                    <li><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> Which file is the real one?</li>
                    <li><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> Who typed the 3–1?</li>
                    <li><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> Sent to the group chat?</li>
                </ul>
            </div>

            <div class="hf-lp-compare__layer hf-lp-compare__layer--after">
                <span class="hf-lp-compare__sheet-tab hf-lp-compare__sheet-tab--clean">
                    <i class="bi bi-trophy" aria-hidden="true"></i>
                    {{ $tournament['name'] }} · {{ $tournament['division'] }}
                </span>

                <ul class="hf-lp-compare__list">
                    @foreach ($rows as $row)
                        @php $team = $teams->get($row['team_id']); @endphp
                        <li class="hf-lp-compare__row {{ $loop->first ? 'is-leader' : '' }}">
                            <span class="hf-lp-compare__rank">{{ str_pad((string) $row['rank'], 2, '0', STR_PAD_LEFT) }}</span>
                            <x-landing.crest :team="$team" />
                            <span class="hf-lp-compare__name">{{ $team['name'] ?? 'Team' }}</span>
                            <span class="hf-lp-compare__record">{{ $row['wins'] }}–{{ $row['losses'] }}</span>
                            <span class="hf-lp-compare__diff">{{ $row['point_diff'] > 0 ? '+' : '' }}{{ $row['point_diff'] }}</span>
                        </li>
                    @endforeach
                </ul>

                <p class="hf-lp-compare__live">
                    <span class="hf-lp-live__dot" aria-hidden="true"></span>
                    Updates the moment a score is final
                </p>
            </div>

            <span class="hf-lp-compare__handle" aria-hidden="true">
                <i class="bi bi-arrows-move" aria-hidden="true"></i>
            </span>

            <label class="hf-lp-compare__control">
                <span class="hf-lp-compare__control-label">Compare spreadsheet and HoopFlow</span>
                <input
                    type="range"
                    min="0"
                    max="100"
                    value="50"
                    step="1"
                    data-compare-range
                    aria-label="Reveal HoopFlow standings over the spreadsheet"
                >
            </label>
        </div>
    </div>
</section>
