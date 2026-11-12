<?php

use App\Models\Player;
use App\Models\Team;
use App\Models\Tournament;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'BracketDemoSeeder'])->assertExitCode(0);
});

it('seeds three demo tournaments with teams and approved registrations', function () {
    expect(Tournament::count())->toBe(3)
        ->and(Team::count())->toBe(16)
        ->and(Player::count())->toBe(160);

    $counts = Tournament::query()
        ->withCount(['registrations as approved_registrations_count' => fn ($query) => $query->where('status', 'approved')])
        ->get()
        ->pluck('approved_registrations_count', 'slug');

    expect($counts['demo-single-elimination'])->toBe(16)
        ->and($counts['demo-double-elimination'])->toBe(16)
        ->and($counts['demo-round-robin'])->toBe(8);
});
