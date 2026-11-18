# Calendar Subscription (.ics) — Design

- **Date:** 2026-11-18
- **Owner:** Jeward T. Mahinay
- **Status:** Approved for planning
- **Scope:** `src/routes/features`, `src/app/Http/Controllers`, `src/app/Services/Calendar`, `src/app/Livewire/Organizer/ScheduleShow.php`, `src/resources/views/livewire/organizer/schedule-show.blade.php`, `src/config/hoopflow.php`, `src/tests`. No migration, model change, Composer package, or npm package.
- **Related:** [[2026-11-07-phase-4e-schedule-design]], [[2026-11-12-bracket-series-schedule-ux-design]]

## Problem

A published HoopFlow schedule only exists inside HoopFlow. Coaches, parents, and fans must remember to open the schedule page to learn about a reschedule or cancellation. There is no way to push the published games into the calendar they already check every day.

The schedule page already has a good month/week/day view. What it lacks is a durable, read-only export that a personal calendar can follow without a HoopFlow account.

## Goals

1. Let any signed-in user subscribe to every published game in a tournament, or to one team's games only.
2. Keep subscribed calendars current when a game is rescheduled or cancelled, without creating duplicates.
3. Require no Google account, OAuth consent, API key, billing, background worker, new dependency, or database migration.
4. Keep unpublished previews and games without a time out of the feed.
5. Reuse the existing schedule page, dialog markup, Alpine behaviour, and design system.
6. Make the feed readable by Google Calendar, Apple Calendar, and Outlook.

## Non-goals

- No Google Calendar API, OAuth, two-way sync, or write-back.
- No per-user feeds, signed/expiring URLs, or access tokens.
- No one-time `.ics` download button.
- No reminders or `VALARM` entries; subscribers keep their own calendar's alert defaults.
- No email, push, or in-app notification when a game moves.
- No per-division or per-court feeds.
- No venue-level timezone handling; the league timezone is one config value.
- No database changes and no change to the existing schedule, bracket, or match pages.

## Decisions

| Decision | Choice |
|---|---|
| Integration style | Read-only iCalendar (`.ics`) subscription feeds |
| Feed scope | One tournament feed plus one feed per participating team |
| URL shape | Numeric IDs; no slugs, so renaming a tournament cannot break subscriptions |
| Authentication | None on feed routes; the same games are already visible on the public live page |
| Published filter | Only slots with `published_at` set |
| Cancelled games | Emitted with `STATUS:CANCELLED` when a published slot still exists |
| Event identity | `match-{match_id}@hoopflow`, stable for the life of the match |
| Revision signal | `SEQUENCE` from the slot's `updated_at` timestamp, plus `LAST-MODIFIED` |
| Duplicate slots | One event per match; newest `published_at`, then highest `id` |
| Timezone | League timezone from config, default `Asia/Manila`, fixed +08:00 |
| Feed refresh | Client-controlled; `REFRESH-INTERVAL` and `X-PUBLISHED-TTL` are hints only |
| Entry point | **Subscribe** button and dialog on the existing schedule page |
| Dependencies | None |

## Architecture

### 1. Feed endpoints

New `src/routes/features/calendar.php`, loaded by the existing feature-route glob. Both routes sit outside the `auth` middleware because calendar clients fetch without a session.

```php
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

Both return `text/calendar; charset=utf-8`, an inline disposition with an ASCII-safe filename such as `hoopflow-{tournament-id}.ics` or `hoopflow-{tournament-id}-{team-id}.ics`, and `Cache-Control: public, max-age=300`. The five-minute header only limits proxy re-fetching; Google still follows its own refresh schedule.

The controller stays thin:

- `tournament(Tournament $tournament, ScheduleCalendarService $calendar)` returns the tournament feed.
- `team(Tournament $tournament, Team $team, ScheduleCalendarService $calendar)` verifies the team has at least one match in the tournament, returns 404 otherwise, and returns the filtered feed.

Route binding handles an unknown tournament with the framework 404. A team that exists but never played in the tournament gets an explicit 404 rather than an empty calendar.

### 2. Selection and deduplication

`ScheduleCalendarService` performs one query with the needed relations:

```php
MatchSlot::query()
    ->where('tournament_id', $tournament->id)
    ->whereNotNull('published_at')
    ->when($team !== null, fn ($query) => $query->whereHas('match', fn ($match) => $match
        ->where('home_team_id', $team->id)
        ->orWhere('away_team_id', $team->id)))
    ->with(['match.homeTeam', 'match.awayTeam', 'court.venue'])
    ->orderByDesc('published_at')
    ->orderByDesc('id')
    ->get()
    ->unique('match_id')
    ->sortBy('start_at')
    ->values();
