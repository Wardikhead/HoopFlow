<?php

/** Domain service for Tournament lifecycle service. */

namespace App\Services;

use App\Models\Bracket;
use App\Models\MatchModel;
use App\Models\Team;
use App\Models\Tournament;

class TournamentLifecycleService
{
    /**
     * Create a lifecycle service with audit logging.
     *
     * @param  AuditLogger  $auditLogger  The audit logger.
     */
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * Activate a tournament for registration.
     *
     * @param  Tournament  $tournament  The tournament.
     * @return bool The result.
     */
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

    /**
     * Archive a tournament and hide it from active views.
     *
     * @param  Tournament  $tournament  The tournament.
     * @return bool The result.
     */
    public function archive(Tournament $tournament): bool
    {
        $tournament->refresh();

        if ($tournament->isArchived()) {
            return false;
        }

        $tournament->update(['archived_at' => now()]);

        $this->auditLogger->log(
            'tournament.archive',
            'tournament',
            $tournament->id,
            ['archived_at' => null],
            ['archived_at' => $tournament->archived_at?->toIso8601String()],
            $tournament->organization_id,
        );

        return true;
    }

    /**
     * Restore an archived tournament to its previous state.
     *
     * @param  Tournament  $tournament  The tournament.
     * @return bool The result.
     */
    public function restore(Tournament $tournament): bool
    {
        $tournament->refresh();

        if (! $tournament->isArchived()) {
            return false;
        }

        $archivedAt = $tournament->archived_at?->toIso8601String();
        $tournament->update(['archived_at' => null]);

        $this->auditLogger->log(
            'tournament.restore',
            'tournament',
            $tournament->id,
            ['archived_at' => $archivedAt],
            ['archived_at' => null],
            $tournament->organization_id,
        );

        return true;
    }

    /**
     * Delete a tournament and its dependent records.
     *
     * @param  Tournament  $tournament  The tournament.
     * @return bool The result.
     */
    public function delete(Tournament $tournament): bool
    {
        $tournament->refresh();

        $this->auditLogger->log(
            'tournament.delete',
            'tournament',
            $tournament->id,
            ['name' => $tournament->name, 'status' => $tournament->status],
            null,
            $tournament->organization_id,
        );

        return (bool) $tournament->delete();
    }

    /**
     * Update tournament completion state from bracket results.
     *
     * @param  Tournament  $tournament  The tournament.
     * @param  ?Team  $champion  The champion.
     * @return bool The result.
     */
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

    /**
     * Check whether every round-robin group has finished.
     *
     * @param  Tournament  $tournament  The tournament.
     * @return bool The result.
     */
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

    /**
     * Mark a tournament complete and record its champion.
     *
     * @param  Tournament  $tournament  The tournament.
     * @param  ?Team  $champion  The champion.
     * @return bool The result.
     */
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
