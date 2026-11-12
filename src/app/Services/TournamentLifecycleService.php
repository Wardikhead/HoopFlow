<?php

namespace App\Services;

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

        if (! $champion instanceof Team) {
            return false;
        }

        return $this->complete($tournament, $champion);
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
