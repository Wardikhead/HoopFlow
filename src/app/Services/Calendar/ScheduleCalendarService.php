<?php

/** Domain service for Schedule calendar service. */

namespace App\Services\Calendar;

use App\Models\MatchModel;
use App\Models\MatchSlot;
use App\Models\Team;
use App\Models\Tournament;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class ScheduleCalendarService
{
    /**
     * Create a calendar service backed by the ICS writer.
     *
     * @param  IcsCalendarWriter  $writer  The writer.
     */
    public function __construct(private readonly IcsCalendarWriter $writer) {}

    /**
     * Build an ICS feed for every scheduled tournament match.
     *
     * @param  Tournament  $tournament  The tournament.
     * @return string The result.
     */
    public function tournament(Tournament $tournament): string
    {
        return $this->feed($tournament, null);
    }

    /**
     * Build an ICS feed for one team's scheduled matches.
     *
     * @param  Tournament  $tournament  The tournament.
     * @param  Team  $team  The team.
     * @return string The result.
     */
    public function team(Tournament $tournament, Team $team): string
    {
        return $this->feed($tournament, $team);
    }

    /**
     * Render an ICS calendar for the filtered schedule.
     *
     * @param  Tournament  $tournament  The tournament.
     * @param  ?Team  $team  The team.
     * @return string The result.
     */
    private function feed(Tournament $tournament, ?Team $team): string
    {
        $query = MatchSlot::query()
            ->where('tournament_id', $tournament->id)
            ->whereNotNull('published_at')
            ->with(['match.bracket', 'match.homeTeam', 'match.awayTeam', 'court.venue'])
            ->orderBy('start_at');

        if ($team instanceof Team) {
            $query->whereHas('match', fn ($match) => $match->where(
                fn ($matchQuery) => $matchQuery
                    ->where('home_team_id', $team->id)
                    ->orWhere('away_team_id', $team->id),
            ));
        }

        $slots = $query->get();

        /** @var list<array{
         *     uid: string,
         *     start: CarbonInterface,
         *     end: CarbonInterface,
         *     summary: string,
         *     location: string,
         *     description: string,
         *     url: string,
         *     status: 'CONFIRMED'|'CANCELLED',
         *     sequence: int,
         *     modifiedAt: CarbonInterface
         * }> $events
         */
        $events = [];

        foreach ($slots as $slot) {
            $event = $this->event($slot, $tournament);

            if ($event !== null) {
                $events[] = $event;
            }
        }

        $name = $team instanceof Team
            ? $team->name.' — '.$tournament->name
            : $tournament->name;

        $description = $team instanceof Team
            ? 'Published games for '.$team->name.' in '.$tournament->name.'.'
            : 'Published game schedule for '.$tournament->name.'.';

        return $this->writer->write($name, $description, $events, CarbonImmutable::now());
    }

    /**
     * @return array{
     *     uid: string,
     *     start: CarbonInterface,
     *     end: CarbonInterface,
     *     summary: string,
     *     location: string,
     *     description: string,
     *     url: string,
     *     status: 'CONFIRMED'|'CANCELLED',
     *     sequence: int,
     *     modifiedAt: CarbonInterface
     * }|null
     */
    private function event(MatchSlot $slot, Tournament $tournament): ?array
    {
        $start = $slot->start_at;
        $end = $slot->end_at;

        if (! $start instanceof CarbonInterface || ! $end instanceof CarbonInterface) {
            return null;
        }

        $match = $slot->match;
        $homeName = $match?->homeTeam?->name;
        $awayName = $match?->awayTeam?->name;
        $home = $homeName ?? 'TBD';
        $away = $awayName ?? 'TBD';
        $round = $match?->roundLabel() ?? 'Match';
        $court = $slot->court?->name;
        $venue = $slot->court?->venue;
        $location = collect([$court, $venue?->name, $venue?->address])
            ->filter()
            ->implode(', ');
        $scheduleUrl = route('schedule', [
            'id' => $tournament->id,
            'date' => $start->format('Y-m-d'),
        ]);

        $description = collect([
            $tournament->name,
            $round,
            $match instanceof MatchModel && ($match->best_of ?? 0) > 1 ? $match->seriesGameLabel() : null,
            $scheduleUrl,
        ])->filter()->implode("\n");

        $modifiedAt = $slot->updated_at ?? $slot->created_at;

        if (! $modifiedAt instanceof CarbonInterface) {
            $modifiedAt = CarbonImmutable::now();
        }

        return [
            'uid' => 'match-'.$slot->match_id.'@hoopflow',
            'start' => $start,
            'end' => $end,
            'summary' => $home.' vs '.$away.' — '.$round,
            'location' => $location !== '' ? $location : $tournament->name,
            'description' => $description,
            'url' => $scheduleUrl,
            'status' => $match?->status === 'cancelled' ? 'CANCELLED' : 'CONFIRMED',
            'sequence' => $modifiedAt->getTimestamp(),
            'modifiedAt' => $modifiedAt,
        ];
    }
}
