<?php

use App\Livewire\Organizer\AuditLogIndex;
use App\Models\User;
use Database\Seeders\PrototypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PrototypeSeeder::class);
});

it('profile loads for organizer', function () {
    $user = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $this->actingAs($user)->get('/profile')->assertStatus(200);
});

it('profile loads for coach', function () {
    $user = User::where('email', 'coach.marcus@hoopflow.ph')->firstOrFail();
    $this->actingAs($user)->get('/profile')->assertStatus(200);
});

it('profile loads for fan', function () {
    $user = User::where('email', 'fan.juan@hoopflow.ph')->firstOrFail();
    $this->actingAs($user)->get('/profile')->assertStatus(200);
});

it('guest redirected from profile', function () {
    $this->get('/profile')->assertRedirect('/login');
});

it('audit loads for organizer', function () {
    $user = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $this->actingAs($user)->get('/audit')->assertStatus(200);
});

it('audit forbidden for coach', function () {
    $user = User::where('email', 'coach.marcus@hoopflow.ph')->firstOrFail();
    $this->actingAs($user)->get('/audit')->assertStatus(403);
});

it('audit forbidden for fan', function () {
    $user = User::where('email', 'fan.juan@hoopflow.ph')->firstOrFail();
    $this->actingAs($user)->get('/audit')->assertStatus(403);
});

it('guest redirected from audit', function () {
    $this->get('/audit')->assertRedirect('/login');
});

it('audit renders the prototype timeline, KPIs, and entity links', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();

    $this->actingAs($organizer)
        ->get('/audit')
        ->assertOk()
        ->assertSee('8 of 8 entries')
        ->assertSee('Score corrected')
        ->assertSee('Verified payment for San Antonio Vipers')
        ->assertSee('Status: paid')
        ->assertSee('data-testid="audit-kpi-total"', false)
        ->assertSee('data-testid="audit-team-link-', false)
        ->assertSee('data-testid="audit-match-link-', false)
        ->assertSee('data-testid="audit-tournament-link-', false)
        ->assertSee('data-testid="audit-announcement-link-', false);
});

it('audit filters by entity and reports the filtered count', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();

    $this->actingAs($organizer);

    Livewire::test(AuditLogIndex::class)
        ->assertSee('8 of 8 entries')
        ->set('entityType', 'team')
        ->assertSee('3 of 8 entries')
        ->assertSee('Verified payment for San Antonio Vipers')
        ->assertDontSee('Bracket generated');
});

it('audit filters by actor and search term', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();

    $this->actingAs($organizer);

    Livewire::test(AuditLogIndex::class)
        ->set('actor', (string) $organizer->id)
        ->assertSee('7 of 8 entries')
        ->set('actor', 'all')
        ->set('search', 'GCASH-9823412')
        ->assertSee('1 of 8 entries')
        ->assertSee('Verified payment for San Antonio Vipers');
});

it('audit clears filters and shows the empty state for no matches', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();

    $this->actingAs($organizer);

    Livewire::test(AuditLogIndex::class)
        ->set('entityType', 'team')
        ->set('search', 'no-such-entry')
        ->assertSee('No audit entries match')
        ->assertSee('audit-clear-inline', false)
        ->call('clearFilters')
        ->assertSet('entityType', 'all')
        ->assertSet('actor', 'all')
        ->assertSet('search', '')
        ->assertSee('8 of 8 entries');
});

it('audit initializes filters from the query string', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();

    $this->actingAs($organizer)
        ->get('/audit?entity=tournament')
        ->assertOk()
        ->assertSee('3 of 8 entries');
});
