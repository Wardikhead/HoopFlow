<?php

use App\Models\Tournament;
use App\Services\BracketService;
use Database\Seeders\PrototypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PrototypeSeeder::class);
});

it('generates a 16-team single-elimination bracket with byes', function () {
    $tournament = Tournament::where('slug', 'hoopflow-summer-cup')->firstOrFail();

    $result = (new BracketService)->generate($tournament);

    expect($result['matches'])->toBe(15);
    expect($result['byes'])->toBe(1);
});

it('generates an 8-team round-robin bracket', function () {
    $tournament = Tournament::where('slug', 'cdo-grassroots-classic')->firstOrFail();

    $result = (new BracketService)->generate($tournament, 'round-robin');

    expect($result['matches'])->toBe(28);
    expect($result['byes'])->toBe(0);
});

it('throws on invalid format', function () {
    $tournament = Tournament::where('slug', 'cdo-grassroots-classic')->firstOrFail();

    expect(fn () => (new BracketService)->generate($tournament, 'double-elimination'))
        ->toThrow(InvalidArgumentException::class);
});