```

The descending order before `unique('match_id')` makes the newest published slot win when a match has more than one slot. The final sort gives calendars a stable chronological order.

Matches whose status is `cancelled` stay in the result when a slot exists; the writer marks them cancelled. Matches with no slot, byes, and slots that were never published are omitted.

A missing team, court, or venue never fails the feed. The event uses `TBD` for the missing side, the tournament name for a missing venue, and still carries the correct time.

### 3. iCalendar contract

The feed is built entirely in memory, then returned as one response. It begins with:

```
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//HoopFlow//Tournament Calendar//EN
CALSCALE:GREGORIAN
METHOD:PUBLISH
X-WR-CALNAME:HoopFlow — {calendar name}
X-WR-CALDESC:{calendar description}
X-WR-TIMEZONE:Asia/Manila
REFRESH-INTERVAL;VALUE=DURATION:PT1H
X-PUBLISHED-TTL:PT1H
BEGIN:VTIMEZONE
TZID:Asia/Manila
BEGIN:STANDARD
DTSTART:19700101T000000
TZOFFSETFROM:+0800
TZOFFSETTO:+0800
TZNAME:PHT
END:STANDARD
END:VTIMEZONE
```

Each selected slot becomes one VEVENT:

```
BEGIN:VEVENT
UID:match-42@hoopflow
DTSTAMP:20261118T040000Z
DTSTART;TZID=Asia/Manila:20261121T090000
DTEND;TZID=Asia/Manila:20261121T100000
SUMMARY:San Isidro vs Poblacion — Semifinals
LOCATION:Court 1\, Brgy. Gym
DESCRIPTION:U16 Cup\nSemifinals\nhttps://hoopflow.test/schedule?id=12&date=2026-11-21
URL:https://hoopflow.test/schedule?id=12&date=2026-11-21
STATUS:CONFIRMED
TRANSP:OPAQUE
SEQUENCE:1763442000
LAST-MODIFIED:20261118T040000Z
END:VEVENT
```

Property mapping:

| Property | Value |
|---|---|
| `UID` | `match-{match_id}@hoopflow`, stable across reschedules and renames |
| `DTSTART` / `DTEND` | Wall-clock slot times with `TZID=Asia/Manila` |
| `SUMMARY` | `{home} vs {away} — {round label}`; `TBD` for an unresolved side |
| `LOCATION` | `{court name}, {venue name}` plus the venue address when present |
| `DESCRIPTION` | Tournament name, round label, series game label when `best_of > 1`, and the absolute schedule URL |
| `URL` | Schedule page for the game's date |
| `STATUS` | `CONFIRMED`, or `CANCELLED` when the match status is `cancelled` |
| `SEQUENCE` | `$slot->updated_at->timestamp`; increases when the slot changes |
| `LAST-MODIFIED` | Slot `updated_at` converted to UTC |
| `DTSTAMP` | Request time converted to UTC |

Calendar names:

- Tournament feed: tournament name.
- Team feed: `{team name} — {tournament name}`.

The feed is UTF-8 with CRLF line endings. Text values escape backslashes, semicolons, commas, and newlines per RFC 5545. Lines are folded at 75 octets with a leading space on continuations, using a byte-safe cut so multi-byte names such as *Peñalosa* are never split.

An empty result is still a valid calendar: envelope, timezone block, and no VEVENTs.

### 4. Timezone handling

HoopFlow stores `start_at` as wall-clock time under a UTC application timezone and displays it unchanged (`09:00 AM`). Emitting true UTC with a trailing `Z` would make Google show that game at 5:00 PM Manila. The feed therefore treats the stored value as league-local time and emits it with `TZID=Asia/Manila`.

The zone comes from `config('hoopflow.timezone')`, backed by `HOOPFLOW_TIMEZONE` and defaulting to `Asia/Manila`. The Philippines has no daylight saving, so a fixed +08:00 `VTIMEZONE` is correct. Venue-level timezones are ignored because the application never sets them in normal use.

### 5. Subscribe dialog

`ScheduleShow` gains:

```php
public bool $showSubscribe = false;
public ?int $subscribeTeamId = null;

