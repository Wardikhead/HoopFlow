<?php

namespace App\Services;

use App\Models\Bracket;
use App\Models\MatchModel;
use App\Models\Team;
use App\Models\Tournament;

class TournamentLifecycleService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function activate(Tournament $tournament): bool
    {
        $tournament->refresh();

        if (! in_array($tournament->status, ['draft', 'upcoming', 'registration-open'], true)) {
            return false;
        }

        $before = $tournament->status;
        $tournament->update(['status' => 'active']);

        $this->auditLogger->log(
            'tournament.activate',
            'tournament',
            $tournament->id,
            ['status' => $before],
            ['status' => 'active'],
            $tournament->organization_id,
        );

        return true;
    }

    public function syncCompletion(Tournament $tournament, ?Team $champion = null): bool
    {
        $tournament->refresh();

        if ($tournament->status === 'completed') {
            return false;
        }

        if (! $champion instanceof Team && ! $this->roundRobinGroupStageFinished($tournament)) {
            return false;
        }

        return $this->complete($tournament, $champion);
    }

    protected function roundRobinGroupStageFinished(Tournament $tournament): bool
    {
        if ($tournament->format() !== 'round-robin') {
            return false;
        }

        $playoffTeams = $tournament->ruleValue('playoff_teams', 0);

        if (is_numeric($playoffTeams) && (int) $playoffTeams > 0) {
            return false;
        }

        $divisionIds = $tournament->divisions()->pluck('id');

        if ($divisionIds->isEmpty()) {
            return false;
        }

        if (Bracket::whereIn('division_id', $divisionIds)->where('phase', 'playoffs')->exists()) {
            return false;
        }

        $group = Bracket::whereIn('division_id', $divisionIds)->where('phase', 'group')->first();

        if (! $group instanceof Bracket) {
            return false;
        }

        $matches = MatchModel::where('bracket_id', $group->id)->get();

        return $matches->isNotEmpty()
            && $matches->every(fn (MatchModel $match): bool => in_array($match->status, ['final', 'forfeit'], true));
    }

    protected function complete(Tournament $tournament, ?Team $champion): bool
    {
        $before = $tournament->status;
        $tournament->update(['status' => 'completed']);

        $this->auditLogger->log(
            'tournament.complete',
            'tournament',
            $tournament->id,
            ['status' => $before],
            ['status' => 'completed', 'champion_team_id' => $champion?->id],
            $tournament->organization_id,
        );

        return true;
    }
}
