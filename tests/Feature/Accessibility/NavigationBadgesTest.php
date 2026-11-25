<?php

/** Tests for Navigation badges test. */

use App\Models\Announcement;
use App\Models\MatchModel;
use App\Models\MatchSlot;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\Tournament;
use App\Models\User;
use App\Services\NavigationBadgeService;
use Database\Seeders\PrototypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PrototypeSeeder::class);

    $this->badgeService = app(NavigationBadgeService::class);
    $this->organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $this->coach = User::where('email', 'coach.marcus@hoopflow.ph')->firstOrFail();
    $this->fan = User::where('email', 'fan.juan@hoopflow.ph')->firstOrFail();
});

/**
 * Seeded data is not deterministic, so every assertion is expressed as a delta
 * from the count before the fixture was created.
 */
function navBadgeCount(NavigationBadgeService $service, User $user, string $key): int
{
    return (int) ($service->forUser($user)[$key] ?? 0);
}

it('returns a count for every destination with work waiting for an organizer', function () {
    Announcement::factory()->create([
        'audience' => 'all',
        'status' => 'published',
        'published_at' => now()->subDay(),
    ]);
    Tournament::factory()->create(['status' => 'registration-open']);

    $registration = Registration::factory()->create(['status' => 'submitted']);
    Payment::factory()->create([
        'registration_id' => $registration->id,
        'status' => 'pending',
    ]);

    $match = MatchModel::factory()->create();
    MatchSlot::factory()->create([
        'match_id' => $match->id,
        'tournament_id' => $match->tournament_id,
        'start_at' => now()->setTime(12, 0),
        'end_at' => now()->setTime(13, 0),
    ]);

    $badges = $this->badgeService->forUser($this->organizer);

    expect($badges)->toHaveKeys([
        'announcements',
        'tournaments',
        'submissions',
        'payments',
        'schedule',
    ]);
});

it('gives coaches only the shared destinations', function () {
    Announcement::factory()->create([
        'audience' => 'coaches',
        'status' => 'published',
        'published_at' => now()->subDay(),
    ]);

    $badges = $this->badgeService->forUser($this->coach);

    expect($badges)->toHaveKey('announcements')
        ->not->toHaveKey('submissions')
        ->not->toHaveKey('payments');
});

it('gives fans only the shared destinations', function () {
    Announcement::factory()->create([
        'audience' => 'fans',
        'status' => 'published',
        'published_at' => now()->subDay(),
    ]);

    $badges = $this->badgeService->forUser($this->fan);

    expect($badges)->toHaveKey('announcements')
        ->not->toHaveKey('submissions')
        ->not->toHaveKey('payments');
});

it('omits zero counts from the result', function () {
    $badges = $this->badgeService->forUser($this->organizer);

    foreach ($badges as $count) {
        expect($count)->toBeGreaterThan(0);
    }
});

it('counts only published announcements from the last seven days', function () {
    $before = navBadgeCount($this->badgeService, $this->fan, 'announcements');

    Announcement::factory()->create([
        'audience' => 'fans',
        'status' => 'published',
        'published_at' => now()->subDay(),
    ]);
    Announcement::factory()->create([
        'audience' => 'fans',
        'status' => 'published',
        'published_at' => now()->subDays(8),
    ]);
    Announcement::factory()->create([
        'audience' => 'fans',
        'status' => 'draft',
        'published_at' => now()->subDay(),
    ]);

    expect(navBadgeCount($this->badgeService, $this->fan, 'announcements'))
        ->toBe($before + 1);
});

it('applies the audience rules to announcements', function () {
    $organizerBefore = navBadgeCount($this->badgeService, $this->organizer, 'announcements');
    $fanBefore = navBadgeCount($this->badgeService, $this->fan, 'announcements');

    Announcement::factory()->create([
        'audience' => 'organizers',
        'status' => 'published',
        'published_at' => now()->subDay(),
    ]);

    expect(navBadgeCount($this->badgeService, $this->organizer, 'announcements'))
        ->toBe($organizerBefore + 1);
    expect(navBadgeCount($this->badgeService, $this->fan, 'announcements'))
        ->toBe($fanBefore);
});

