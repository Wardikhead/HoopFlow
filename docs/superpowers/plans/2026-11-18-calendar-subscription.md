# Calendar Subscription (.ics) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Serve read-only `.ics` feeds for a tournament and for each participating team, and expose them through a Subscribe dialog on the schedule page.

**Architecture:** A public controller resolves the tournament/team and delegates to a service that selects published slots. The service maps models into event arrays and passes them to a pure ICS writer, which owns the calendar envelope, RFC 5545 escaping, line folding, timezone, and revision fields. The schedule page gets a small Livewire state addition and a dialog; no schema or dependency changes.

**Tech Stack:** Laravel 13, PHP 8.4+, Livewire 4, Blade, Alpine 3, Bootstrap 5.3 SCSS, Pest 4, Carbon.

## Global Constraints

- Run application commands from `src/`. Run git commands from the repository root.
- No new Composer package, npm package, database migration, model change, Bootstrap JavaScript, Tailwind, React, or Vue.
- Feed routes are public (no `auth` middleware) and numeric-only; throttle them at 60 requests per minute.
- Only slots with `published_at` set may appear in a feed.
- Event UIDs are `match-{match_id}@hoopflow` and never change.
- League timezone is `config('hoopflow.timezone')`, default `Asia/Manila`; `start_at` and `end_at` are wall-clock values, not UTC instants.
- `composer quality` must pass, and its actual output must be reported.
- Never stage `.obsidian/` or `.kilo/`.
- Follow existing code style: explicit return types, constructor property promotion, PHPDoc array shapes where PHPStan needs them.

---

### Task 1: ICS writer and timezone configuration

**Files:**
- Create: `src/config/hoopflow.php`
- Create: `src/app/Services/Calendar/IcsCalendarWriter.php`
- Create: `src/tests/Unit/Services/Calendar/IcsCalendarWriterTest.php`
- Modify: `src/app/Providers/AppServiceProvider.php:21-24` (the empty `register()` method)

**Interfaces:**
- Consumes: `Carbon\CarbonInterface`.
- Produces:
  - `App\Services\Calendar\IcsCalendarWriter::__construct(string $timezone = 'Asia/Manila')`
  - `IcsCalendarWriter::write(string $calendarName, string $calendarDescription, array $events, CarbonInterface $generatedAt): string`
  - Event array keys: `uid`, `start`, `end`, `summary`, `location`, `description`, `url`, `status` (`CONFIRMED` or `CANCELLED`), `sequence` (int), `modifiedAt`.
  - Config key: `hoopflow.timezone`.

- [ ] **Step 1: Write the failing writer tests**

Create `src/tests/Unit/Services/Calendar/IcsCalendarWriterTest.php`:

```php
<?php

use App\Services\Calendar\IcsCalendarWriter;
use Carbon\CarbonImmutable;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function calendarWriterEvent(array $overrides = []): array
{
    return array_merge([
        'uid' => 'match-42@hoopflow',
        'start' => CarbonImmutable::parse('2026-11-21 09:00:00'),
        'end' => CarbonImmutable::parse('2026-11-21 10:00:00'),
        'summary' => 'San Isidro vs Poblacion — Semifinals',
        'location' => 'Court 1, Brgy. Gym',
        'description' => "Summer Cup\nSemifinals\nhttps://hoopflow.test/schedule?id=12&date=2026-11-21",
        'url' => 'https://hoopflow.test/schedule?id=12&date=2026-11-21',
        'status' => 'CONFIRMED',
        'sequence' => 1763442000,
        'modifiedAt' => CarbonImmutable::parse('2026-11-18 04:00:00', 'UTC'),
    ], $overrides);
}

/**
 * @param  list<array<string, mixed>>  $events
 */
function calendarWriterFeed(array $events): string
{
    return (new IcsCalendarWriter('Asia/Manila'))->write(
        'Summer Cup',
        'Published game schedule for Summer Cup.',
        $events,
        CarbonImmutable::parse('2026-11-18 04:00:00', 'UTC'),
    );
}

it('renders the calendar envelope and timezone', function () {
    $ics = calendarWriterFeed([calendarWriterEvent()]);

    expect($ics)
        ->toContain('BEGIN:VCALENDAR')
        ->toContain('VERSION:2.0')
        ->toContain('PRODID:-//HoopFlow//Tournament Calendar//EN')
        ->toContain('CALSCALE:GREGORIAN')
        ->toContain('X-WR-CALNAME:HoopFlow — Summer Cup')
        ->toContain('X-WR-TIMEZONE:Asia/Manila')
        ->toContain('BEGIN:VTIMEZONE')
        ->toContain('TZID:Asia/Manila')
        ->toContain('TZOFFSETTO:+0800')
        ->toContain('TZNAME:PHT');
});

it('renders a confirmed event with a stable uid and manila wall time', function () {
    $ics = calendarWriterFeed([calendarWriterEvent()]);

    expect($ics)
        ->toContain('UID:match-42@hoopflow')
        ->toContain('DTSTART;TZID=Asia/Manila:20261121T090000')
        ->toContain('DTEND;TZID=Asia/Manila:20261121T100000')
        ->toContain('SUMMARY:San Isidro vs Poblacion — Semifinals')
        ->toContain('STATUS:CONFIRMED');

    expect($ics)->not->toContain('DTSTART:20261121T010000Z');
    expect($ics)->not->toContain('DTSTART;TZID=Asia/Manila:20261121T170000');
});

it('renders a cancelled event with the cancelled status', function () {
    $ics = calendarWriterFeed([
        calendarWriterEvent(['status' => 'CANCELLED']),
    ]);

    expect($ics)->toContain('STATUS:CANCELLED');
});

it('escapes text values', function () {
    $ics = calendarWriterFeed([
        calendarWriterEvent([
            'summary' => 'San Isidro, Poblacion; A\\B',
            'description' => "Line one\nLine two",
        ]),
    ]);

    expect($ics)
        ->toContain('SUMMARY:San Isidro\\, Poblacion\\; A\\\\B')
        ->toContain('DESCRIPTION:Line one\\nLine two');
});

it('folds long lines without splitting utf-8 characters', function () {
    $ics = calendarWriterFeed([
        calendarWriterEvent(['summary' => str_repeat('Peñalosa Warriors ', 8)]),
    ]);

    expect(mb_check_encoding($ics, 'UTF-8'))->toBeTrue();
    expect($ics)->toContain("\r\n ");

    foreach (explode("\r\n", $ics) as $line) {
        expect(strlen($line))->toBeLessThanOrEqual(75);
    }
});

it('uses crlf line endings', function () {
    $ics = calendarWriterFeed([calendarWriterEvent()]);

    expect(preg_match('/(?<!\r)\n/', $ics))->toBe(0)
        ->and($ics)->toContain("BEGIN:VCALENDAR\r\n");
});

it('writes utc timestamps for dtstamp and last-modified', function () {
    $ics = calendarWriterFeed([calendarWriterEvent()]);

    expect($ics)
        ->toContain('DTSTAMP:20261118T040000Z')
        ->toContain('LAST-MODIFIED:20261118T040000Z');
});

it('renders a valid empty calendar', function () {
    $ics = calendarWriterFeed([]);

    expect($ics)
        ->toContain('BEGIN:VCALENDAR')
        ->toContain('END:VCALENDAR');

    expect($ics)->not->toContain('BEGIN:VEVENT');
});
```

- [ ] **Step 2: Run the writer tests to verify they fail**

Run from `src/`:

```powershell
php artisan test tests/Unit/Services/Calendar/IcsCalendarWriterTest.php
```

Expected: FAIL with `Class "App\Services\Calendar\IcsCalendarWriter" not found`.

- [ ] **Step 3: Add the config, writer, and container binding**

Create `src/config/hoopflow.php`:

```php
<?php

return [
    'timezone' => env('HOOPFLOW_TIMEZONE', 'Asia/Manila'),
];
```

Create `src/app/Services/Calendar/IcsCalendarWriter.php`:

