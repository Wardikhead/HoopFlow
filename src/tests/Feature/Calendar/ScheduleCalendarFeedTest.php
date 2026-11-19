<?php

use App\Models\Bracket;
use App\Models\Court;
use App\Models\Division;
use App\Models\MatchModel;
use App\Models\MatchSlot;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\Venue;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * @return array{tournament: Tournament, venue: Venue, court: Court, home: Team, away: Team, match: MatchModel, slot: MatchSlot}
 */
function calendarFixture(): array
{
    $tournament = Tournament::factory()->create(['name' => 'Summer Cup']);
    $division = Division::factory()->for($tournament)->create();
    $bracket = Bracket::factory()->for($division)->create();
    $venue = Venue::factory()->create(['name' => 'Brgy. Gym', 'address' => 'Purok 1']);
    $court = Court::factory()->for($venue)->create(['name' => 'Court 1']);
    $home = Team::factory()->create(['name' => 'San Isidro']);
    $away = Team::factory()->create(['name' => 'Poblacion']);

    $match = MatchModel::factory()->for($bracket)->for($tournament)->create([
        'home_team_id' => $home->id,
        'away_team_id' => $away->id,
        'status' => 'scheduled',
        'round_number' => 2,
        'match_number' => 1,
    ]);

    $slot = MatchSlot::factory()->for($match, 'match')->for($tournament)->for($court)->create([
        'start_at' => '2026-11-21 09:00:00',
        'end_at' => '2026-11-21 10:00:00',
        'published_at' => '2026-11-18 04:00:00',
        'status' => 'published',
    ]);

    return compact('tournament', 'venue', 'court', 'home', 'away', 'match', 'slot');
}

/**
 * @return array{match: MatchModel, slot: MatchSlot}
 */
function addCalendarMatch(
    Tournament $tournament,
    Court $court,
    Team $home,
    Team $away,
    string $startAt = '2026-11-22 09:00:00',
): array {
    $bracket = Bracket::query()
        ->whereHas('division', fn ($query) => $query->where('tournament_id', $tournament->id))
        ->firstOrFail();

    $match = MatchModel::factory()->for($bracket)->for($tournament)->create([
        'home_team_id' => $home->id,
        'away_team_id' => $away->id,
        'status' => 'scheduled',
        'round_number' => 2,
        'match_number' => 2,
    ]);

    $slot = MatchSlot::factory()->for($match, 'match')->for($tournament)->for($court)->create([
        'start_at' => $startAt,
        'end_at' => CarbonImmutable::parse($startAt)->addHour(),
        'published_at' => '2026-11-18 04:00:00',
        'status' => 'published',
    ]);

    return compact('match', 'slot');
}

it('serves a public tournament feed with calendar headers', function () {
    ['tournament' => $tournament] = calendarFixture();

    $response = $this->get(route('calendar.tournament', $tournament));

    $response->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/calendar');
    expect($response->headers->get('Cache-Control'))->toContain('max-age=300');
    expect($response->getContent())
        ->toContain('BEGIN:VCALENDAR')
        ->toContain('X-WR-CALNAME:HoopFlow — Summer Cup');
});

it('emits one event for each published slot', function () {
    ['tournament' => $tournament, 'match' => $match] = calendarFixture();

    $content = (string) $this->get(route('calendar.tournament', $tournament))->getContent();

    expect(substr_count($content, 'BEGIN:VEVENT'))->toBe(1);
    expect($content)->toContain('UID:match-'.$match->id.'@hoopflow');
});

it('omits slots that are not published', function () {
    ['tournament' => $tournament, 'match' => $match, 'slot' => $slot] = calendarFixture();
    $slot->update(['published_at' => null]);

    $content = (string) $this->get(route('calendar.tournament', $tournament))->getContent();

    expect($content)->not->toContain('BEGIN:VEVENT');
    expect($content)->not->toContain('UID:match-'.$match->id.'@hoopflow');
});

it('marks cancelled matches as cancelled', function () {
    ['tournament' => $tournament, 'match' => $match] = calendarFixture();
    $match->update(['status' => 'cancelled']);

    $content = (string) $this->get(route('calendar.tournament', $tournament))->getContent();

    expect($content)->toContain('STATUS:CANCELLED');
});

it('filters a team feed to that team games', function () {
    [
        'tournament' => $tournament,
        'court' => $court,
        'home' => $home,
        'match' => $firstMatch,
    ] = calendarFixture();

    $otherHome = Team::factory()->create(['name' => 'Bagong Silang']);
    $otherAway = Team::factory()->create(['name' => 'Manggahan']);
    ['match' => $secondMatch] = addCalendarMatch($tournament, $court, $otherHome, $otherAway);

    $content = (string) $this->get(route('calendar.team', [
        'tournament' => $tournament,
        'team' => $home,
    ]))->getContent();

    expect($content)
        ->toContain('UID:match-'.$firstMatch->id.'@hoopflow')
        ->toContain('X-WR-CALNAME:HoopFlow — San Isidro — Summer Cup');

    expect($content)->not->toContain('UID:match-'.$secondMatch->id.'@hoopflow');
});

it('returns 404 for teams outside the tournament and unknown tournaments', function () {
    ['tournament' => $tournament] = calendarFixture();
    $stranger = Team::factory()->create(['name' => 'Elsewhere']);

    $this->get(route('calendar.team', ['tournament' => $tournament, 'team' => $stranger]))
        ->assertNotFound();

    $this->get('/calendar/tournaments/999999.ics')->assertNotFound();
});

it('keeps the uid and moves the event when a slot is rescheduled', function () {
    ['tournament' => $tournament, 'match' => $match, 'slot' => $slot] = calendarFixture();

    DB::table('match_slots')->where('id', $slot->id)->update([
        'updated_at' => '2026-11-18 04:00:00',
    ]);

    $before = (string) $this->get(route('calendar.tournament', $tournament))->getContent();

    DB::table('match_slots')->where('id', $slot->id)->update([
        'start_at' => '2026-11-21 11:00:00',
        'end_at' => '2026-11-21 12:00:00',
        'updated_at' => '2026-11-18 05:00:00',
    ]);

    $after = (string) $this->get(route('calendar.tournament', $tournament))->getContent();

    preg_match('/SEQUENCE:(\d+)/', $before, $beforeSequence);
    preg_match('/SEQUENCE:(\d+)/', $after, $afterSequence);

    expect($before)->toContain('DTSTART;TZID=Asia/Manila:20261121T090000');
    expect($after)
        ->toContain('UID:match-'.$match->id.'@hoopflow')
        ->toContain('DTSTART;TZID=Asia/Manila:20261121T110000')
        ->toContain('LAST-MODIFIED:20261118T050000Z');
    expect($after)->not->toContain('DTSTART;TZID=Asia/Manila:20261121T090000');
    expect((int) $afterSequence[1])->toBeGreaterThan((int) $beforeSequence[1]);
});

it('serves a valid empty calendar when nothing is published', function () {
    $tournament = Tournament::factory()->create(['name' => 'Empty Cup']);

    $content = (string) $this->get(route('calendar.tournament', $tournament))->getContent();

    expect($content)
        ->toContain('BEGIN:VCALENDAR')
        ->toContain('END:VCALENDAR');

    expect($content)->not->toContain('BEGIN:VEVENT');
});