it('counts a specific announcement only for targeted users', function () {
    $coachBefore = navBadgeCount($this->badgeService, $this->coach, 'announcements');
    $fanBefore = navBadgeCount($this->badgeService, $this->fan, 'announcements');

    Announcement::factory()->create([
        'audience' => 'specific',
        'target_user_ids' => [$this->coach->id],
        'status' => 'published',
        'published_at' => now()->subDay(),
    ]);

    expect(navBadgeCount($this->badgeService, $this->coach, 'announcements'))
        ->toBe($coachBefore + 1);
    expect(navBadgeCount($this->badgeService, $this->fan, 'announcements'))
        ->toBe($fanBefore);
});

it('counts only pending submissions on non-archived tournaments', function () {
    $before = navBadgeCount($this->badgeService, $this->organizer, 'submissions');

    Registration::factory()->create(['status' => 'pending']);

    expect(navBadgeCount($this->badgeService, $this->organizer, 'submissions'))
        ->toBe($before + 1);

    $archived = Tournament::factory()->create(['archived_at' => now()]);
    Registration::factory()->create([
        'status' => 'submitted',
        'tournament_id' => $archived->id,
    ]);

    expect(navBadgeCount($this->badgeService, $this->organizer, 'submissions'))
        ->toBe($before + 1);
});

it('counts pending and partial payments but not verified or rejected ones', function () {
    $before = navBadgeCount($this->badgeService, $this->organizer, 'payments');

    $partial = Registration::factory()->create(['status' => 'approved']);
    Payment::factory()->create([
        'registration_id' => $partial->id,
        'status' => 'partial',
    ]);

    $pending = Registration::factory()->create(['status' => 'submitted']);
    Payment::factory()->create([
        'registration_id' => $pending->id,
        'status' => 'pending',
    ]);

    expect(navBadgeCount($this->badgeService, $this->organizer, 'payments'))
        ->toBe($before + 2);

    $verified = Registration::factory()->create(['status' => 'approved']);
    Payment::factory()->create([
        'registration_id' => $verified->id,
        'status' => 'verified',
    ]);

    $rejected = Registration::factory()->create(['status' => 'rejected']);
    Payment::factory()->create([
        'registration_id' => $rejected->id,
        'status' => 'pending',
    ]);

    expect(navBadgeCount($this->badgeService, $this->organizer, 'payments'))
        ->toBe($before + 2);
});

it('excludes pending payments on archived tournaments', function () {
    $before = navBadgeCount($this->badgeService, $this->organizer, 'payments');

    $archived = Tournament::factory()->create(['archived_at' => now()]);
    $registration = Registration::factory()->create([
        'status' => 'approved',
        'tournament_id' => $archived->id,
    ]);
    Payment::factory()->create([
        'registration_id' => $registration->id,
        'status' => 'pending',
    ]);

    expect(navBadgeCount($this->badgeService, $this->organizer, 'payments'))
        ->toBe($before);
});

it('counts today\'s matches and ignores other days', function () {
    $before = navBadgeCount($this->badgeService, $this->organizer, 'schedule');

    MatchSlot::factory()->create([
        'start_at' => now()->setTime(12, 0),
        'end_at' => now()->setTime(13, 0),
    ]);
    MatchSlot::factory()->create([
        'start_at' => now()->subDay()->setTime(12, 0),
        'end_at' => now()->subDay()->setTime(13, 0),
    ]);
    MatchSlot::factory()->create([
        'start_at' => now()->addDay()->setTime(12, 0),
        'end_at' => now()->addDay()->setTime(13, 0),
    ]);

    expect(navBadgeCount($this->badgeService, $this->organizer, 'schedule'))
        ->toBe($before + 1);
});

/**
 * Nav badges x path.
 *
 * @param  string  $html  The html.
 * @return DOMXPath The result.
 */
function navBadgesXPath(string $html): DOMXPath
{
    $dom = new DOMDocument;
    $previous = libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    return new DOMXPath($dom);
}

it('renders count badges in the organizer rail', function () {
    Announcement::factory()->create([
        'audience' => 'all',
        'status' => 'published',
        'published_at' => now()->subDay(),
    ]);
    Tournament::factory()->create(['status' => 'registration-open']);

    $registration = Registration::factory()->create(['status' => 'submitted']);
    Payment::factory()->create([
        'registration_id' => $registration->id,
        'status' => 'pending',
    ]);

    MatchSlot::factory()->create([
        'start_at' => now()->setTime(12, 0),
        'end_at' => now()->setTime(13, 0),
    ]);

    $response = $this->actingAs($this->organizer)->get(route('dashboard'))->assertOk();
    $xpath = navBadgesXPath((string) $response->getContent());

    foreach (['announcements', 'tournaments', 'submissions', 'payments', 'schedule'] as $key) {
        $badge = $xpath->query(
            "//a[@data-testid='nav-{$key}']//span[contains(@class, 'hf-nav-badge')]"
        )->item(0);

        expect($badge)->not->toBeNull("Missing badge on nav-{$key}");
        expect(trim($badge->textContent))->toMatch('/^\d+\+?$/');
    }
});

