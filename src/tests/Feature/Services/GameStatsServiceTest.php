<?php

use App\Models\AuditLog;
use App\Models\MatchModel;
use App\Models\PlayerStat;
use App\Models\PlayerStatQuarter;
use App\Services\GameStatsService;
use Database\Seeders\PrototypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PrototypeSeeder::class);
});

function gssMatch(int $round, int $number): MatchModel
{
    return MatchModel::where('round_number', $round)->where('match_number', $number)->firstOrFail();
}

function gssPlayerId(MatchModel $match, string $side, int $index = 0): int
{
    $team = $side === 'team1' ? $match->homeTeam : $match->awayTeam;

    return (int) $team->players()->orderBy('jersey_number')->pluck('id')->get($index);
}

function gssPayload(MatchModel $match, array $overrides = []): array
{
    $p1 = gssPlayerId($match, 'team1');
    $p2 = gssPlayerId($match, 'team2');

    return array_replace([
        'quarters' => [
            'team1' => [10, 10, 10, 10],
            'team2' => [8, 8, 8, 8],
        ],
        'finals' => ['team1' => 40, 'team2' => 32],
        'stats' => [
            'team1' => [
                $p1 => ['quarters' => [
                    ['fgm' => 2, 'fga' => 4, 'tpm' => 1, 'tpa' => 2, 'ftm' => 1, 'fta' => 2],
                ]],
            ],
            'team2' => [
                $p2 => ['quarters' => [
                    ['fgm' => 1, 'fga' => 3, 'tpm' => 0, 'tpa' => 1, 'ftm' => 0, 'fta' => 0],
                ]],
            ],
        ],
    ], $overrides);
}

it('loads a scheduled match with a full roster, padded quarters, and zero-filled stats', function () {
    $match = gssMatch(8, 3);
    $loaded = app(GameStatsService::class)->load($match);

    expect($loaded['quarters']['team1'])->toHaveCount(4)
        ->and($loaded['quarters']['team1'])->toBe([0, 0, 0, 0])
        ->and($loaded['quarters']['team2'])->toBe([0, 0, 0, 0])
        ->and($loaded['stats']['team1'])->toHaveCount($match->homeTeam->players()->count())
        ->and($loaded['stats']['team2'])->toHaveCount($match->awayTeam->players()->count());

    $firstPlayer = array_key_first($loaded['stats']['team1']);
    expect($loaded['stats']['team1'][$firstPlayer]['quarters'])->toHaveCount(4)
        ->and($loaded['stats']['team1'][$firstPlayer]['quarters'][0]['fgm'])->toBe(0);
});

it('saves a draft, derives points and totals, and leaves the match scheduled', function () {
    $match = gssMatch(8, 3);
    $payload = gssPayload($match);

    app(GameStatsService::class)->saveDraft($match, $payload);

    $match->refresh();
    expect($match->status)->toBe('scheduled')
        ->and($match->quarters_json)->toBe(['team1' => [10, 10, 10, 10], 'team2' => [8, 8, 8, 8]]);

    $stat = PlayerStat::where('match_id', $match->id)
        ->where('player_id', gssPlayerId($match, 'team1'))
        ->firstOrFail();

    expect((int) $stat->points)->toBe(6)
        ->and((int) $stat->field_goals_made)->toBe(2)
        ->and((int) $stat->three_points_made)->toBe(1);

    expect(PlayerStatQuarter::where('match_id', $match->id)->count())
        ->toBe(($match->homeTeam->players()->count() + $match->awayTeam->players()->count()) * 4);

    expect(AuditLog::where('action', 'stats.save')->where('entity_id', $match->id)->exists())->toBeTrue();
});

