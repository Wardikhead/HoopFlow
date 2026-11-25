<?php

/** Domain service for Live score service. */

namespace App\Services;

use App\Exceptions\ScoreConflictException;
use App\Models\MatchModel;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LiveScoreService
{
    public const MAX_SCORE = 999;

    /** Scores are only accepted while a match is scheduled or already running. */
    private const EDITABLE_STATUSES = ['scheduled', 'in_progress'];

    /**
     * Create a live-score service with audit logging.
     *
     * @param  AuditLogger  $auditLogger  The audit logger.
     */
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @return array{
     *   tournament: array{id: int, name: string, slug: string, status: string},
     *   server_time: string,
     *   matches: list<array<string, mixed>>
     * }
     */
    public function snapshot(Tournament $tournament): array
    {
        $matches = $tournament->matches()
            ->with([
                'homeTeam:id,name,short_name',
                'awayTeam:id,name,short_name',
                'bracket',
                'tournament',
            ])
            ->orderByRaw("case when status = 'in_progress' then 0 when status = 'scheduled' then 1 else 2 end")
            ->orderBy('round_number')
            ->orderBy('match_number')
            ->limit(60)
            ->get();

        return [
            'tournament' => [
                'id' => (int) $tournament->id,
                'name' => $tournament->name,
                'slug' => $tournament->slug,
                'status' => $tournament->status,
            ],
            'server_time' => now()->toIso8601String(),
            'matches' => $matches
                ->map(fn (MatchModel $match): array => $this->matchPayload($match))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function matchPayload(MatchModel $match): array
    {
        $match->loadMissing([
            'homeTeam:id,name,short_name',
            'awayTeam:id,name,short_name',
            'bracket',
            'tournament',
        ]);

        return [
            'id' => (int) $match->id,
            'status' => $match->status,
            'status_label' => $match->statusLabel(),
            'stage' => $match->stage,
            'round' => $match->roundLabel(),
            'round_number' => (int) $match->round_number,
            'match_number' => (int) $match->match_number,
            'home' => $this->teamPayload($match->homeTeam),
            'away' => $this->teamPayload($match->awayTeam),
            'home_score' => $match->home_score !== null ? (int) $match->home_score : null,
            'away_score' => $match->away_score !== null ? (int) $match->away_score : null,
            'version' => (int) ($match->version ?? 1),
            'score_updated_at' => $match->score_updated_at?->toIso8601String(),
            'updated_at' => $match->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Update the running score without finalising the result. Finalisation stays
     * in the stats workspace so bracket progression and box scores stay in sync.
     *
     * @param  array{home_score: int, away_score: int, version: int}  $data
     */
    public function updateScore(MatchModel $match, array $data, User $actor): MatchModel
    {
        return DB::transaction(function () use ($match, $data, $actor): MatchModel {
            $locked = MatchModel::query()
                ->whereKey($match->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($locked->status, self::EDITABLE_STATUSES, true)) {
                throw new InvalidArgumentException('This match is no longer accepting live score updates.');
            }

            $currentVersion = (int) ($locked->version ?? 1);
            $expectedVersion = (int) $data['version'];

            if ($expectedVersion !== $currentVersion) {
                throw new ScoreConflictException($locked);
            }

            $before = [
                'home_score' => $locked->home_score,
                'away_score' => $locked->away_score,
                'status' => $locked->status,
                'version' => $currentVersion,
            ];

            $locked->home_score = (int) $data['home_score'];
            $locked->away_score = (int) $data['away_score'];
            $locked->status = 'in_progress';
            $locked->version = $currentVersion + 1;
            $locked->score_updated_at = now();
            $locked->save();

            $this->auditLogger->log(
                'score.live_update',
                'match',
                $locked->id,
                $before,
                [
                    'home_score' => $locked->home_score,
                    'away_score' => $locked->away_score,
                    'status' => $locked->status,
                    'version' => $locked->version,
                ],
                $locked->tournament?->organization_id !== null
                    ? (int) $locked->tournament->organization_id
                    : null,
                (int) $actor->id,
            );

            return $locked->refresh();
        });
    }

    /**
     * @return array{id: int, name: string, short_name: string|null}|null
     */
    private function teamPayload(mixed $team): ?array
    {
        if (! $team instanceof Team) {
            return null;
        }

        return [
            'id' => (int) $team->id,
            'name' => $team->name,
            'short_name' => $team->short_name,
        ];
    }
}