public function openSubscribe(): void;
public function closeSubscribe(): void;
```

Two computed values build absolute URLs:

```php
public function tournamentCalendarUrl(): string;
public function teamCalendarUrl(): ?string; // null until a team is chosen
```

A third computed value, `subscribeTeams`, derives the team list from the already-loaded `scheduledSlots`, so opening the dialog adds no query. It returns a sorted list of `['id' => int, 'name' => string]` for every team that appears in a published slot.

The schedule page header action row gains a **Subscribe** button when `hasScheduledGames` is true:

```
Tournament · Bracket · Matchups · Subscribe
```

The dialog reuses the native `<dialog class="hf-dialog">` pattern already used by the schedule preview:

1. **All tournament games** — read-only URL field and a Copy button.
2. **One team** — a labelled team `<select wire:model.live="subscribeTeamId">`, a read-only URL field that updates on selection, and a Copy button.

Copy uses Alpine's `navigator.clipboard.writeText()` with a select-and-`document.execCommand('copy')` fallback for non-secure origins. The button text changes to **Copied** for about 1.5 seconds and the change is announced through `aria-live="polite"`. If no team is selected, the team URL field is disabled and shows a short prompt instead of a dead link.

The dialog also states the one-step instruction: *Google Calendar → Other calendars → From URL → paste*. It notes that Apple Calendar and Outlook accept the same address.

### 6. Class responsibilities

| Class | Responsibility | Depends on |
|---|---|---|
| `ScheduleCalendarController` | Resolve models, enforce the team-in-tournament check, set response headers | Route binding, service |
| `ScheduleCalendarService` | Choose published slots, filter by team, dedupe by match, map models into event arrays | Eloquent models, config, writer |
| `IcsCalendarWriter` | Render a complete ICS string from event arrays, including envelope, VEVENT, timezone, escaping, and folding | Injected timezone and Carbon values; no models, HTTP, or config reads |

The writer does not query, authorize, or read config. The service does not build response objects. The controller does not format calendar text. Each can be tested on its own.

### 7. Writer API and event shape

The service injects the configured league timezone into the writer, so the writer never reads config itself:

```php
new IcsCalendarWriter(config('hoopflow.timezone'));
```

The writer exposes one method:

```php
/**
 * @param  list<array{
 *     uid: string,
 *     start: \Carbon\CarbonInterface,
 *     end: \Carbon\CarbonInterface,
 *     summary: string,
 *     location: string,
 *     description: string,
 *     url: string,
 *     status: 'CONFIRMED'|'CANCELLED',
 *     sequence: int,
 *     modifiedAt: \Carbon\CarbonInterface
 * }>  $events
 */