```php
<?php

namespace App\Services\Calendar;

use Carbon\CarbonInterface;

class IcsCalendarWriter
{
    public function __construct(private readonly string $timezone = 'Asia/Manila')
    {
    }

    /**
     * @param  list<array{
     *     uid: string,
     *     start: CarbonInterface,
     *     end: CarbonInterface,
     *     summary: string,
     *     location: string,
     *     description: string,
     *     url: string,
     *     status: 'CONFIRMED'|'CANCELLED',
     *     sequence: int,
     *     modifiedAt: CarbonInterface
     * }>  $events
     */
    public function write(
        string $calendarName,
        string $calendarDescription,
        array $events,
        CarbonInterface $generatedAt,
    ): string {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//HoopFlow//Tournament Calendar//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:'.$this->escapeText('HoopFlow — '.$calendarName),
            'X-WR-CALDESC:'.$this->escapeText($calendarDescription),
            'X-WR-TIMEZONE:'.$this->timezone,
            'REFRESH-INTERVAL;VALUE=DURATION:PT1H',
            'X-PUBLISHED-TTL:PT1H',
        ];

        foreach ($this->timezoneLines($generatedAt) as $line) {
            $lines[] = $line;
        }

        foreach ($events as $event) {
            foreach ($this->eventLines($event, $generatedAt) as $line) {
                $lines[] = $line;
            }
        }

        $lines[] = 'END:VCALENDAR';

        $output = [];

        foreach ($lines as $line) {
            foreach ($this->fold($line) as $folded) {
                $output[] = $folded;
            }
        }

        return implode("\r\n", $output)."\r\n";
    }

    /**
     * @return list<string>
     */
    private function timezoneLines(CarbonInterface $generatedAt): array
    {
        $local = $generatedAt->copy()->setTimezone($this->timezone);
        $offset = $local->format('O');
        $name = $this->timezone === 'Asia/Manila' ? 'PHT' : $local->format('T');

        return [
            'BEGIN:VTIMEZONE',
            'TZID:'.$this->timezone,
            'BEGIN:STANDARD',
            'DTSTART:19700101T000000',
            'TZOFFSETFROM:'.$offset,
            'TZOFFSETTO:'.$offset,
            'TZNAME:'.$name,
            'END:STANDARD',
            'END:VTIMEZONE',
        ];
    }

    /**
     * @param  array{
     *     uid: string,
     *     start: CarbonInterface,
     *     end: CarbonInterface,
     *     summary: string,
     *     location: string,
     *     description: string,
     *     url: string,
     *     status: 'CONFIRMED'|'CANCELLED',
     *     sequence: int,
     *     modifiedAt: CarbonInterface
     * }  $event
     * @return list<string>
     */
    private function eventLines(array $event, CarbonInterface $generatedAt): array
    {
        return [
            'BEGIN:VEVENT',
            'UID:'.$this->escapeText($event['uid']),
            'DTSTAMP:'.$generatedAt->copy()->utc()->format('Ymd\THis\Z'),
            'DTSTART;TZID='.$this->timezone.':'.$event['start']->format('Ymd\THis'),
            'DTEND;TZID='.$this->timezone.':'.$event['end']->format('Ymd\THis'),
            'SUMMARY:'.$this->escapeText($event['summary']),
            'LOCATION:'.$this->escapeText($event['location']),
            'DESCRIPTION:'.$this->escapeText($event['description']),
            'URL:'.$event['url'],
            'STATUS:'.$event['status'],
            'TRANSP:OPAQUE',
            'SEQUENCE:'.$event['sequence'],
            'LAST-MODIFIED:'.$event['modifiedAt']->copy()->utc()->format('Ymd\THis\Z'),
            'END:VEVENT',
        ];
    }

    private function escapeText(string $value): string
    {
        return str_replace(
            ["\\", "\r\n", "\n", "\r", ';', ','],
            ['\\\\', '\\n', '\\n', '\\n', '\\;', '\\,'],
            $value,
        );
    }

    /**
     * @return list<string>
     */
    private function fold(string $line): array
    {
        if (strlen($line) <= 75) {
            return [$line];
        }

        $chunks = [];
        $offset = 0;
        $limit = 75;

        while ($offset < strlen($line)) {
            $chunk = mb_strcut($line, $offset, $limit, 'UTF-8');

            if ($chunk === '') {
                break;
            }

            $chunks[] = $offset === 0 ? $chunk : ' '.$chunk;
            $offset += strlen($chunk);
            $limit = 74;
        }

        return $chunks;
    }
}
```

Modify `src/app/Providers/AppServiceProvider.php`. Add this import after the `App\Models\User` import:

```php
use App\Services\Calendar\IcsCalendarWriter;
```

Replace the empty `register()` method with:

```php
public function register(): void
{
    $this->app->bind(IcsCalendarWriter::class, fn (): IcsCalendarWriter => new IcsCalendarWriter(
        (string) config('hoopflow.timezone', 'Asia/Manila'),
    ));
}
```

- [ ] **Step 4: Run the writer tests to verify they pass**

Run from `src/`:

```powershell
php artisan test tests/Unit/Services/Calendar/IcsCalendarWriterTest.php
```

Expected: PASS, 8 tests.

- [ ] **Step 5: Format the new files**

Run from `src/`:

```powershell
php vendor/bin/pint --test tests/Unit/Services/Calendar/IcsCalendarWriterTest.php app/Services/Calendar/IcsCalendarWriter.php config/hoopflow.php app/Providers/AppServiceProvider.php
```

Expected: no style issues. If Pint reports issues, run the same command without `--test` and re-run the tests.

- [ ] **Step 6: Commit**

From the repository root:

```powershell
git add -- src/config/hoopflow.php src/app/Services/Calendar/IcsCalendarWriter.php src/tests/Unit/Services/Calendar/IcsCalendarWriterTest.php src/app/Providers/AppServiceProvider.php
git commit -m "feat(calendar): add ics calendar writer"
```

---

### Task 2: Schedule feed selection, controller, and routes

**Files:**
- Create: `src/routes/features/calendar.php`
- Create: `src/app/Http/Controllers/ScheduleCalendarController.php`
- Create: `src/app/Services/Calendar/ScheduleCalendarService.php`
- Create: `src/tests/Feature/Calendar/ScheduleCalendarFeedTest.php`

