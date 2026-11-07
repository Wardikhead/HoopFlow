<?php

use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\MatchModel;
use App\Models\Payment;
use App\Models\Player;
use App\Models\Registration;
use App\Models\Team;
use App\Models\Tournament;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds prototype data', function () {
    $this->artisan('db:seed', ['--class' => 'PrototypeSeeder'])->assertExitCode(0);

    expect(Tournament::count())->toBe(3);
    expect(Team::count())->toBeGreaterThanOrEqual(31);
    expect(Player::count())->toBeGreaterThanOrEqual(200);
    expect(MatchModel::count())->toBe(15);
    expect(Payment::count())->toBeGreaterThanOrEqual(20);
    expect(Announcement::count())->toBe(4);
    expect(AuditLog::count())->toBeGreaterThanOrEqual(8);
});

it('has no orphan registrations', function () {
    $this->artisan('db:seed', ['--class' => 'PrototypeSeeder'])->assertExitCode(0);

    expect(Registration::whereDoesntHave('team')->count())->toBe(0);
    expect(Registration::whereDoesntHave('tournament')->count())->toBe(0);
});