public function write(
    string $calendarName,
    string $calendarDescription,
    array $events,
    \Carbon\CarbonInterface $generatedAt,
): string;
```

`$generatedAt` is passed in rather than read inside the writer, so `DTSTAMP` is deterministic in tests. `start` and `end` carry the wall-clock values to stamp with `TZID`; `modifiedAt` and `generatedAt` are converted to UTC for `LAST-MODIFIED` and `DTSTAMP`.

## Files

| File | Change |
|---|---|
| `src/routes/features/calendar.php` | New public feed routes with numeric constraints and throttling. |
| `src/app/Http/Controllers/ScheduleCalendarController.php` | New thin controller for both feeds. |
| `src/app/Services/Calendar/ScheduleCalendarService.php` | New selection, filtering, dedupe, and event mapping. |
| `src/app/Services/Calendar/IcsCalendarWriter.php` | New pure ICS renderer. |
| `src/config/hoopflow.php` | New `timezone` value, default `Asia/Manila`. |
| `src/app/Livewire/Organizer/ScheduleShow.php` | Add subscribe state, actions, URL and team computed values. |
| `src/resources/views/livewire/organizer/schedule-show.blade.php` | Add Subscribe button and dialog with copy controls. |
| `src/tests/Unit/Services/Calendar/IcsCalendarWriterTest.php` | New writer tests. |
| `src/tests/Feature/Calendar/ScheduleCalendarFeedTest.php` | New feed route and behaviour tests. |
| `src/tests/Feature/Livewire/ScheduleSubscribeTest.php` | New dialog and URL tests. |
| `docs/superpowers/specs/2026-11-18-calendar-subscription-design.md` | This spec. |

## Contracts Preserved

- Every existing schedule route, policy, `data-testid`, form, dialog, and test-asserted string is unchanged.
- The schedule page still works for organizers, coaches, and fans; the button is additive and hidden when nothing is published.
- No Bootstrap JavaScript, no second client-side framework, and no new dependency.
- The feed never exposes unpublished slots or players, payments, or eligibility data.
- `.obsidian/` and `.kilo/` are never staged.

## Testing

### Writer unit tests

1. Envelope contains `VERSION:2.0`, the PRODID, `CALSCALE:GREGORIAN`, and the timezone block.
2. A confirmed event contains the expected UID, `DTSTART;TZID=Asia/Manila`, `DTEND`, summary, and status.
3. A cancelled event emits `STATUS:CANCELLED`.
4. Commas, semicolons, backslashes, and newlines are escaped.
5. A long summary is folded at 75 octets; the continuation begins with a space and a multi-byte character is not split.
6. Output uses CRLF line endings.
7. `DTSTAMP` and `LAST-MODIFIED` are UTC with a trailing `Z`.
8. An empty event list still produces a valid calendar with no VEVENT.
9. A stored 9:00 AM time renders as `T090000` under `Asia/Manila`, not `010000Z` or `170000`.

### Feed feature tests

1. An unauthenticated request returns 200 with `text/calendar`.
2. Published slots produce one VEVENT each; unpublished slots produce none.
3. A cancelled match with a slot produces `STATUS:CANCELLED`.
4. The team feed includes only matches where the team is home or away.
5. A team with no match in the tournament gets 404; an unknown tournament gets 404.
6. Rescheduling keeps the UID, changes `DTSTART`, and changes `SEQUENCE`.
7. Two slots for one match produce a single event, and the newest slot wins.
8. Both feeds include the correct calendar name.
9. A tournament with no published games returns a valid empty calendar.

### Livewire tests

1. The Subscribe button and dialog appear when the tournament has published games and are absent otherwise.
2. The dialog shows the tournament feed URL.
3. The team select lists only participating teams and updates the team feed URL when changed.
4. An organizer, coach, and fan can all open the dialog.

### Gates

- `composer quality` in `src/` passes, and the actual output is reported.
- No existing test is edited to accommodate the feature.

## Risks and Mitigations

| Risk | Mitigation |
|---|---|
| Google may take hours, occasionally a day, to re-read a subscribed feed. | Documented in the dialog and spec; the feed sets `REFRESH-INTERVAL` and `X-PUBLISHED-TTL` as hints. Reschedules are not promised as instant. |
| Subscribing to both the tournament and a team calendar shows the same game twice. | Expected behaviour of two calendars; the dialog explains the two options are alternatives. |
| Stored wall-clock times could be converted twice. | The writer formats wall time with `TZID` and never calls `utc()` on event start/end; a dedicated test covers 9:00 AM. |
| A feed URL is public. | The same games are already on public live/bracket pages; no private data is added, and routes are throttled. |
| Duplicate slots create duplicate events. | `unique('match_id')` after sorting by `published_at` and `id`; covered by a test. |
| A very large tournament creates a large response. | A 32-team bracket is tens of kilobytes; no cache is needed. The five-minute HTTP header limits repeated proxy fetches. |
| Clipboard API is unavailable on an insecure origin. | Select-and-copy fallback; the URL remains visible and selectable regardless. |
| A cancelled game whose slot is deleted disappears instead of showing cancelled. | Acceptable: the event leaves the feed and clients remove it on refresh, which is the correct outcome for a removed fixture. |