**Interfaces:**
- Consumes:
  - `IcsCalendarWriter::write(...)` from Task 1.
  - `App\Models\Tournament`, `App\Models\Team`, `App\Models\MatchModel`, `App\Models\MatchSlot`.
- Produces:
  - Route names `calendar.tournament` and `calendar.team`.
  - `ScheduleCalendarService::tournament(Tournament $tournament): string`
  - `ScheduleCalendarService::team(Tournament $tournament, Team $team): string`
  - `ScheduleCalendarController::tournament(...)` and `::team(...)`.

- [ ] **Step 1: Write the failing feed tests**

Create `src/tests/Feature/Calendar/ScheduleCalendarFeedTest.php`:

```php
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
```

- [ ] **Step 2: Run the feed tests to verify they fail**

Run from `src/`:

```powershell
php artisan test tests/Feature/Calendar/ScheduleCalendarFeedTest.php
```

Expected: FAIL with `Route [calendar.tournament] not defined`.

- [ ] **Step 3: Add the service, controller, and routes**

Create `src/app/Services/Calendar/ScheduleCalendarService.php`:

```php
<?php

namespace App\Services\Calendar;

use App\Models\MatchModel;
use App\Models\MatchSlot;
use App\Models\Team;
use App\Models\Tournament;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class ScheduleCalendarService
{
    public function __construct(private readonly IcsCalendarWriter $writer)
    {
    }

    public function tournament(Tournament $tournament): string
    {
        return $this->feed($tournament, null);
    }

    public function team(Tournament $tournament, Team $team): string
    {
        return $this->feed($tournament, $team);
    }

    private function feed(Tournament $tournament, ?Team $team): string
    {
        $query = MatchSlot::query()
            ->where('tournament_id', $tournament->id)
            ->whereNotNull('published_at')
            ->with(['match.bracket', 'match.homeTeam', 'match.awayTeam', 'court.venue'])
            ->orderBy('start_at');

        if ($team instanceof Team) {
            $query->whereHas('match', fn ($match) => $match->where(
                fn ($matchQuery) => $matchQuery
                    ->where('home_team_id', $team->id)
                    ->orWhere('away_team_id', $team->id),
            ));
        }

        $slots = $query->get();

        /** @var list<array{
         *     uid: string,
         *     start: CarbonInterface,
         *     end: CarbonInterface,
         *     summary: string,
         *     location: string,
         *     description: string,
         *     url: string,
         *     status: 'CONFIRMED'|'CANCELLED',
         *     sequence: int,
         *     modifiedAt: CarbonInterface
         * }> $events
         */
        $events = [];

        foreach ($slots as $slot) {
            $event = $this->event($slot, $tournament);

            if ($event !== null) {
                $events[] = $event;
            }
        }

        $name = $team instanceof Team
            ? $team->name.' — '.$tournament->name
            : $tournament->name;

        $description = $team instanceof Team
            ? 'Published games for '.$team->name.' in '.$tournament->name.'.'
            : 'Published game schedule for '.$tournament->name.'.';

        return $this->writer->write($name, $description, $events, CarbonImmutable::now());
    }

    /**
     * @return array{
     *     uid: string,
     *     start: CarbonInterface,
     *     end: CarbonInterface,
     *     summary: string,
     *     location: string,
     *     description: string,
     *     url: string,
     *     status: 'CONFIRMED'|'CANCELLED',
     *     sequence: int,
     *     modifiedAt: CarbonInterface
     * }|null
     */
    private function event(MatchSlot $slot, Tournament $tournament): ?array
    {
        $start = $slot->start_at;
        $end = $slot->end_at;

        if (! $start instanceof CarbonInterface || ! $end instanceof CarbonInterface) {
            return null;
        }

        $match = $slot->match;
        $home = $match?->homeTeam?->name ?? 'TBD';
        $away = $match?->awayTeam?->name ?? 'TBD';
        $round = $match?->roundLabel() ?? 'Match';
        $court = $slot->court?->name;
        $venue = $slot->court?->venue;
        $location = collect([$court, $venue?->name, $venue?->address])
            ->filter()
            ->implode(', ');
        $scheduleUrl = route('schedule', [
            'id' => $tournament->id,
            'date' => $start->format('Y-m-d'),
        ]);

        $description = collect([
            $tournament->name,
            $round,
            $match instanceof MatchModel && ($match->best_of ?? 0) > 1 ? $match->seriesGameLabel() : null,
            $scheduleUrl,
        ])->filter()->implode("\n");

        $modifiedAt = $slot->updated_at ?? $slot->created_at;

        if (! $modifiedAt instanceof CarbonInterface) {
            $modifiedAt = CarbonImmutable::now();
        }

        return [
            'uid' => 'match-'.$slot->match_id.'@hoopflow',
            'start' => $start,
            'end' => $end,
            'summary' => $home.' vs '.$away.' — '.$round,
            'location' => $location !== '' ? $location : $tournament->name,
            'description' => $description,
            'url' => $scheduleUrl,
            'status' => $match?->status === 'cancelled' ? 'CANCELLED' : 'CONFIRMED',
            'sequence' => $modifiedAt->getTimestamp(),
            'modifiedAt' => $modifiedAt,
        ];
    }
}
```

