<?php

use App\Livewire\Organizer\AnnouncementCreate;
use App\Models\Announcement;
use App\Models\User;
use Database\Seeders\PrototypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function hfDraftOrganizer(): User
{
    return User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
}

/**
 * @return array<string, mixed>
 */
function hfDraftResponse(string $title, string $body): array
{
    return [
        'candidates' => [[
            'content' => [
                'parts' => [['text' => json_encode(['title' => $title, 'body' => $body], JSON_THROW_ON_ERROR)]],
            ],
        ]],
    ];
}

beforeEach(function () {
    $this->seed(PrototypeSeeder::class);

    config([
        'services.gemini.key' => 'test-key',
        'services.gemini.model' => 'gemini-test',
    ]);

    RateLimiter::clear('announcement-draft:'.hfDraftOrganizer()->id);
});

it('shows the drafting card when Gemini is configured', function () {
    Livewire::actingAs(hfDraftOrganizer())
        ->test(AnnouncementCreate::class)
        ->assertSee('Draft with AI')
        ->assertSee('Rough notes')
        ->assertSee('data-testid="announcement-ai-draft"', false);
});

it('hides the drafting controls when Gemini is not configured', function () {
    config(['services.gemini.key' => null]);

    Livewire::actingAs(hfDraftOrganizer())
        ->test(AnnouncementCreate::class)
        ->assertSee('AI drafting is not configured.')
        ->assertDontSee('Rough notes')
        ->assertDontSee('data-testid="announcement-ai-draft"', false)
        ->assertSee('Publish announcement');
});

it('requires at least ten characters of notes before drafting', function () {
    Http::fake();

    Livewire::actingAs(hfDraftOrganizer())
        ->test(AnnouncementCreate::class)
        ->set('aiNotes', 'short')
        ->call('draftWithAi')
        ->assertHasErrors(['aiNotes' => 'min']);

    Http::assertNothingSent();
});

it('fills the headline and message from a successful draft', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(hfDraftResponse(
            'Finals move to Court 1',
            'All semifinal and final games will be played on Court 1. Gate opens 45 minutes before tip-off.',
        )),
    ]);

    Livewire::actingAs(hfDraftOrganizer())
        ->test(AnnouncementCreate::class)
        ->set('aiNotes', 'gate 45 min early, finals court 1')
        ->call('draftWithAi')
        ->assertHasNoErrors()
        ->assertSet('title', 'Finals move to Court 1')
        ->assertSet('body', 'All semifinal and final games will be played on Court 1. Gate opens 45 minutes before tip-off.')
        ->assertSet('aiNotes', 'gate 45 min early, finals court 1')
        ->assertSet('aiDraftError', null);

    Http::assertSentCount(1);
});

it('keeps the existing text and shows an error when the draft fails', function () {
    Http::fake(['*' => Http::response(['error' => ['message' => 'boom']], 500)]);

    Livewire::actingAs(hfDraftOrganizer())
        ->test(AnnouncementCreate::class)
        ->set('title', 'Existing headline')
        ->set('body', 'The existing message is still here.')
        ->set('aiNotes', 'gate 45 min early, finals court 1')
        ->call('draftWithAi')
        ->assertSet('title', 'Existing headline')
        ->assertSet('body', 'The existing message is still here.')
        ->assertSet('aiDraftError', "Couldn't draft right now. Your notes are safe — try again or write it manually.");
});

it('shows a busy message when Gemini rate limits the request', function () {
    Http::fake(['*' => Http::response([], 429)]);

    Livewire::actingAs(hfDraftOrganizer())
        ->test(AnnouncementCreate::class)
        ->set('aiNotes', 'gate 45 min early, finals court 1')
        ->call('draftWithAi')
        ->assertSet('aiDraftError', 'AI drafting is busy. Try again in a minute.');
});

it('limits each organizer to five drafts per minute', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(hfDraftResponse(
            'Court update',
            'All games remain on Court 1. Gate opens 45 minutes before tip-off.',
        )),
    ]);

    $component = Livewire::actingAs(hfDraftOrganizer())
        ->test(AnnouncementCreate::class)
        ->set('aiNotes', 'all games court 1, gate 45 min early');

    foreach (range(1, 5) as $attempt) {
        $component->call('draftWithAi')->assertHasNoErrors();
    }

    $component->call('draftWithAi')
        ->assertSet('aiDraftError', "You've drafted a few times already. Wait a minute.");

    Http::assertSentCount(5);
});

it('does not publish an announcement when drafting', function () {
    $before = Announcement::count();

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(hfDraftResponse(
            'Finals move to Court 1',
            'All semifinal and final games will be played on Court 1. Gate opens 45 minutes before tip-off.',
        )),
    ]);

    Livewire::actingAs(hfDraftOrganizer())
        ->test(AnnouncementCreate::class)
        ->set('aiNotes', 'gate 45 min early, finals court 1')
        ->call('draftWithAi')
        ->assertHasNoErrors();

    expect(Announcement::count())->toBe($before);
});

it('does nothing when the drafting feature is not configured', function () {
    config(['services.gemini.key' => null]);
    Http::fake();

    Livewire::actingAs(hfDraftOrganizer())
        ->test(AnnouncementCreate::class)
        ->set('aiNotes', 'gate 45 min early, finals court 1')
        ->call('draftWithAi')
        ->assertSet('aiDraftError', null);

    Http::assertNothingSent();
});
