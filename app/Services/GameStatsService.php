<?php

/** Domain service for Game stats service. */

namespace App\Services;

use App\Models\MatchModel;
use App\Models\Player;
use App\Models\PlayerStat;
use App\Models\PlayerStatQuarter;
use App\Models\Team;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GameStatsService
{
    public const MIN_QUARTERS = 4;

    public const MAX_QUARTERS = 9;

    public const MAX_VALUE = 199;

    /** @var array<string, string> */
    public const FIELDS = [
        'fgm' => 'field_goals_made',
        'fga' => 'field_goals_attempted',
        'tpm' => 'three_points_made',
        'tpa' => 'three_points_attempted',
        'ftm' => 'free_throws_made',
        'fta' => 'free_throws_attempted',
        'reb' => 'rebounds',
        'ast' => 'assists',
        'stl' => 'steals',
        'blk' => 'blocks',
        'to' => 'turnovers',
        'pf' => 'fouls',
    ];

    /**
     * Create a stats service with its dependencies.
     *
     * @param  AuditLogger  $auditLogger  The audit logger.
     * @param  BracketService  $bracketService  The bracket service.
     */
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly BracketService $bracketService,
    ) {}

    /**
     * @return array{
     *   quarters: array{team1: list<int>, team2: list<int>},
     *   finals: array{team1: int, team2: int},
     *   stats: array{
     *     team1: array<int, array{quarters: list<array<string, int>>}>,
     *     team2: array<int, array{quarters: list<array<string, int>>}>
     *   }
     * }
     */
    public function load(MatchModel $match): array
    {
        $raw = is_array($match->quarters_json) ? $match->quarters_json : [];
        $team1 = $this->cleanQuarterList($raw['team1'] ?? []);
        $team2 = $this->cleanQuarterList($raw['team2'] ?? []);
        $maxQuarterIndex = (int) $match->playerQuarterStats->max('quarter_index');
        $count = min(
            self::MAX_QUARTERS,
            max(self::MIN_QUARTERS, count($team1), count($team2), $maxQuarterIndex + 1),
        );

        $team1 = $this->pad($team1, $count);
        $team2 = $this->pad($team2, $count);

        $statsRows = $match->playerStats;
        $quarterRows = $match->playerQuarterStats->groupBy('player_id');
        $stats = ['team1' => [], 'team2' => []];

        foreach (['team1' => $match->homeTeam, 'team2' => $match->awayTeam] as $side => $team) {
            if (! $team instanceof Team) {
                continue;
            }

            foreach ($this->roster($team) as $player) {
                $quarters = [];
                $rows = $quarterRows->get($player->id, collect())->keyBy('quarter_index');

                for ($index = 0; $index < $count; $index++) {
                    $row = $rows->get($index);
                    $quarters[] = $row instanceof PlayerStatQuarter
                        ? $this->shortFields($row->getAttributes())
                        : $this->emptyQuarter();
                }

                $stats[$side][(int) $player->id] = ['quarters' => $quarters];
            }
        }

        return [
            'quarters' => ['team1' => $team1, 'team2' => $team2],
            'finals' => [
                'team1' => $match->home_score !== null ? (int) $match->home_score : array_sum($team1),
                'team2' => $match->away_score !== null ? (int) $match->away_score : array_sum($team2),
            ],
            'stats' => $stats,
        ];
    }

    /**
     * @param  array{quarters?: array{team1?: array<int|string, mixed>, team2?: array<int|string, mixed>}, stats?: array<string, mixed>}  $payload
     */
    public function saveDraft(MatchModel $match, array $payload): void
    {
        $this->guardEditable($match);
        $normalized = $this->normalize($match, $payload);

        DB::transaction(function () use ($match, $normalized): void {
            $this->write($match, $normalized);
        });
    }

    /**
     * @param  array{quarters?: array{team1?: array<int|string, mixed>, team2?: array<int|string, mixed>}, finals?: array{team1?: mixed, team2?: mixed}, stats?: array<string, mixed>}  $payload
     */
    public function publish(MatchModel $match, array $payload): MatchModel
    {
        $this->guardEditable($match);
        $normalized = $this->normalize($match, $payload);

        $final1 = $this->intValue($payload['finals']['team1'] ?? null, 'Final score must be a whole number.');
        $final2 = $this->intValue($payload['finals']['team2'] ?? null, 'Final score must be a whole number.');

        if ($final1 !== array_sum($normalized['quarters']['team1']) || $final2 !== array_sum($normalized['quarters']['team2'])) {
            throw new \InvalidArgumentException('Final score must equal the sum of the quarters.');
        }

        if ($final1 === $final2) {
            throw new \InvalidArgumentException('Elimination games cannot end in a tie.');
        }

        $previous = is_array($match->quarters_json) ? $match->quarters_json : null;

        DB::transaction(function () use ($match, $normalized, $final1, $final2, $previous): void {
            $this->write($match, $normalized, false);
            $this->bracketService->recordScore($match->refresh(), $final1, $final2);
            $this->writeAudit($match, 'stats.publish', $previous, [
                'quarters' => $normalized['quarters'],
                'finals' => ['team1' => $final1, 'team2' => $final2],
            ]);
        });

        return $match->refresh();
    }

    /**
     * @param  array{quarters?: array{team1?: array<int|string, mixed>, team2?: array<int|string, mixed>}, finals?: array{team1?: mixed, team2?: mixed}, stats?: array<string, mixed>}  $payload
     */
    public function correct(MatchModel $match, array $payload, string $reason): MatchModel
    {
        if ($match->status !== 'final') {
            throw new \InvalidArgumentException('Only a final result can be corrected.');
        }

        if ($match->home_team_id === null || $match->away_team_id === null) {
            throw new \InvalidArgumentException('Both teams must be known before correcting stats.');
        }

        $reason = trim($reason);
        $length = mb_strlen($reason);

        if ($length < 5) {
            throw new \InvalidArgumentException('Enter at least 5 characters for the correction reason.');
        }

        if ($length > 500) {
            throw new \InvalidArgumentException('The correction reason cannot exceed 500 characters.');
        }

        $normalized = $this->normalize($match, $payload);

        $final1 = $this->intValue($payload['finals']['team1'] ?? null, 'Final score must be a whole number.');
        $final2 = $this->intValue($payload['finals']['team2'] ?? null, 'Final score must be a whole number.');

        if ($final1 !== array_sum($normalized['quarters']['team1']) || $final2 !== array_sum($normalized['quarters']['team2'])) {
            throw new \InvalidArgumentException('Final score must equal the sum of the quarters.');
        }

        if ($final1 === $final2) {
            throw new \InvalidArgumentException('Elimination games cannot end in a tie.');
        }

        $previous = is_array($match->quarters_json) ? $match->quarters_json : null;

        DB::transaction(function () use ($match, $normalized, $final1, $final2, $previous, $reason): void {
            $this->write($match, $normalized, false);
            $this->bracketService->correctScore($match->refresh(), $final1, $final2, $reason);
            $this->writeAudit($match, 'stats.correct', $previous, [
                'quarters' => $normalized['quarters'],
                'finals' => ['team1' => $final1, 'team2' => $final2],
                'players' => count($normalized['stats']['team1']) + count($normalized['stats']['team2']),
                'reason' => $reason,
            ]);
        });

        return $match->refresh();
    }

    /**
     * @param  array<string, int>  $fields
     */
    public function pointsFor(array $fields): int
    {
        return ((int) ($fields['fgm'] ?? $fields['field_goals_made'] ?? 0)) * 2
            + (int) ($fields['tpm'] ?? $fields['three_points_made'] ?? 0)
            + (int) ($fields['ftm'] ?? $fields['free_throws_made'] ?? 0);
    }

    /**
     * @return Collection<int, Player>
     */
    public function roster(Team $team): Collection
    {
        return $team->players()
            ->orderBy('jersey_number')
            ->orderBy('first_name')
            ->get();
    }

    /**
     * Ensure a match's stats are still editable.
     *
     * @param  MatchModel  $match  The match.
     * @return void Nothing.
     */
    protected function guardEditable(MatchModel $match): void
    {
        if (in_array($match->status, ['final', 'forfeit'], true)) {
            throw new \InvalidArgumentException('This result is already final. Use correction to change it.');
        }

        if ($match->home_team_id === null || $match->away_team_id === null) {
            throw new \InvalidArgumentException('Both teams must be known before entering stats.');
        }
    }

    /**
     * @param  array{quarters?: array{team1?: array<int|string, mixed>, team2?: array<int|string, mixed>}, stats?: array<string, mixed>}  $payload
     * @return array{
     *   quarters: array{team1: list<int>, team2: list<int>},
     *   stats: array{team1: array<int, array{quarters: list<array<string, int>>}>, team2: array<int, array{quarters: list<array<string, int>>}>}
     * }
     */
    protected function normalize(MatchModel $match, array $payload): array
    {
        $home = $match->homeTeam;
        $away = $match->awayTeam;

        if (! $home instanceof Team || ! $away instanceof Team) {
            throw new \InvalidArgumentException('Both teams must be known before entering stats.');
        }

        $team1 = $this->quarterInput($payload['quarters']['team1'] ?? []);
        $team2 = $this->quarterInput($payload['quarters']['team2'] ?? []);

        if (count($team1) > self::MAX_QUARTERS || count($team2) > self::MAX_QUARTERS) {
            throw new \InvalidArgumentException('A game cannot have more than 9 quarters.');
        }

        $count = max(self::MIN_QUARTERS, count($team1), count($team2));
        $team1 = $this->pad($team1, $count);
        $team2 = $this->pad($team2, $count);

        $rosters = [
            'team1' => $this->roster($home)->keyBy('id'),
            'team2' => $this->roster($away)->keyBy('id'),
        ];

        $inputStats = is_array($payload['stats'] ?? null) ? $payload['stats'] : [];
        $stats = ['team1' => [], 'team2' => []];

        foreach (['team1', 'team2'] as $side) {
            $provided = is_array($inputStats[$side] ?? null) ? $inputStats[$side] : [];

            foreach (array_keys($provided) as $playerId) {
                if (! $rosters[$side]->has((int) $playerId)) {
                    throw new \InvalidArgumentException('Player '.$playerId.' is not on the roster for this game.');
                }
            }

            foreach ($rosters[$side] as $playerId => $player) {
                $entry = is_array($provided[$playerId] ?? null) ? $provided[$playerId] : [];
                $quarters = is_array($entry['quarters'] ?? null) ? array_values($entry['quarters']) : [];

                if (count($quarters) > $count) {
                    throw new \InvalidArgumentException('A player cannot have more quarters than the game.');
                }

                $normalizedQuarters = [];

                for ($index = 0; $index < $count; $index++) {
                    $normalizedQuarters[] = $this->normalizeQuarter(
                        is_array($quarters[$index] ?? null) ? $quarters[$index] : []
                    );
                }

                $stats[$side][(int) $playerId] = ['quarters' => $normalizedQuarters];
            }
        }

        return ['quarters' => ['team1' => $team1, 'team2' => $team2], 'stats' => $stats];
    }

    /**
     * @param  array{
     *   quarters: array{team1: list<int>, team2: list<int>},
     *   stats: array{team1: array<int, array{quarters: list<array<string, int>>}>, team2: array<int, array{quarters: list<array<string, int>>}>}
     * }  $normalized
     */
    protected function write(MatchModel $match, array $normalized, bool $audit = true): void
    {
        $previous = is_array($match->quarters_json) ? $match->quarters_json : null;
        $match->quarters_json = $normalized['quarters'];
        $match->save();

        $teamIds = [
            'team1' => (int) $match->home_team_id,
            'team2' => (int) $match->away_team_id,
        ];

        $rosterIds = [];

        foreach ($normalized['stats'] as $side => $players) {
            foreach ($players as $playerId => $entry) {
                $rosterIds[] = (int) $playerId;
                $totals = $this->sumQuarters($entry['quarters']);
                $values = $totals;
                $values['points'] = $this->pointsFor($totals);
                $values['team_id'] = $teamIds[$side];

                if (DB::getDriverName() === 'pgsql') {
                    $values['tournament_id'] = (int) $match->tournament_id;
                }

                PlayerStat::updateOrCreate(
                    ['match_id' => $match->id, 'player_id' => $playerId],
                    $values,
                );

                foreach ($entry['quarters'] as $index => $quarter) {
                    $row = [];

                    foreach (self::FIELDS as $short => $column) {
                        $row[$column] = $quarter[$short] ?? 0;
                    }

                    $row['points'] = $this->pointsFor($quarter);
                    $row['team_id'] = $teamIds[$side];

                    PlayerStatQuarter::updateOrCreate(
                        ['match_id' => $match->id, 'player_id' => $playerId, 'quarter_index' => $index],
                        $row,
                    );
                }
            }
        }

        PlayerStatQuarter::where('match_id', $match->id)
            ->whereNotIn('player_id', $rosterIds)
            ->delete();

        PlayerStatQuarter::where('match_id', $match->id)
            ->where('quarter_index', '>=', count($normalized['quarters']['team1']))
            ->delete();

        if ($audit) {
            $this->writeAudit($match, 'stats.save', $previous, [
                'quarters' => $normalized['quarters'],
                'players' => count($rosterIds),
            ]);
        }
    }

    /**
     * @param  list<array<string, int>>  $quarters
     * @return array<string, int>
     */
    protected function sumQuarters(array $quarters): array
    {
        $totals = array_fill_keys(array_values(self::FIELDS), 0);

        foreach ($quarters as $quarter) {
            foreach (self::FIELDS as $short => $column) {
                $totals[$column] += (int) ($quarter[$short] ?? 0);
            }
        }

        return $totals;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, int>
     */
    protected function normalizeQuarter(array $values): array
    {
        $out = [];

        foreach (self::FIELDS as $short => $column) {
            $value = $values[$short] ?? 0;
            $out[$short] = $this->intValue($value, 'Player stats must be whole numbers.');
        }

        foreach ([['fgm', 'fga'], ['tpm', 'tpa'], ['ftm', 'fta'], ['tpm', 'fgm'], ['tpa', 'fga']] as [$made, $attempted]) {
            if ($out[$made] > $out[$attempted]) {
                throw new \InvalidArgumentException('Made shots cannot exceed attempts.');
            }
        }

        return $out;
    }

    /**
     * @param  array<int|string, mixed>  $values
     * @return list<int>
     */
    protected function quarterInput(array $values): array
    {
        return array_map(
            fn (mixed $value): int => $this->intValue($value, 'Quarter scores must be whole numbers.'),
            array_values($values),
        );
    }

    /**
     * @param  array<int|string, mixed>  $values
     * @return list<int>
     */
    protected function cleanQuarterList(array $values): array
    {
        return array_map(
            fn (mixed $value): int => is_numeric($value) ? max(0, min(self::MAX_VALUE, (int) $value)) : 0,
            array_values($values),
        );
    }

    /**
     * @param  list<int>  $values
     * @return list<int>
     */
    protected function pad(array $values, int $count): array
    {
        while (count($values) < $count) {
            $values[] = 0;
        }

        return array_slice($values, 0, $count);
    }

    /**
     * Convert a request value to a positive integer.
     *
     * @param  mixed  $value  The value.
     * @param  string  $message  The message.
     * @return int The result.
     */
    protected function intValue(mixed $value, string $message): int
    {
        if (! is_numeric($value) || (float) $value !== (float) (int) $value) {
            throw new \InvalidArgumentException($message);
        }

        $int = (int) $value;

        if ($int < 0 || $int > self::MAX_VALUE) {
            throw new \InvalidArgumentException($message);
        }

        return $int;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, int>
     */
    protected function shortFields(array $attributes): array
    {
        $out = [];

        foreach (self::FIELDS as $short => $column) {
            $out[$short] = (int) ($attributes[$column] ?? 0);
        }

        return $out;
    }

    /**
     * @return array<string, int>
     */
    protected function emptyQuarter(): array
    {
        return array_fill_keys(array_keys(self::FIELDS), 0);
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>  $after
     */
    protected function writeAudit(MatchModel $match, string $action, ?array $before, array $after): void
    {
        $this->auditLogger->log(
            $action,
            'match',
            $match->id,
            $before,
            $after,
            $match->tournament?->organization_id !== null ? (int) $match->tournament->organization_id : null,
            auth()->id(),
        );
    }
}