Create `src/app/Http/Controllers/ScheduleCalendarController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\MatchModel;
use App\Models\Team;
use App\Models\Tournament;
use App\Services\Calendar\ScheduleCalendarService;
use Illuminate\Http\Response;

class ScheduleCalendarController extends Controller
{
    public function tournament(Tournament $tournament, ScheduleCalendarService $calendar): Response
    {
        return $this->calendarResponse(
            $calendar->tournament($tournament),
            'hoopflow-'.$tournament->id.'.ics',
        );
    }

    public function team(Tournament $tournament, Team $team, ScheduleCalendarService $calendar): Response
    {
        $participates = MatchModel::query()
            ->where('tournament_id', $tournament->id)
            ->where(fn ($query) => $query
                ->where('home_team_id', $team->id)
                ->orWhere('away_team_id', $team->id))
            ->exists();

        abort_unless($participates, 404);

        return $this->calendarResponse(
            $calendar->team($tournament, $team),
            'hoopflow-'.$tournament->id.'-'.$team->id.'.ics',
        );
    }

    private function calendarResponse(string $ics, string $filename): Response
    {
        return new Response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }
}
```

Create `src/routes/features/calendar.php`:

```php
<?php

use App\Http\Controllers\ScheduleCalendarController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:60,1')->group(function () {
    Route::get('/calendar/tournaments/{tournament}.ics', [ScheduleCalendarController::class, 'tournament'])
        ->whereNumber('tournament')
        ->name('calendar.tournament');

    Route::get('/calendar/tournaments/{tournament}/teams/{team}.ics', [ScheduleCalendarController::class, 'team'])
        ->whereNumber('tournament')
        ->whereNumber('team')
        ->name('calendar.team');
});
```

- [ ] **Step 4: Run the feed tests to verify they pass**

Run from `src/`:

```powershell
php artisan test tests/Feature/Calendar/ScheduleCalendarFeedTest.php
```

Expected: PASS, 8 tests.

- [ ] **Step 5: Run the existing schedule tests for regressions**

Run from `src/`:

```powershell
php artisan test tests/Feature/Pages/ScheduleTest.php
```

Expected: PASS, all existing schedule tests.

- [ ] **Step 6: Format the new files**

Run from `src/`:

```powershell
php vendor/bin/pint --test tests/Feature/Calendar/ScheduleCalendarFeedTest.php app/Services/Calendar/ScheduleCalendarService.php app/Http/Controllers/ScheduleCalendarController.php routes/features/calendar.php
```

Expected: no style issues. If Pint reports issues, run the same command without `--test` and re-run the feed tests.

- [ ] **Step 7: Commit**

From the repository root:

```powershell
git add -- src/routes/features/calendar.php src/app/Http/Controllers/ScheduleCalendarController.php src/app/Services/Calendar/ScheduleCalendarService.php src/tests/Feature/Calendar/ScheduleCalendarFeedTest.php
git commit -m "feat(calendar): add schedule feed endpoints"
```

---

### Task 3: Subscribe dialog on the schedule page

**Files:**
- Modify: `src/app/Livewire/Organizer/ScheduleShow.php`
- Modify: `src/resources/views/livewire/organizer/schedule-show.blade.php`
- Create: `src/tests/Feature/Livewire/ScheduleSubscribeTest.php`

**Interfaces:**
- Consumes: route names `calendar.tournament` and `calendar.team` from Task 2.
- Produces:
  - `ScheduleShow::$showSubscribe` (bool)
  - `ScheduleShow::$subscribeTeamId` (?int)
  - `ScheduleShow::openSubscribe(): void`
  - `ScheduleShow::closeSubscribe(): void`
  - Computed `publishedSlots`, `hasPublishedGames`, `subscribeTeams`, `tournamentCalendarUrl`, `teamCalendarUrl`.

- [ ] **Step 1: Write the failing Livewire tests**

Create `src/tests/Feature/Livewire/ScheduleSubscribeTest.php`:

```php
<?php

use App\Livewire\Organizer\ScheduleShow;
use App\Models\Bracket;
use App\Models\Division;
use App\Models\MatchModel;
use App\Models\MatchSlot;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;
use Database\Seeders\PrototypeSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PrototypeSeeder::class);
});

it('shows the subscribe dialog and the tournament feed url', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $tournament = Tournament::where('slug', 'hoopflow-summer-cup')->firstOrFail();

    Livewire::actingAs($organizer)
        ->test(ScheduleShow::class, ['id' => $tournament->id])
        ->assertSeeHtml('data-testid="schedule-subscribe"')
        ->call('openSubscribe')
        ->assertSet('showSubscribe', true)
        ->assertSee(route('calendar.tournament', $tournament));
});

it('lists participating teams and updates the team feed url', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $tournament = Tournament::where('slug', 'hoopflow-summer-cup')->firstOrFail();
    $stranger = Team::factory()->create(['name' => 'Elsewhere Titans']);
    $slot = MatchSlot::query()
        ->where('tournament_id', $tournament->id)
        ->whereNotNull('published_at')
        ->whereHas('match', fn ($query) => $query->whereNotNull('home_team_id'))
        ->with('match.homeTeam')
        ->firstOrFail();
    $team = $slot->match->homeTeam;

    Livewire::actingAs($organizer)
        ->test(ScheduleShow::class, ['id' => $tournament->id])
        ->call('openSubscribe')
        ->assertSee($team->name)
        ->assertDontSee($stranger->name)
        ->assertDontSeeHtml('data-testid="subscribe-team-url"')
        ->set('subscribeTeamId', $team->id)
        ->assertSee(route('calendar.team', ['tournament' => $tournament, 'team' => $team]))
        ->assertSeeHtml('data-testid="subscribe-team-url"');
});

it('hides subscribe when there are no games', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $tournament = Tournament::factory()->create(['name' => 'Empty Cup']);

    Livewire::actingAs($organizer)
        ->test(ScheduleShow::class, ['id' => $tournament->id])
        ->assertDontSeeHtml('data-testid="schedule-subscribe"');
});

it('hides subscribe when games are not published', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $tournament = Tournament::factory()->create(['name' => 'Draft Cup']);
    $division = Division::factory()->for($tournament)->create();
    $bracket = Bracket::factory()->for($division)->create();
    $match = MatchModel::factory()->for($bracket)->for($tournament)->create([
        'status' => 'scheduled',
    ]);

    MatchSlot::factory()
        ->for($match, 'match')
        ->for($tournament)
        ->create(['published_at' => null]);

    Livewire::actingAs($organizer)
        ->test(ScheduleShow::class, ['id' => $tournament->id])
        ->assertDontSeeHtml('data-testid="schedule-subscribe"');
});

it('lets coaches and fans open the subscribe dialog', function (string $email) {
    $user = User::where('email', $email)->firstOrFail();
    $tournament = Tournament::where('slug', 'hoopflow-summer-cup')->firstOrFail();

    Livewire::actingAs($user)
        ->test(ScheduleShow::class, ['id' => $tournament->id])
        ->call('openSubscribe')
        ->assertSet('showSubscribe', true)
        ->assertSee(route('calendar.tournament', $tournament));
})->with([
    'coach' => 'coach.marcus@hoopflow.ph',
    'fan' => 'fan.juan@hoopflow.ph',
]);
```

- [ ] **Step 2: Run the Livewire tests to verify they fail**

Run from `src/`:

```powershell
php artisan test tests/Feature/Livewire/ScheduleSubscribeTest.php
```

Expected: FAIL because `schedule-subscribe` is not rendered and `openSubscribe` does not exist.

- [ ] **Step 3: Add the Livewire state and computed values**

Modify `src/app/Livewire/Organizer/ScheduleShow.php`.

Add `use App\Models\Team;` after the existing `use App\Models\Tournament;` import.

Add these two properties after `public ?string $selectedDay = null;`:

```php
public bool $showSubscribe = false;

public ?int $subscribeTeamId = null;
```

Add these lines to the class-level `@property-read` docblock after the `$scheduledMatchOptions` line:

```php
 * @property-read Collection<int, MatchSlot> $publishedSlots
 * @property-read bool $hasPublishedGames
 * @property-read array<int, array{id: int, name: string}> $subscribeTeams
 * @property-read string $tournamentCalendarUrl
 * @property-read string|null $teamCalendarUrl
```

Add `publishedSlots()` immediately after `scheduledSlots()`:

```php
/**
 * @return Collection<int, MatchSlot>
 */
#[Computed]
public function publishedSlots(): Collection
{
    return $this->scheduledSlots
        ->filter(fn (MatchSlot $slot): bool => $slot->published_at !== null)
        ->values();
}
```

Add `hasPublishedGames()` immediately after `hasScheduledGames()`, followed by `subscribeTeams()`, `tournamentCalendarUrl()`, and `teamCalendarUrl()`:

```php
#[Computed]
public function hasPublishedGames(): bool
{
    return $this->publishedSlots->isNotEmpty();
}

/**
 * @return array<int, array{id: int, name: string}>
 */
#[Computed]
public function subscribeTeams(): array
{
    /** @var array<int, string> $names */
    $names = [];

    foreach ($this->publishedSlots as $slot) {
        foreach ([$slot->match?->homeTeam, $slot->match?->awayTeam] as $team) {
            if (! $team instanceof Team) {
                continue;
            }

            $names[(int) $team->id] = (string) $team->name;
        }
    }

    asort($names);

    /** @var list<array{id: int, name: string}> $teams */
    $teams = [];

    foreach ($names as $id => $name) {
        $teams[] = [
            'id' => (int) $id,
            'name' => $name,
        ];
    }

    return $teams;
}

#[Computed]
public function tournamentCalendarUrl(): string
{
    return route('calendar.tournament', $this->tournamentId);
}

#[Computed]
public function teamCalendarUrl(): ?string
{
    if ((int) $this->subscribeTeamId < 1) {
        return null;
    }

    return route('calendar.team', [
        'tournament' => $this->tournamentId,
        'team' => (int) $this->subscribeTeamId,
    ]);
}
```

Add these actions after `closePreview()`:

```php
public function openSubscribe(): void
{
    $this->showSubscribe = true;
    $this->showPreview = false;
    $this->showReschedule = false;
    $this->showConfirm = false;
}

public function closeSubscribe(): void
{
    $this->showSubscribe = false;
}
```

Also add `$this->showSubscribe = false;` to the dialog resets in `startPreview()` (next to `$this->showPreview = true;`) and `openReschedule()` (next to `$this->showReschedule = true;`) so only one dialog can be open at a time.

- [ ] **Step 4: Add the button and dialog to the schedule view**

Modify `src/resources/views/livewire/organizer/schedule-show.blade.php`.

Insert this button after the Matchups link and before the Print button in the action row:

```blade
@if ($this->hasPublishedGames)
    <button type="button" class="hf-btn" wire:click="openSubscribe" data-testid="schedule-subscribe" wire:loading.attr="disabled" wire:loading.class="is-loading">
        <i class="bi bi-calendar-plus" aria-hidden="true"></i> Subscribe
    </button>
@endif
```

Insert this dialog immediately before `@if ($showConfirm)`:

```blade
@if ($showSubscribe)
    <dialog
        class="hf-dialog"
        x-data
        x-init="if (! $el.open) $el.showModal()"
        x-on:cancel.prevent="$wire.closeSubscribe()"
        x-on:click="if ($event.target === $el) $wire.closeSubscribe()"
        wire:key="schedule-subscribe-dialog"
        data-testid="schedule-subscribe-dialog"
        aria-labelledby="scheduleSubscribeDialogTitle">
        <div class="hf-dialog__head">
            <h2 class="hf-dialog__title" id="scheduleSubscribeDialogTitle">Subscribe to this schedule</h2>
            <button type="button" class="hf-icon-btn" wire:click="closeSubscribe" aria-label="Close dialog" data-testid="dialog-close">
                <i class="bi bi-x-lg" aria-hidden="true"></i>
            </button>
        </div>
        <div class="hf-dialog__body">
            <p class="hf-small hf-muted">
                Paste the link into Google Calendar → Other calendars → From URL. Apple Calendar and Outlook accept the same address.
            </p>

            <div class="hf-field hf-mt-2" x-data="{ copied: false }">
                <label class="hf-label" for="subscribeTournamentUrl">All tournament games</label>
                <div class="hf-btn-row">
                    <input
                        id="subscribeTournamentUrl"
                        class="hf-input"
                        type="text"
                        readonly
                        value="{{ $this->tournamentCalendarUrl }}"
                        x-ref="tournamentCalendarUrl"
                        data-testid="subscribe-tournament-url">
                    <button
                        type="button"
                        class="hf-btn"
                        x-on:click="
                            const input = $refs.tournamentCalendarUrl;
                            const done = () => { copied = true; setTimeout(() => copied = false, 1500); };
                            if (navigator.clipboard && window.isSecureContext) {
                                navigator.clipboard.writeText(input.value).then(done).catch(() => { input.select(); document.execCommand('copy'); done(); });
                            } else {
                                input.select();
                                document.execCommand('copy');
                                done();
                            }
                        "
                        x-text="copied ? 'Copied' : 'Copy'"
                        aria-live="polite"
                        data-testid="subscribe-copy-tournament">Copy</button>
                </div>
            </div>

            <div class="hf-field hf-mt-3" x-data="{ copied: false }">
                <label class="hf-label" for="subscribeTeam">One team</label>
                <select id="subscribeTeam" class="hf-select" wire:model.live="subscribeTeamId" data-testid="subscribe-team">
                    <option value="0">Choose a team</option>
                    @foreach ($this->subscribeTeams as $team)
                        <option value="{{ $team['id'] }}">{{ $team['name'] }}</option>
                    @endforeach
                </select>

                @if ($this->teamCalendarUrl !== null)
                    <div class="hf-btn-row hf-mt-2">
                        <input
                            id="subscribeTeamUrl"
                            class="hf-input"
                            type="text"
                            readonly
                            value="{{ $this->teamCalendarUrl }}"
                            x-ref="teamCalendarUrl"
                            data-testid="subscribe-team-url">
                        <button
                            type="button"
                            class="hf-btn"
                            x-on:click="
                                const input = $refs.teamCalendarUrl;
                                const done = () => { copied = true; setTimeout(() => copied = false, 1500); };
                                if (navigator.clipboard && window.isSecureContext) {
                                    navigator.clipboard.writeText(input.value).then(done).catch(() => { input.select(); document.execCommand('copy'); done(); });
                                } else {
                                    input.select();
                                    document.execCommand('copy');
                                    done();
                                }
                            "
                            x-text="copied ? 'Copied' : 'Copy'"
                            aria-live="polite"
                            data-testid="subscribe-copy-team">Copy</button>
                    </div>
                @else
                    <p class="hf-small hf-muted hf-mt-1" data-testid="subscribe-team-hint">Choose a team to get its link.</p>
                @endif
            </div>
        </div>
        <div class="hf-dialog__foot">
            <button type="button" class="hf-btn" wire:click="closeSubscribe" data-testid="subscribe-close">Close</button>
        </div>
    </dialog>
@endif
```

