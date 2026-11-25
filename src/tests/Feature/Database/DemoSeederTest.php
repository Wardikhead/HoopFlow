<?php

/** Tests for Demo seeder test. */

use App\Models\Announcement;
use App\Models\MatchModel;
use App\Models\MatchSlot;
use App\Models\Payment;
use App\Models\Player;
use App\Models\Registration;
use App\Models\Team;
use App\Models\Tournament;
use App\Services\BracketService;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds the full demonstration dataset', function () {
    $this->seed(DemoSeeder::class);

    expect(Tournament::count())->toBe(5)
        ->and(Team::count())->toBe(20)
        ->and(Player::count())->toBe(200)
        ->and(Registration::count())->toBe(69)
        ->and(Payment::count())->toBe(69)
        ->and(Announcement::count())->toBe(9)
        ->and(Announcement::where('status', 'published')->count())->toBe(8)
        ->and(Payment::where('status', 'verified')->count())->toBe(61)
        ->and(Payment::where('status', 'partial')->count())->toBe(2)
        ->and(Payment::where('status', 'pending')->count())->toBe(6);

    $founders = Tournament::query()->where('slug', 'founders-cup-2026')->firstOrFail();

    expect($founders->status)->toBe('completed')
        ->and($founders->matches()->count())->toBe(15)
        ->and($founders->matches()->where('status', 'final')->count())->toBe(15)
        ->and(app(BracketService::class)->champion($founders)?->name)->toBe('Kapitolyo Kings');

    $barangay = Tournament::query()->where('slug', 'barangay-hoops-2026')->firstOrFail();
    $series = MatchModel::query()
        ->where('tournament_id', $barangay->id)
        ->whereNull('series_id')
        ->orderBy('round_number', 'desc')
        ->orderBy('match_number')
        ->get();

    $tiedSemifinal = MatchModel::query()
        ->where('tournament_id', $barangay->id)
        ->whereNull('series_id')
        ->where('round_number', 4)
        ->where('status', 'in_progress')
        ->firstOrFail();

    expect($barangay->status)->toBe('active')
        ->and($barangay->ruleValue('best_of_quarterfinal'))->toBe(3)
        ->and($barangay->ruleValue('best_of_semifinal'))->toBe(3)
        ->and($barangay->ruleValue('best_of_final'))->toBe(3)
        ->and($series)->toHaveCount(15)
        ->and($tiedSemifinal->series_home_wins)->toBe(1)
        ->and($tiedSemifinal->series_away_wins)->toBe(1)
        ->and($tiedSemifinal->series()->where('game_number', 3)->where('status', 'scheduled')->exists())->toBeTrue();

    $league = Tournament::query()->where('slug', 'community-league-2026')->firstOrFail();

    expect($league->status)->toBe('completed')
        ->and($league->matches()->count())->toBe(37)
        ->and($league->matches()->where('status', 'final')->count())->toBe(34);

    $doubleElimination = Tournament::query()->where('slug', 'double-elimination-cup-2026')->firstOrFail();

    expect($doubleElimination->status)->toBe('completed')
        ->and($doubleElimination->matches()->count())->toBe(31)
        ->and($doubleElimination->matches()->where('status', 'final')->count())->toBe(30)
        ->and(app(BracketService::class)->champion($doubleElimination)?->name)->toBe('Pasig Pirates');

    $summer = Tournament::query()->where('slug', 'summer-league-2027')->firstOrFail();

    expect($summer->status)->toBe('registration-open')
        ->and($summer->registrations()->where('status', 'approved')->count())->toBe(9)
        ->and($summer->registrations()->where('status', 'submitted')->count())->toBe(3)
        ->and($summer->registrations()->where('status', 'rejected')->count())->toBe(1);

    expect(MatchSlot::query()->where('status', 'published')->count())->toBeGreaterThan(0)
        ->and(Announcement::query()->where('pinned', true)->count())->toBe(2)
        ->and(Announcement::query()->where('status', 'draft')->count())->toBe(1);
});
