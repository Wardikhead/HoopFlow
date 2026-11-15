<div class="hf-lp-standings">
    <div class="hf-lp-standings__caption">
        <strong>Regular season</strong>
        <span>Top 8 advance to the quarterfinals.</span>
    </div>

    <div class="hf-lp-table-wrap">
        <table class="hf-lp-standings__table">
            <caption class="hf-sr-only">Regular season standings</caption>
            <thead>
                <tr>
                    <th scope="col">#</th>
                    <th scope="col">Team</th>
                    <th scope="col" class="hf-lp-num">W</th>
                    <th scope="col" class="hf-lp-num">L</th>
                    <th scope="col" class="hf-lp-num">PF</th>
                    <th scope="col" class="hf-lp-num">PA</th>
                    <th scope="col" class="hf-lp-num">+/−</th>
                    <th scope="col" class="hf-lp-num">Seed</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($standings as $row)
                    @php
                        $team = $teams->get($row['team_id']);
                        $diff = $row['point_diff'] > 0
                            ? '+'.$row['point_diff']
                            : ($row['point_diff'] < 0 ? '−'.abs($row['point_diff']) : '0');
                    @endphp
                    <tr class="{{ $row['qualified'] ? 'is-qualified' : '' }}" data-standings-row="{{ $row['rank'] }}">
                        <td class="hf-lp-num">{{ $row['rank'] }}</td>
                        <td>
                            <span class="hf-lp-standings__team">
                                <x-landing.crest :team="$team" />
                                <strong>{{ $team['name'] ?? 'Team' }}</strong>
                            </span>
                        </td>
                        <td class="hf-lp-num"><span data-count="{{ $row['wins'] }}">{{ $row['wins'] }}</span></td>
                        <td class="hf-lp-num"><span data-count="{{ $row['losses'] }}">{{ $row['losses'] }}</span></td>
                        <td class="hf-lp-num"><span data-count="{{ $row['points_for'] }}">{{ $row['points_for'] }}</span></td>
                        <td class="hf-lp-num"><span data-count="{{ $row['points_against'] }}">{{ $row['points_against'] }}</span></td>
                        <td class="hf-lp-num"><span data-count="{{ $row['point_diff'] }}" data-format="signed">{{ $diff }}</span></td>
                        <td class="hf-lp-num">{{ $row['seed'] ?? '–' }}</td>
                    </tr>

                    @if ($row['rank'] === 8)
                        <tr class="hf-lp-standings__cut">
                            <td colspan="8"><span>Top 8 advance to the quarterfinals</span></td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="hf-lp-standings__cards">
        @foreach ($standings as $row)
            @php
                $team = $teams->get($row['team_id']);
                $diff = $row['point_diff'] > 0
                    ? '+'.$row['point_diff']
                    : ($row['point_diff'] < 0 ? '−'.abs($row['point_diff']) : '0');
            @endphp
            <article class="hf-lp-standings__card {{ $row['qualified'] ? 'is-qualified' : '' }}">
                <div class="hf-lp-standings__card-head">
                    <span class="hf-lp-standings__rank">#{{ $row['rank'] }}</span>
                    <x-landing.crest :team="$team" />
                    <strong>{{ $team['name'] ?? 'Team' }}</strong>
                    @if ($row['qualified'])
                        <span class="hf-lp-standings__tick"><i class="bi bi-check2" aria-hidden="true"></i></span>
                    @endif
                </div>
                <div class="hf-lp-standings__card-stats">
                    <span><strong data-count="{{ $row['wins'] }}">{{ $row['wins'] }}</strong> W</span>
                    <span><strong data-count="{{ $row['losses'] }}">{{ $row['losses'] }}</strong> L</span>
                    <span><strong data-count="{{ $row['point_diff'] }}" data-format="signed">{{ $diff }}</strong> DIFF</span>
                </div>
            </article>

            @if ($row['rank'] === 8)
                <div class="hf-lp-standings__cut-card"><span>Top 8 advance to the quarterfinals</span></div>
            @endif
        @endforeach
    </div>
</div>