- [ ] **Step 5: Run the Livewire tests to verify they pass**

Run from `src/`:

```powershell
php artisan test tests/Feature/Livewire/ScheduleSubscribeTest.php
```

Expected: PASS, 6 tests.

- [ ] **Step 6: Run the schedule page tests again**

Run from `src/`:

```powershell
php artisan test tests/Feature/Pages/ScheduleTest.php tests/Feature/Livewire/ScheduleSubscribeTest.php
```

Expected: PASS, all tests in both files.

- [ ] **Step 7: Format the changed files**

Run from `src/`:

```powershell
php vendor/bin/pint --test app/Livewire/Organizer/ScheduleShow.php tests/Feature/Livewire/ScheduleSubscribeTest.php
```

Blade is not checked by Pint. Expected: no style issues. If Pint reports issues, run the same command without `--test`.

- [ ] **Step 8: Commit**

From the repository root:

```powershell
git add -- src/app/Livewire/Organizer/ScheduleShow.php src/resources/views/livewire/organizer/schedule-show.blade.php src/tests/Feature/Livewire/ScheduleSubscribeTest.php
git commit -m "feat(calendar): add schedule subscribe dialog"
```

---

### Task 4: Full quality gate

**Files:**
- No new files. This task verifies Tasks 1-3 together and commits formatting or static-analysis fixes if needed.

**Interfaces:**
- Consumes: everything produced by Tasks 1-3.
- Produces: a green quality gate and a clean commit history.

- [ ] **Step 1: Run the full gate**

Run from `src/`:

```powershell
composer quality
```

This runs Pint, PHPStan level 6, and the full Pest suite. Expected: Pint reports no style issues, PHPStan reports no errors, and every test passes.

- [ ] **Step 2: Fix any gate failures**

If Pint reports style issues, run from `src/` against only the calendar files:

```powershell
php vendor/bin/pint app/Services/Calendar app/Http/Controllers/ScheduleCalendarController.php app/Livewire/Organizer/ScheduleShow.php app/Providers/AppServiceProvider.php config/hoopflow.php routes/features/calendar.php tests/Feature/Calendar tests/Feature/Livewire/ScheduleSubscribeTest.php tests/Unit/Services/Calendar
```

If Pint reports issues in files outside that list, report them instead of reformatting unrelated work.

If PHPStan reports a nullable Carbon value in `ScheduleCalendarService::event()`, keep the `instanceof CarbonInterface` guards; do not add ignores. If it reports the event list type, keep the `@var list<array{...}>` annotation above `$events`. If it reports the writer timezone binding, confirm `AppServiceProvider::register()` is using the binding from Task 1.

If a test fails, run the failing file alone with `php artisan test <path>` and fix the production code, not the assertion, unless the assertion contradicts the approved spec.

- [ ] **Step 3: Re-run the gate**

Run from `src/`:

```powershell
composer quality
```

Expected: all green.

- [ ] **Step 4: Commit any formatting fixes**

If Step 2 changed files, commit only the calendar feature files from the repository root:

```powershell
git add -- src/app/Services/Calendar src/app/Http/Controllers/ScheduleCalendarController.php src/app/Livewire/Organizer/ScheduleShow.php src/app/Providers/AppServiceProvider.php src/config/hoopflow.php src/routes/features/calendar.php src/tests/Feature/Calendar src/tests/Feature/Livewire/ScheduleSubscribeTest.php src/tests/Unit/Services/Calendar
git commit -m "chore(calendar): format calendar subscription"
```

If no files changed, skip this commit.

- [ ] **Step 5: Report the actual output**

Report the real Pint, PHPStan, and Pest output. Do not claim success without the command output.
