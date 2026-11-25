<?php

/** Tests for Authorization test. */

use App\Models\Announcement;
use App\Models\MatchModel;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;
use Database\Seeders\PrototypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PrototypeSeeder::class);
});

/**
 * Auth users.
 *
 * @return array<mixed> The result.
 */
function authUsers(): array
{
    return [
        'organizer' => User::where('email', 'organizer@hoopflow.ph')->firstOrFail(),
        'coach' => User::where('email', 'coach.marcus@hoopflow.ph')->firstOrFail(),
        'fan' => User::where('email', 'fan.juan@hoopflow.ph')->firstOrFail(),
    ];
}

it('fan can view but not manage core models', function () {
    ['fan' => $fan] = authUsers();

    $team = Team::whereNull('coach_user_id')->firstOrFail();
    $tournament = Tournament::firstOrFail();
    $registration = Registration::firstOrFail();
    $payment = Payment::firstOrFail();
    $match = MatchModel::firstOrFail();
    $announcement = Announcement::firstOrFail();

    $cases = [
        [Team::class, $team],
        [Tournament::class, $tournament],
        [Registration::class, $registration],
        [Payment::class, $payment],
        [MatchModel::class, $match],
        [Announcement::class, $announcement],
    ];

    foreach ($cases as [$class, $model]) {
        expect(Gate::forUser($fan)->allows('viewAny', $class))->toBeTrue();
        expect(Gate::forUser($fan)->allows('view', $model))->toBeTrue();
        expect(Gate::forUser($fan)->allows('create', $class))->toBeFalse();
        expect(Gate::forUser($fan)->allows('update', $model))->toBeFalse();
        expect(Gate::forUser($fan)->allows('delete', $model))->toBeFalse();
    }
});

it('coach can view but not manage non-owned records', function () {
    ['coach' => $coach] = authUsers();

    $team = Team::whereNull('coach_user_id')->firstOrFail();
    $tournament = Tournament::firstOrFail();
    $registration = Registration::firstOrFail();
    $payment = Payment::firstOrFail();
    $match = MatchModel::firstOrFail();
    $announcement = Announcement::firstOrFail();

    $cases = [
        [Team::class, $team],
        [Tournament::class, $tournament],
        [Registration::class, $registration],
        [Payment::class, $payment],
        [MatchModel::class, $match],
        [Announcement::class, $announcement],
    ];

    foreach ($cases as [$class, $model]) {
        expect(Gate::forUser($coach)->allows('viewAny', $class))->toBeTrue();
        expect(Gate::forUser($coach)->allows('view', $model))->toBeTrue();
        expect(Gate::forUser($coach)->allows('create', $class))->toBeFalse();
        expect(Gate::forUser($coach)->allows('update', $model))->toBeFalse();
        expect(Gate::forUser($coach)->allows('delete', $model))->toBeFalse();
    }
});

it('organizer can do everything on core models', function () {
    ['organizer' => $organizer] = authUsers();

    $team = Team::whereNull('coach_user_id')->firstOrFail();
    $tournament = Tournament::firstOrFail();
    $registration = Registration::firstOrFail();
    $payment = Payment::firstOrFail();
    $match = MatchModel::firstOrFail();
    $announcement = Announcement::firstOrFail();

    $cases = [
        [Team::class, $team],
        [Tournament::class, $tournament],
        [Registration::class, $registration],
        [Payment::class, $payment],
        [MatchModel::class, $match],
        [Announcement::class, $announcement],
    ];

    foreach ($cases as [$class, $model]) {
        expect(Gate::forUser($organizer)->allows('viewAny', $class))->toBeTrue();
        expect(Gate::forUser($organizer)->allows('view', $model))->toBeTrue();
        expect(Gate::forUser($organizer)->allows('create', $class))->toBeTrue();
        expect(Gate::forUser($organizer)->allows('update', $model))->toBeTrue();
        expect(Gate::forUser($organizer)->allows('delete', $model))->toBeTrue();
    }
});

it('coach can update own team but not another', function () {
    ['coach' => $coach] = authUsers();

    $own = Team::where('name', 'San Antonio Vipers')->firstOrFail();
    $other = Team::where('name', '!=', 'San Antonio Vipers')->firstOrFail();

    expect(Gate::forUser($coach)->allows('update', $own))->toBeTrue();
    expect(Gate::forUser($coach)->allows('update', $other))->toBeFalse();
});

it('domain actions are organizer only', function () {
    ['organizer' => $organizer, 'coach' => $coach, 'fan' => $fan] = authUsers();

    $tournament = Tournament::firstOrFail();
    $payment = Payment::firstOrFail();
    $match = MatchModel::firstOrFail();
    $announcement = Announcement::firstOrFail();

    foreach ([$organizer, $coach, $fan] as $index => $user) {
        $allowed = $index === 0;
        expect(Gate::forUser($user)->allows('generateBracket', $tournament))->toBe($allowed);
        expect(Gate::forUser($user)->allows('verify', $payment))->toBe($allowed);
        expect(Gate::forUser($user)->allows('correctScore', $match))->toBe($allowed);
        expect(Gate::forUser($user)->allows('publish', $announcement))->toBe($allowed);
    }
});

it('non-model gates are organizer only', function () {
    ['organizer' => $organizer, 'coach' => $coach, 'fan' => $fan] = authUsers();

    foreach (['manage-users', 'reset-demo', 'view-audit'] as $gate) {
        expect(Gate::forUser($organizer)->allows($gate))->toBeTrue();
        expect(Gate::forUser($coach)->allows($gate))->toBeFalse();
        expect(Gate::forUser($fan)->allows($gate))->toBeFalse();
    }
});