it('sums quarter rows into player totals', function () {
    $match = gssMatch(8, 3);
    $playerId = gssPlayerId($match, 'team1');

    $payload = gssPayload($match, [
        'stats' => [
            'team1' => [
                $playerId => ['quarters' => [
                    ['fgm' => 1, 'fga' => 2, 'tpm' => 0, 'tpa' => 0, 'ftm' => 1, 'fta' => 1, 'reb' => 2, 'ast' => 1],
                    ['fgm' => 2, 'fga' => 3, 'tpm' => 1, 'tpa' => 1, 'ftm' => 0, 'fta' => 0, 'reb' => 3, 'ast' => 2, 'stl' => 1],
                ]],
            ],
        ],
    ]);

    app(GameStatsService::class)->saveDraft($match, $payload);

    $stat = PlayerStat::where('match_id', $match->id)->where('player_id', $playerId)->firstOrFail();

    expect((int) $stat->points)->toBe(8)
        ->and((int) $stat->field_goals_made)->toBe(3)
        ->and((int) $stat->field_goals_attempted)->toBe(5)
        ->and((int) $stat->rebounds)->toBe(5)
        ->and((int) $stat->assists)->toBe(3)
        ->and((int) $stat->steals)->toBe(1);
});

it('rejects unknown players', function () {
    $match = gssMatch(8, 3);
    $payload = gssPayload($match, ['stats' => ['team1' => [999999 => ['quarters' => []]]]]);

    app(GameStatsService::class)->saveDraft($match, $payload);
})->throws(InvalidArgumentException::class, 'is not on the roster');

it('rejects made shots greater than attempts', function () {
    $match = gssMatch(8, 3);
    $playerId = gssPlayerId($match, 'team1');
    $payload = gssPayload($match, [
        'stats' => ['team1' => [$playerId => ['quarters' => [['fgm' => 5, 'fga' => 2]]]]],
    ]);

    app(GameStatsService::class)->saveDraft($match, $payload);
})->throws(InvalidArgumentException::class, 'cannot exceed');

it('pads missing quarters and caps the number of quarters at nine', function () {
    $match = gssMatch(8, 3);
    $service = app(GameStatsService::class);

    $service->saveDraft($match, gssPayload($match, [
        'quarters' => ['team1' => [1, 2], 'team2' => [3]],
    ]));

    expect($match->refresh()->quarters_json['team1'])->toBe([1, 2, 0, 0]);

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('cannot have more than 9 quarters');

    $service->saveDraft($match, gssPayload($match, [
        'quarters' => ['team1' => array_fill(0, 10, 1), 'team2' => array_fill(0, 10, 1)],
    ]));
});

it('publishes a result, advances the winner, and writes audits', function () {
    $match = gssMatch(8, 3);
    $away = $match->awayTeam;
    $next = MatchModel::where('round_number', 4)->where('match_number', 2)->firstOrFail();

    app(GameStatsService::class)->publish($match, gssPayload($match));

    $match->refresh();
    $next->refresh();

    expect($match->status)->toBe('final')
        ->and((int) $match->home_score)->toBe(40)
        ->and((int) $match->away_score)->toBe(32)
        ->and((int) $match->winner_team_id)->toBe((int) $match->home_team_id)
        ->and($next->home_team_id)->toBe((int) $match->home_team_id);

    expect(AuditLog::where('action', 'score.save')->where('entity_id', $match->id)->exists())->toBeTrue()
        ->and(AuditLog::where('action', 'stats.publish')->where('entity_id', $match->id)->exists())->toBeTrue();

    expect($away->id)->not->toBe($match->winner_team_id);
});

it('rejects publish when finals do not equal the quarter sums', function () {
    $match = gssMatch(8, 3);

    app(GameStatsService::class)->publish($match, gssPayload($match, [
        'finals' => ['team1' => 41, 'team2' => 32],
    ]));
})->throws(InvalidArgumentException::class, 'Final score must equal the sum of the quarters.');

it('rejects publish on a tie', function () {
    $match = gssMatch(8, 3);
    $payload = gssPayload($match, [
        'quarters' => ['team1' => [10, 10, 10, 10], 'team2' => [10, 10, 10, 10]],
        'finals' => ['team1' => 40, 'team2' => 40],
    ]);

    app(GameStatsService::class)->publish($match, $payload);
})->throws(InvalidArgumentException::class, 'Elimination games cannot end in a tie.');

it('rejects mutations on a final match', function () {
    $match = gssMatch(8, 1);

    app(GameStatsService::class)->saveDraft($match, gssPayload($match));
})->throws(InvalidArgumentException::class, 'This result is already final. Use correction to change it.');
