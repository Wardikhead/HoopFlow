<?php

namespace App\Http\Controllers;

use App\Models\MatchModel;
use App\Models\Team;
use App\Models\Tournament;
use App\Services\Calendar\ScheduleCalendarService;
use Illuminate\Http\Response;

class ScheduleCalendarController extends Controller
{
    public function tournament(Tournament $tournament, ScheduleCalendarService $calendar): Response
    {
        return $this->calendarResponse(
            $calendar->tournament($tournament),
            'hoopflow-'.$tournament->id.'.ics',
        );
    }

    public function team(Tournament $tournament, Team $team, ScheduleCalendarService $calendar): Response
    {
        $participates = MatchModel::query()
            ->where('tournament_id', $tournament->id)
            ->where(fn ($query) => $query
                ->where('home_team_id', $team->id)
                ->orWhere('away_team_id', $team->id))
            ->exists();

        abort_unless($participates, 404);

        return $this->calendarResponse(
            $calendar->team($tournament, $team),
            'hoopflow-'.$tournament->id.'-'.$team->id.'.ics',
        );
    }

    private function calendarResponse(string $ics, string $filename): Response
    {
        return new Response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }
}
