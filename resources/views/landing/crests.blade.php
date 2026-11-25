{{-- Blade view for Crests. --}}
@php
    $byTeam = collect($standings)->keyBy('team_id');
@endphp

<section id="teams" class="hf-lp-crestwall" aria-labelledby="crestwall-title">
    <div class="hf-lp-shell">
        <header class="hf-lp-crestwall__head hf-lp-wipe">
            <span class="hf-eyebrow hf-lp-eyebrow">Ten teams, one record</span>
            <h2 id="crestwall-title">Every crest carries <em>its own history.</em></h2>
            <p>Hover a crest to see where that team stands — wins, losses, and the seed it earned.</p>
        </header>

        <ul class="hf-lp-crestwall__grid">
            @foreach ($teams as $team)
                @php $row = $byTeam->get($team['id']); @endphp
                <li class="hf-lp-crestw hf-lp-tilt hf-lp-reveal" data-hf-card style="--i: {{ $loop->index }}; --card-accent: {{ $team['color'] }}">
                    <x-landing.crest :team="$team" size="md" />
                    <span class="hf-lp-crestw__name">{{ $team['name'] }}</span>
                    <span class="hf-lp-crestw__meta">
                        @if ($row && $row['qualified'])
                            Seed {{ $row['seed'] }} · {{ $row['wins'] }}–{{ $row['losses'] }}
                        @elseif ($row)
                            {{ $row['wins'] }}–{{ $row['losses'] }} · cut line
                        @else
                            Awaiting seeding
                        @endif
                    </span>
                </li>
            @endforeach
        </ul>
    </div>
</section>
