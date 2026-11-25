<?php

/** Tests for Announcements test. */

use App\Livewire\Organizer\AnnouncementCreate;
use App\Livewire\Organizer\AnnouncementsIndex;
use App\Models\Announcement;
use App\Models\Organization;
use App\Models\Tournament;
use App\Models\User;
use Database\Seeders\PrototypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PrototypeSeeder::class);
});

it('announcements page loads for organizer', function () {
    $user = User::where('email', 'organizer@hoopflow.ph')->first();
    $this->actingAs($user)->get('/announcements')->assertStatus(200);
});

it('announcements page loads for coach', function () {
    $user = User::where('email', 'coach.marcus@hoopflow.ph')->first();
    $this->actingAs($user)->get('/announcements')->assertStatus(200);
});

it('announcements page loads for fan', function () {
    $user = User::where('email', 'fan.juan@hoopflow.ph')->first();
    $this->actingAs($user)->get('/announcements')->assertStatus(200);
});

it('create announcement loads for organizer', function () {
    $user = User::where('email', 'organizer@hoopflow.ph')->first();
    $this->actingAs($user)->get('/announcements/create')->assertStatus(200);
});

it('guest redirected from announcements', function () {
    $this->get('/announcements')->assertRedirect('/login');
});

it('non-organizer cannot access create announcement', function () {
    $user = User::where('email', 'fan.juan@hoopflow.ph')->first();
    $this->actingAs($user)->get('/announcements/create')->assertStatus(403);
});

it('shows coaches-only notices to organizers but not fans', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $fan = User::where('email', 'fan.juan@hoopflow.ph')->firstOrFail();

    $this->actingAs($organizer)
        ->get('/announcements')
        ->assertOk()
        ->assertSee('Championship week moves to Court 1')
        ->assertSee('Roster verification deadline is May 19');

    $this->actingAs($fan)
        ->get('/announcements')
        ->assertOk()
        ->assertSee('Championship week moves to Court 1')
        ->assertDontSee('Roster verification deadline is May 19');
});

it('shows specific-audience notices only to targeted users', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $coach = User::where('email', 'coach.marcus@hoopflow.ph')->firstOrFail();
    $fan = User::where('email', 'fan.juan@hoopflow.ph')->firstOrFail();
    $tournament = Tournament::where('slug', 'hoopflow-summer-cup')->firstOrFail();

    Announcement::create([
        'organization_id' => Organization::firstOrFail()->id,
        'tournament_id' => $tournament->id,
        'title' => 'Private warm-up slot change',
        'body' => 'Your team warms up on Court 2 instead of Court 1 this Saturday.',
        'category' => 'schedule',
        'audience' => 'specific',
        'target_user_ids' => [$fan->id],
        'pinned' => false,
        'status' => 'published',
        'published_at' => now(),
        'created_by' => $organizer->id,
    ]);

    $this->actingAs($fan)
        ->get('/announcements')
        ->assertOk()
        ->assertSee('Private warm-up slot change');

    $this->actingAs($coach)
        ->get('/announcements')
        ->assertOk()
        ->assertDontSee('Private warm-up slot change');
});

it('publishes an announcement with audience, category, pin, and audit entry', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $coach = User::where('email', 'coach.marcus@hoopflow.ph')->firstOrFail();
    $tournament = Tournament::where('slug', 'hoopflow-summer-cup')->firstOrFail();

    $this->actingAs($organizer);

    Livewire::test(AnnouncementCreate::class)
        ->assertSet('tournament_id', $tournament->id)
        ->set('title', 'Semifinal court change')
        ->set('body', 'All semifinal games move to Court 2. Gate opens 45 minutes before tip-off.')
        ->set('category', 'venue')
        ->set('audience', 'specific')
        ->set('target_user_ids', [$coach->id])
        ->set('pinned', true)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('announcements', ['id' => $tournament->id]));

    $announcement = Announcement::where('title', 'Semifinal court change')->firstOrFail();

    expect($announcement->category)->toBe('venue')
        ->and($announcement->audience)->toBe('specific')
        ->and($announcement->target_user_ids)->toBe([$coach->id])
        ->and($announcement->pinned)->toBeTrue()
        ->and($announcement->status)->toBe('published')
        ->and($announcement->published_at)->not->toBeNull()
        ->and($announcement->created_by)->toBe($organizer->id);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'announcement.publish',
        'entity_type' => 'announcement',
        'entity_id' => $announcement->id,
        'actor_user_id' => $organizer->id,
    ]);
});

it('requires at least one target user for a specific audience', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $tournament = Tournament::where('slug', 'hoopflow-summer-cup')->firstOrFail();

    $this->actingAs($organizer);

    Livewire::test(AnnouncementCreate::class)
        ->set('tournament_id', $tournament->id)
        ->set('title', 'Targeted notice')
        ->set('body', 'This notice needs somebody selected before it can publish.')
        ->set('category', 'general')
        ->set('audience', 'specific')
        ->set('target_user_ids', [])
        ->call('save')
        ->assertHasErrors(['target_user_ids']);
});

it('pins an announcement and records an audit entry', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $announcement = Announcement::where('title', 'Pasig Youth Hoopfest registration closes June 1')->firstOrFail();

    $this->actingAs($organizer);

    Livewire::test(AnnouncementsIndex::class)
        ->call('openAnnouncement', $announcement->id)
        ->call('togglePin')
        ->assertDispatched('hf-toast');

    expect($announcement->fresh()->pinned)->toBeTrue();

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'announcement.pin',
        'entity_type' => 'announcement',
        'entity_id' => $announcement->id,
        'actor_user_id' => $organizer->id,
    ]);
});

it('deletes an announcement and records an audit entry', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $announcement = Announcement::where('title', 'Court assignments posted for the CDO Classic')->firstOrFail();

    $this->actingAs($organizer);

    Livewire::test(AnnouncementsIndex::class)
        ->call('openAnnouncement', $announcement->id)
        ->call('deleteAnnouncement')
        ->assertDispatched('hf-toast');

    $this->assertModelMissing($announcement);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'announcement.delete',
        'entity_type' => 'announcement',
        'entity_id' => $announcement->id,
        'actor_user_id' => $organizer->id,
    ]);
});
