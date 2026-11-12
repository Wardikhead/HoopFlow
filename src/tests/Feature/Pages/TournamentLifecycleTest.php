<?php

use App\Models\AuditLog;
use App\Models\Tournament;
use App\Models\User;
use App\Services\BracketService;
use Database\Seeders\PrototypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PrototypeSeeder::class);
});

it('activates the tournament when a bracket is generated and closes coach registration', function () {
    $tournament = Tournament::where('slug', 'pasig-youth-hoopfest')->firstOrFail();
    $coach = User::where('email', 'coach.marcus@hoopflow.ph')->firstOrFail();

    $this->actingAs($coach)
        ->get('/tournaments/'.$tournament->id)
        ->assertOk()
        ->assertSeeHtml('data-testid="show-register"');

    (new BracketService)->generate($tournament, 'single-elimination');

    expect($tournament->fresh()->status)->toBe('active')
        ->and(AuditLog::where('action', 'tournament.activate')->count())->toBe(1);

    $this->actingAs($coach)
        ->get('/tournaments/'.$tournament->id)
        ->assertOk()
        ->assertDontSeeHtml('data-testid="show-register"');
});
