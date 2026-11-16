<?php

use App\Livewire\Organizer\AuditLogIndex;
use App\Models\MatchModel;
use App\Models\User;
use Database\Seeders\PrototypeSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PrototypeSeeder::class);
});

it('escapes user-controlled names in blade output', function () {
    $user = User::factory()->coach()->create([
        'first_name' => '<script>alert(1)</script>',
        'last_name' => 'Tester',
    ]);

    $this->actingAs($user)
        ->get('/profile')
        ->assertOk()
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
});

it('treats SQL metacharacters in search fields as plain text', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();

    Livewire::actingAs($organizer)
        ->test(AuditLogIndex::class)
        ->set('search', "' OR 1=1 --")
        ->assertSee('No audit entries match')
        ->assertDontSee('Verified payment for San Antonio Vipers');
});

it('escapes search terms rendered back into livewire output', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();

    Livewire::actingAs($organizer)
        ->test(AuditLogIndex::class)
        ->set('search', '<script>alert(1)</script>')
        ->assertDontSee('<script>alert(1)</script>', false);
});

it('escapes generated URLs in game stats links', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $match = MatchModel::firstOrFail();

    $expected = e(route('bracket', [
        'id' => $match->tournament_id,
        'match' => $match->id,
    ]));

    $this->actingAs($organizer)
        ->get('/game-stats?match='.$match->id)
        ->assertOk()
        ->assertSee('href="'.$expected.'"', false);
});