it('renders badges in the More sheet links', function () {
    Announcement::factory()->create([
        'audience' => 'all',
        'status' => 'published',
        'published_at' => now()->subDay(),
    ]);

    $response = $this->actingAs($this->organizer)->get(route('dashboard'))->assertOk();
    $xpath = navBadgesXPath((string) $response->getContent());
    $badge = $xpath->query(
        "//a[@data-more-link='announcements']//span[contains(@class, 'hf-nav-badge')]"
    )->item(0);

    expect($badge)->not->toBeNull();
});

it('hides submissions and payments from a fan', function () {
    $response = $this->actingAs($this->fan)->get(route('dashboard'))->assertOk();
    $xpath = navBadgesXPath((string) $response->getContent());

    expect($xpath->query("//a[@data-testid='nav-submissions']")->length)->toBe(0);
    expect($xpath->query("//a[@data-testid='nav-payments']")->length)->toBe(0);
});

it('renders no badge or suffix for a zero count', function () {
    $response = $this->actingAs($this->fan)->get(route('dashboard'))->assertOk();
    $xpath = navBadgesXPath((string) $response->getContent());
    $link = $xpath->query("//a[@data-testid='nav-announcements']")->item(0);

    expect($link)->not->toBeNull();
    expect($xpath->query(".//span[contains(@class, 'hf-nav-badge')]", $link)->length)->toBe(0);
    expect($xpath->query(".//span[contains(@class, 'hf-sr-only')]", $link)->length)->toBe(0);
    expect($link->getAttribute('data-label'))->toBe('Announcements');
});

it('names the count in the screen-reader suffix and tooltip', function () {
    Announcement::factory()->create([
        'audience' => 'all',
        'status' => 'published',
        'published_at' => now()->subDay(),
    ]);

    $response = $this->actingAs($this->organizer)->get(route('dashboard'))->assertOk();
    $xpath = navBadgesXPath((string) $response->getContent());
    $link = $xpath->query("//a[@data-testid='nav-announcements']")->item(0);
    $suffix = $xpath->query(".//span[contains(@class, 'hf-sr-only')]", $link)->item(0);

    expect($suffix)->not->toBeNull();
    expect(trim($suffix->textContent))->toMatch('/^, \d+ new$/');
    expect($link->getAttribute('data-label'))->toMatch('/^Announcements · \d+ new$/');
});

it('badges the mobile schedule tab', function () {
    MatchSlot::factory()->create([
        'start_at' => now()->setTime(12, 0),
        'end_at' => now()->setTime(13, 0),
    ]);

    $response = $this->actingAs($this->fan)->get(route('dashboard'))->assertOk();
    $xpath = navBadgesXPath((string) $response->getContent());
    $tab = $xpath->query("//a[@data-testid='mobile-nav-schedule']")->item(0);

    expect($tab)->not->toBeNull();
    expect($xpath->query(".//span[contains(@class, 'hf-nav-badge')]", $tab)->length)->toBe(1);

    $suffix = $xpath->query(".//span[contains(@class, 'hf-sr-only')]", $tab)->item(0);
    expect($suffix)->not->toBeNull();
    expect(trim($suffix->textContent))->toMatch('/^, \d+ matches today$/');
});

it('marks the More tab with a dot when a sheet destination has a count', function () {
    Announcement::factory()->create([
        'audience' => 'all',
        'status' => 'published',
        'published_at' => now()->subDay(),
    ]);

    $response = $this->actingAs($this->organizer)->get(route('dashboard'))->assertOk();
    $xpath = navBadgesXPath((string) $response->getContent());
    $more = $xpath->query("//button[@data-testid='mobile-nav-more']")->item(0);

    expect($more)->not->toBeNull();
    expect($xpath->query(".//span[contains(@class, 'hf-nav-badge--dot')]", $more)->length)->toBe(1);

    $suffix = $xpath->query(".//span[contains(@class, 'hf-sr-only')]", $more)->item(0);
    expect($suffix)->not->toBeNull();
    expect(trim($suffix->textContent))->toBe(', New items in More');
});
