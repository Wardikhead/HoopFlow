# HoopFlow Landing Page Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the walking-skeleton home page with a warm, editorial landing page that lets visitors explore a read-only bracket, schedule, and standings demo.

**Architecture:** Blade + curated PHP showcase data + vanilla JS. A dedicated `layouts.landing` shell and section partials compose the page; `LandingShowcase` supplies all demo content so the page never depends on the database. Separate `landing.scss` and `landing.js` Vite entries keep the app bundle untouched.

**Tech Stack:** Laravel 13, PHP 8.4+, Blade, Bootstrap 5.3 compiled from SCSS, Vite, vanilla ES modules, Pest, Pint, Larastan.

## Global Constraints

- Laravel 13, PHP 8.4+, Blade, Bootstrap 5.3 from SCSS, Vite.
- **No Bootstrap JavaScript, Livewire, Alpine, Tailwind, Vue, React, or new npm dependencies.**
- All demo names, players, references, and amounts are fictional. No real personal data.
- `LandingShowcase` is a plain PHP class: no Eloquent, no database queries.
- The landing page lives at `/` and is public. Signed-in visitors see a `Dashboard` link.
- Keep `layouts.public` untouched; it still serves auth and app pages.
- Preserve the existing uncommitted brand/logo and `_hoopflow.scss` work. Do not stage or revert unrelated files.
- All copy and sample data follow `docs/superpowers/specs/2026-11-11-hoopflow-landing-page-design.md`.
- Quality gates before completion:
  ```powershell
  cd src
  npm run build
  ./vendor/bin/pint --test
  ./vendor/bin/phpstan analyse
  php artisan test
  ```

---

## File Structure

**Create:**

| Path | Responsibility |
|---|---|
| `src/app/Http/Controllers/LandingController.php` | Passes showcase data to the home view |
| `src/app/Support/LandingShowcase.php` | Curated tournament, teams, bracket, schedule, standings, copy |
| `src/resources/css/_bootstrap.scss` | Shared Bootstrap SCSS configuration |
| `src/resources/css/landing.scss` | Landing Vite entry: Bootstrap + hoopflow + landing partials |
| `src/resources/css/landing/_base.scss` | Tokens, shell, typography, buttons, reveals |
| `src/resources/css/landing/_header.scss` | Sticky header and mobile menu |
| `src/resources/css/landing/_hero.scss` | Hero, court art, scoreboard scene |
| `src/resources/css/landing/_lab.scss` | Feature lab shell, tabs, stage |
| `src/resources/css/landing/_bracket.scss` | Bracket board and match cards |
| `src/resources/css/landing/_schedule.scss` | Day/court chips and match rows |
| `src/resources/css/landing/_standings.scss` | Standings table, cut line, mobile cards |
| `src/resources/css/landing/_flow.scss` | Five-step matchday timeline |
| `src/resources/css/landing/_roles.scss` | Role cards and vignettes |
| `src/resources/css/landing/_eligibility.scss` | Review and payment cards |
| `src/resources/css/landing/_wire.scss` | Announcement marquee |
| `src/resources/css/landing/_closing.scss` | Closing CTA |
| `src/resources/css/landing/_footer.scss` | Footer |
| `src/resources/js/landing.js` | Landing entry; imports and initializes modules |
| `src/resources/js/landing/header.js` | Sticky state and mobile menu |
| `src/resources/js/landing/reveal.js` | IntersectionObserver reveal helper |
| `src/resources/js/landing/tabs.js` | Accessible demo tabs + footer tab links |
| `src/resources/js/landing/schedule.js` | Day and court filters |
| `src/resources/js/landing/countup.js` | Standings count-up |
| `src/resources/js/landing/glow.js` | Pointer-follow lab glow |
| `src/resources/views/layouts/landing.blade.php` | Landing shell: head, header, main, footer |
| `src/resources/views/landing/hero.blade.php` | Hero section |
| `src/resources/views/landing/lab.blade.php` | Feature lab shell and tab panels |
| `src/resources/views/landing/bracket-panel.blade.php` | Bracket demo |
| `src/resources/views/landing/schedule-panel.blade.php` | Schedule demo |
| `src/resources/views/landing/standings-panel.blade.php` | Standings demo |
| `src/resources/views/landing/flow.blade.php` | Matchday flow |
| `src/resources/views/landing/roles.blade.php` | Role cards |
| `src/resources/views/landing/eligibility.blade.php` | Eligibility + payments |
| `src/resources/views/landing/wire.blade.php` | Announcement ticker |
| `src/resources/views/landing/closing.blade.php` | Closing CTA |
| `src/resources/views/components/landing/crest.blade.php` | Small team crest badge |
| `src/tests/Feature/Pages/LandingTest.php` | Public page, content, links, auth state |
| `src/tests/Unit/Support/LandingShowcaseTest.php` | Curated data integrity |

**Modify:**

| Path | Change |
|---|---|
| `src/routes/web.php` | Point `/` at `LandingController` |
| `src/resources/css/app.scss` | Use shared `_bootstrap.scss` |
| `src/vite.config.js` | Add `landing.scss` and `landing.js` inputs |
| `src/resources/views/pages/home.blade.php` | Replace walking skeleton with landing composition |

---

## Task 1: Curated showcase data

**Files:**
- Create: `src/app/Support/LandingShowcase.php`
- Test: `src/tests/Unit/Support/LandingShowcaseTest.php`

**Interfaces:**
- Produces: `LandingShowcase::tournament(): array`, `teams(): array`, `bracket(): array`, `schedule(): array`, `standings(): array`, `liveGame(): array`, `nextGame(): array`, `announcements(): array`, `flow(): array`, `roles(): array`, `eligibility(): array`.
- Match shape used everywhere: `id`, `round`, `round_key`, `code`, `day`, `day_label`, `time`, `court`, `home`, `away`, `home_label`, `away_label`, `home_score`, `away_score`, `winner`, `status`.

- [ ] **Step 1: Write the failing unit test**

Create `src/tests/Unit/Support/LandingShowcaseTest.php`:

```php
<?php

use App\Support\LandingShowcase;
use Tests\TestCase;

uses(TestCase::class);

it('provides a complete ten-team showcase league', function () {
    $teams = collect((new LandingShowcase())->teams());

    expect($teams)->toHaveCount(10)
        ->and($teams->pluck('id')->unique())->toHaveCount(10)
        ->and($teams->pluck('short')->unique())->toHaveCount(10);
});

it('builds a seven-match bracket from seeds one through eight', function () {
    $showcase = new LandingShowcase();
    $bracket = $showcase->bracket();
    $teams = collect($showcase->teams())->keyBy('id');

    expect($bracket['rounds'])->toHaveCount(3)
        ->and($bracket['rounds'][0]['matches'])->toHaveCount(4)
        ->and($bracket['rounds'][1]['matches'])->toHaveCount(2)
        ->and($bracket['rounds'][2]['matches'])->toHaveCount(1);

    $matches = collect($bracket['rounds'])->flatMap(fn (array $round) => $round['matches']);

    expect($matches)->toHaveCount(7);

    foreach ($matches as $match) {
        foreach (['home', 'away'] as $side) {
            if ($match[$side] !== null) {
                expect($teams->has($match[$side]))->toBeTrue();
            }
        }
    }

    $seeds = $matches
        ->where('round_key', 'quarterfinals')
        ->flatMap(fn (array $match) => [$match['home'], $match['away']])
        ->sort()
        ->values()
        ->all();

    expect($seeds)->toBe([1, 2, 3, 4, 5, 6, 7, 8]);
});

it('keeps completed matches consistent and the live game unfinished', function () {
    $bracket = (new LandingShowcase())->bracket();
    $matches = collect($bracket['rounds'])
        ->flatMap(fn (array $round) => $round['matches'])
        ->keyBy('id');

    foreach ($matches->where('status', 'final') as $match) {
        expect($match['home_score'])->not->toBeNull()
            ->and($match['away_score'])->not->toBeNull()
            ->and($match['winner'])->not->toBeNull();
    }

    $live = $matches->firstWhere('status', 'live');

    expect($live)->not->toBeNull()
        ->and($live['winner'])->toBeNull()
        ->and($live['home_score'])->not->toBeNull()
        ->and($live['away_score'])->not->toBeNull();
});

it('keeps standings balanced and qualifies the top eight', function () {
    $rows = collect((new LandingShowcase())->standings());

    expect($rows)->toHaveCount(10)
        ->and($rows->sum('wins'))->toBe(20)
        ->and($rows->sum('losses'))->toBe(20)
        ->and($rows->sum('point_diff'))->toBe(0)
        ->and($rows->where('qualified', true))->toHaveCount(8)
        ->and($rows->first()['rank'])->toBe(1)
        ->and($rows->last()['rank'])->toBe(10);
});

it('schedules the same seven matches across three days', function () {
    $showcase = new LandingShowcase();
    $scheduledIds = collect($showcase->schedule())
        ->flatMap(fn (array $day) => $day['matches'])
        ->pluck('id')
        ->sort()
        ->values()
        ->all();

    $bracketIds = collect($showcase->bracket()['rounds'])
        ->flatMap(fn (array $round) => $round['matches'])
        ->pluck('id')
        ->sort()
        ->values()
        ->all();

    expect($showcase->schedule())->toHaveCount(3)
        ->and($scheduledIds)->toBe($bracketIds);
});

it('ships non-empty marketing content', function () {
    $showcase = new LandingShowcase();

    expect($showcase->announcements())->not->toBeEmpty()
        ->and($showcase->flow())->toHaveCount(5)
        ->and($showcase->roles())->toHaveCount(3)
        ->and($showcase->eligibility()['players'])->toHaveCount(4)
        ->and($showcase->eligibility()['payments'])->toHaveCount(2);
});
```

- [ ] **Step 2: Run the test and confirm it fails**

Run: `cd src; php artisan test tests/Unit/Support/LandingShowcaseTest.php`

Expected: FAIL with `Class "App\Support\LandingShowcase" not found`.

- [ ] **Step 3: Create the showcase class**

Create `src/app/Support/LandingShowcase.php`:

```php
<?php

namespace App\Support;

use LogicException;

final class LandingShowcase
{
    /**
     * @return array{name: string, division: string, venue: string, dates: string, format: string, courts: list<string>}
     */
    public function tournament(): array
    {
        return [
            'name' => 'San Isidro Cup 2026',
            'division' => 'U16 Boys',
            'venue' => 'San Isidro Covered Court',
            'dates' => 'Nov 14–22, 2026',
            'format' => 'Single elimination · Top 8 of 10',
            'courts' => ['Court A', 'Court B'],
        ];
    }

    /**
     * @return list<array{id: int, name: string, short: string, color: string}>
     */
    public function teams(): array
    {
        return [
            ['id' => 1, 'name' => 'San Isidro Vipers', 'short' => 'SIV', 'color' => '#0f766e'],
            ['id' => 2, 'name' => 'Poblacion Panthers', 'short' => 'POB', 'color' => '#1d4ed8'],
            ['id' => 3, 'name' => 'Sta. Cruz Saints', 'short' => 'SCS', 'color' => '#b91c1c'],
            ['id' => 4, 'name' => 'Lakeside Lions', 'short' => 'LAK', 'color' => '#b45309'],
            ['id' => 5, 'name' => 'Mabini Mavericks', 'short' => 'MAB', 'color' => '#0e7490'],
            ['id' => 6, 'name' => 'Bagong Silang Bolts', 'short' => 'BSB', 'color' => '#7c3aed'],
            ['id' => 7, 'name' => 'Riverside Ravens', 'short' => 'RIV', 'color' => '#4d7c0f'],
            ['id' => 8, 'name' => 'Northside Ninos', 'short' => 'NOR', 'color' => '#be123c'],
            ['id' => 9, 'name' => 'Hilltop Hawks', 'short' => 'HIL', 'color' => '#475569'],
            ['id' => 10, 'name' => 'Bayanihan Ballers', 'short' => 'BAY', 'color' => '#a16207'],
        ];
    }

    /**
     * @return array{rounds: list<array{key: string, label: string, matches: list<array<string, mixed>>}>}
     */
    public function bracket(): array
    {
        $matches = $this->matches();

        $round = fn (string $key, string $label): array => [
            'key' => $key,
            'label' => $label,
            'matches' => array_values(array_filter(
                $matches,
                fn (array $match): bool => $match['round_key'] === $key,
            )),
        ];

        return [
            'rounds' => [
                $round('quarterfinals', 'Quarterfinals'),
                $round('semifinals', 'Semifinals'),
                $round('final', 'Final'),
            ],
        ];
    }

    /**
     * @return list<array{key: string, label: string, short: string, subtitle: string, matches: list<array<string, mixed>>}>
     */
    public function schedule(): array
    {
        $matches = $this->matches();
        $days = [
            ['key' => 'day1', 'label' => 'Sat, Nov 14', 'short' => 'Nov 14', 'subtitle' => 'Quarterfinals', 'matches' => []],
            ['key' => 'day2', 'label' => 'Sat, Nov 21', 'short' => 'Nov 21', 'subtitle' => 'Semifinals', 'matches' => []],
            ['key' => 'day3', 'label' => 'Sun, Nov 22', 'short' => 'Nov 22', 'subtitle' => 'Final', 'matches' => []],
        ];

        foreach ($days as &$day) {
            $day['matches'] = array_values(array_filter(
                $matches,
                fn (array $match): bool => $match['day'] === $day['key'],
            ));
        }
        unset($day);

        return $days;
    }

    /**
     * @return list<array{rank: int, team_id: int, played: int, wins: int, losses: int, points_for: int, points_against: int, point_diff: int, seed: int|null, qualified: bool}>
     */
    public function standings(): array
    {
        $rows = [
            [1, 1, 4, 0, 302, 224, 78, 1, true],
            [2, 2, 3, 1, 288, 236, 52, 2, true],
            [3, 3, 3, 1, 274, 243, 31, 3, true],
            [4, 4, 3, 1, 261, 243, 18, 4, true],
            [5, 5, 2, 2, 249, 247, 2, 5, true],
            [6, 6, 2, 2, 238, 245, -7, 6, true],
            [7, 7, 1, 3, 231, 253, -22, 7, true],
            [8, 8, 1, 3, 226, 261, -35, 8, true],
            [9, 9, 1, 3, 219, 265, -46, null, false],
            [10, 10, 0, 4, 204, 275, -71, null, false],
        ];

        return array_map(fn (array $row): array => [
            'rank' => $row[0],
            'team_id' => $row[1],
            'wins' => $row[2],
            'losses' => $row[3],
            'points_for' => $row[4],
            'points_against' => $row[5],
            'point_diff' => $row[6],
            'seed' => $row[7],
            'qualified' => $row[8],
            'played' => $row[2] + $row[3],
        ], $rows);
    }

    /**
     * @return array<string, mixed>
     */
    public function liveGame(): array
    {
        return $this->match('qf4');
    }

    /**
     * @return array<string, mixed>
     */
    public function nextGame(): array
    {
        return $this->match('sf1');
    }

    /**
     * @return list<string>
     */
    public function announcements(): array
    {
        return [
            'QF4 is live on Court B — Panthers lead Ravens, 66–50 in the fourth.',
            'Semifinal tip-off moved to 1:00 PM. Arrive 30 minutes early for warm-ups.',
            'U16 roster lock is Friday, 6:00 PM. No additions after the first semifinal.',
            'Vipers advance after a 58–44 win over the Ninos.',
            'Entry-fee payments close before the semifinals.',
            'Scorekeepers: quarter stats are now recorded per player.',
        ];
    }

    /**
     * @return list<array{title: string, copy: string, tag: string, icon: string}>
     */
    public function flow(): array
    {
        return [
            [
                'title' => 'Set the stage',
                'copy' => 'Create the tournament, divisions, venue, courts, and entry fee.',
                'tag' => 'One setup',
                'icon' => 'calendar-plus',
            ],
            [
                'title' => 'Register & vet',
                'copy' => 'Rosters, birthdates, guardian consent, documents, and payment references.',
                'tag' => 'Eligibility queue',
                'icon' => 'clipboard-check',
            ],
            [
                'title' => 'Seed the bracket',
                'copy' => 'Approved teams are seeded and published, with byes handled.',
                'tag' => 'Auto-seeded',
                'icon' => 'diagram-3',
            ],
            [
                'title' => 'Schedule the floor',
                'copy' => 'Courts, rest periods, and feeder matches resolve into one schedule.',
                'tag' => 'Conflict checks',
                'icon' => 'calendar3',
            ],
            [
                'title' => 'Run the night',
                'copy' => 'Final scores advance winners, update standings, and notify the bleachers.',
                'tag' => 'Live standings',
                'icon' => 'broadcast',
            ],
        ];
    }

    /**
     * @return list<array{key: string, eyebrow: string, title: string, copy: string, accent: string}>
     */
    public function roles(): array
    {
        return [
            [
                'key' => 'organizer',
                'eyebrow' => 'Organizer',
                'title' => 'See the whole floor.',
                'copy' => 'Eligibility, payments, brackets, schedule, and audit — the whole tournament in one place.',
                'accent' => '#ea580c',
            ],
            [
                'key' => 'coach',
                'eyebrow' => 'Coach',
                'title' => 'Know before tip-off.',
                'copy' => 'Register a team, track requirements, and see the next game without chasing a group chat.',
                'accent' => '#0f766e',
            ],
            [
                'key' => 'fan',
                'eyebrow' => 'Fan / Parent',
                'title' => 'Follow from the bleachers.',
                'copy' => 'Bracket, schedule, standings, and announcements — readable on the phone in your pocket.',
                'accent' => '#1d4ed8',
            ],
        ];
    }

    /**
     * @return array{players: list<array{name: string, age: int, status: string, label: string}>, payments: list<array{label: string, amount: string, method: string, reference: string, status: string, status_label: string}>}
     */
    public function eligibility(): array
    {
        return [
            'players' => [
                ['name' => 'J. Santos', 'age' => 15, 'status' => 'verified', 'label' => 'Verified'],
                ['name' => 'M. Reyes', 'age' => 15, 'status' => 'submitted', 'label' => 'Consent pending'],
                ['name' => 'A. Cruz', 'age' => 16, 'status' => 'missing', 'label' => 'Document missing'],
                ['name' => 'K. Dela Cruz', 'age' => 14, 'status' => 'verified', 'label' => 'Verified'],
            ],
            'payments' => [
                [
                    'label' => 'Entry fee',
                    'amount' => '₱1,500',
                    'method' => 'GCash',
                    'reference' => 'Ref 0928 441 773',
                    'status' => 'paid',
                    'status_label' => 'Received',
                ],
                [
                    'label' => 'Jersey deposit',
                    'amount' => '₱500',
                    'method' => 'Cash',
                    'reference' => 'Recorded by organizer',
                    'status' => 'paid',
                    'status_label' => 'Recorded',
                ],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function matches(): array
    {
        return [
            [
                'id' => 'qf1', 'round' => 'Quarterfinals', 'round_key' => 'quarterfinals', 'code' => 'QF1',
                'day' => 'day1', 'day_label' => 'Sat, Nov 14', 'time' => '8:00 AM', 'court' => 'Court A',
                'home' => 1, 'away' => 8, 'home_label' => null, 'away_label' => null,
                'home_score' => 58, 'away_score' => 44, 'winner' => 1, 'status' => 'final',
            ],
            [
                'id' => 'qf2', 'round' => 'Quarterfinals', 'round_key' => 'quarterfinals', 'code' => 'QF2',
                'day' => 'day1', 'day_label' => 'Sat, Nov 14', 'time' => '9:30 AM', 'court' => 'Court A',
                'home' => 4, 'away' => 5, 'home_label' => null, 'away_label' => null,
                'home_score' => 49, 'away_score' => 52, 'winner' => 5, 'status' => 'final',
            ],
            [
                'id' => 'qf3', 'round' => 'Quarterfinals', 'round_key' => 'quarterfinals', 'code' => 'QF3',
                'day' => 'day1', 'day_label' => 'Sat, Nov 14', 'time' => '11:00 AM', 'court' => 'Court B',
                'home' => 3, 'away' => 6, 'home_label' => null, 'away_label' => null,
                'home_score' => 61, 'away_score' => 57, 'winner' => 3, 'status' => 'final',
            ],
            [
                'id' => 'qf4', 'round' => 'Quarterfinals', 'round_key' => 'quarterfinals', 'code' => 'QF4',
                'day' => 'day1', 'day_label' => 'Sat, Nov 14', 'time' => '1:00 PM', 'court' => 'Court B',
                'home' => 2, 'away' => 7, 'home_label' => null, 'away_label' => null,
                'home_score' => 66, 'away_score' => 50, 'winner' => null, 'status' => 'live',
            ],
            [
                'id' => 'sf1', 'round' => 'Semifinals', 'round_key' => 'semifinals', 'code' => 'SF1',
                'day' => 'day2', 'day_label' => 'Sat, Nov 21', 'time' => '1:00 PM', 'court' => 'Court A',
                'home' => 1, 'away' => 5, 'home_label' => null, 'away_label' => null,
                'home_score' => null, 'away_score' => null, 'winner' => null, 'status' => 'scheduled',
            ],
            [
                'id' => 'sf2', 'round' => 'Semifinals', 'round_key' => 'semifinals', 'code' => 'SF2',
                'day' => 'day2', 'day_label' => 'Sat, Nov 21', 'time' => '2:30 PM', 'court' => 'Court A',
                'home' => 3, 'away' => null, 'home_label' => null, 'away_label' => 'Winner QF4',
                'home_score' => null, 'away_score' => null, 'winner' => null, 'status' => 'scheduled',
            ],
            [
                'id' => 'final', 'round' => 'Final', 'round_key' => 'final', 'code' => 'F',
                'day' => 'day3', 'day_label' => 'Sun, Nov 22', 'time' => '4:00 PM', 'court' => 'Court A',
                'home' => null, 'away' => null, 'home_label' => 'Winner SF1', 'away_label' => 'Winner SF2',
                'home_score' => null, 'away_score' => null, 'winner' => null, 'status' => 'scheduled',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function match(string $id): array
    {
        foreach ($this->matches() as $match) {
            if ($match['id'] === $id) {
                return $match;
            }
        }

        throw new LogicException("Unknown showcase match [{$id}].");
    }
}
```

- [ ] **Step 4: Run the unit test and confirm it passes**

Run: `cd src; php artisan test tests/Unit/Support/LandingShowcaseTest.php`

Expected: PASS, 6 tests.

- [ ] **Step 5: Format and commit**

```powershell
cd src
./vendor/bin/pint app/Support/LandingShowcase.php tests/Unit/Support/LandingShowcaseTest.php
git add app/Support/LandingShowcase.php tests/Unit/Support/LandingShowcaseTest.php
git commit -m "feat(landing): add curated showcase data"
```

## Task 2: Landing foundation, header, and hero

**Files:**
- Create: `src/app/Http/Controllers/LandingController.php`
- Create: `src/resources/views/layouts/landing.blade.php`
- Create: `src/resources/views/landing/hero.blade.php`
- Create: `src/resources/views/components/landing/crest.blade.php`
- Create: `src/resources/css/_bootstrap.scss`
- Create: `src/resources/css/landing.scss`
- Create: `src/resources/css/landing/_base.scss`
- Create: `src/resources/css/landing/_header.scss`
- Create: `src/resources/css/landing/_hero.scss`
- Create: `src/resources/js/landing.js`
- Create: `src/resources/js/landing/header.js`
- Create: `src/resources/js/landing/reveal.js`
- Modify: `src/routes/web.php:30`
- Modify: `src/resources/css/app.scss`
- Modify: `src/vite.config.js`
- Modify: `src/resources/views/pages/home.blade.php` (replace)
- Test: `src/tests/Feature/Pages/LandingTest.php`

**Interfaces:**
- Consumes: `LandingShowcase` from Task 1.
- Produces: `layouts.landing` shell, `.hf-lp-*` base classes, `initHeader()`, `initReveal()`, and the `x-landing.crest` component (`team`, `label`, `size`).

- [ ] **Step 1: Write the failing feature test**

Create `src/tests/Feature/Pages/LandingTest.php`:

```php
<?php

use App\Models\User;

it('renders the public landing page with its hero', function () {
    $this->get('/')
        ->assertOk()
        ->assertSeeText('Run the league, not the spreadsheet.')
        ->assertSeeText('Barangay & youth basketball')
        ->assertSeeText('Poblacion Panthers')
        ->assertSee('data-testid="landing-hero"', false)
        ->assertSee('data-theme-toggle', false)
        ->assertSee(route('login'), false)
        ->assertSee(route('register'), false);
});

it('shows a dashboard link instead of auth calls to action when signed in', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/')
        ->assertOk()
        ->assertSee('Dashboard')
        ->assertDontSee(route('login'), false);
});
```

- [ ] **Step 2: Run the test and confirm it fails**

Run: `cd src; php artisan test tests/Feature/Pages/LandingTest.php`

Expected: FAIL (the current walking-skeleton page has no hero).

- [ ] **Step 3: Add the controller and route**

Create `src/app/Http/Controllers/LandingController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Support\LandingShowcase;
use Illuminate\Contracts\View\View;

final class LandingController extends Controller
{
    public function __invoke(LandingShowcase $showcase): View
    {
        return view('pages.home', ['showcase' => $showcase]);
    }
}
```

In `src/routes/web.php`, add the import and replace the home route:

```php
use App\Http\Controllers\LandingController;
```

```php
Route::get('/', LandingController::class)->name('home');
```

- [ ] **Step 4: Split the Bootstrap configuration and add landing entries**

Create `src/resources/css/_bootstrap.scss` with the existing configuration:

```scss
@use "bootstrap/scss/bootstrap" with (
  $primary: #ea580c,
  $secondary: #64748b,
  $success: #10b981,
  $danger: #ef4444,
  $warning: #f59e0b,
  $info: #0284c7,
  $body-bg: #f8fafc,
  $body-color: #0f172a,
  $font-family-sans-serif: ("Inter", system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif),
  $border-radius: 0.65rem,
  $border-radius-sm: 0.5rem,
  $border-radius-lg: 1rem,
  $enable-shadows: false,
  $card-border-color: #e2e8f0
);
```

Replace the Bootstrap block in `src/resources/css/app.scss` with:

```scss
@use "bootstrap";
@use "hoopflow";
```

Create `src/resources/css/landing.scss`:

```scss
@use "bootstrap";
@use "hoopflow";
@use "landing/base";
@use "landing/header";
@use "landing/hero";
```

Update `src/vite.config.js` inputs:

```js
input: [
    'resources/css/app.scss',
    'resources/css/landing.scss',
    'resources/js/app.js',
    'resources/js/landing.js',
],
```

- [ ] **Step 5: Create the landing layout**

Create `src/resources/views/layouts/landing.blade.php`. Reuse the existing theme bootstrap script from `layouts.public` verbatim, add `document.documentElement.classList.add('hf-js')`, and load the landing entries:

```blade
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name'))</title>
    <meta name="description" content="HoopFlow keeps registration, eligibility, brackets, schedules, and standings in one place for barangay and grassroots youth basketball.">

    <link rel="icon" type="image/png" href="{{ asset('img/hoopflow icon without background.png') }}">

    <script>
        (function () {
            try {
                var t = localStorage.getItem('hf-theme');
                var next = (t === 'dark' || t === 'light') ? t : 'light';
                document.documentElement.setAttribute('data-theme', next);
                document.documentElement.style.colorScheme = next;
            } catch (e) {
                document.documentElement.setAttribute('data-theme', 'light');
            }
            document.documentElement.classList.add('hf-js');
        })();
    </script>

    <link rel="stylesheet" href="{{ asset('fonts/fonts.css') }}">
    @vite(['resources/css/landing.scss', 'resources/js/app.js', 'resources/js/landing.js'])
</head>
<body class="hf-lp">
    <a class="visually-hidden-focusable" href="#main">Skip to content</a>

    <header class="hf-lp-header" data-landing-header>
        <div class="hf-lp-shell hf-lp-header__inner">
            <a class="hf-lp-header__brand" href="{{ route('home') }}" aria-label="HoopFlow home">
                <x-brand size="sm" />
            </a>

            <nav class="hf-lp-nav" aria-label="Landing">
                <a href="#features">Features</a>
                <a href="#flow">Matchday</a>
                <a href="#roles">Roles</a>
                <a href="#eligibility">Eligibility</a>
            </nav>

            <div class="hf-lp-header__actions">
                <button type="button" class="hf-icon-btn" data-theme-toggle aria-label="Toggle color theme">
                    <i class="bi bi-moon-stars" aria-hidden="true"></i>
                </button>
                @auth
                    <a class="hf-btn hf-btn--primary hf-btn--sm" href="{{ route('dashboard') }}">Dashboard</a>
                @else
                    <a class="hf-btn hf-btn--ghost hf-btn--sm hf-lp-hide-sm" href="{{ route('login') }}">Sign in</a>
                    <a class="hf-btn hf-btn--primary hf-btn--sm" href="{{ route('register') }}">Create account</a>
                @endauth
                <button type="button" class="hf-lp-menu-btn" data-landing-menu aria-expanded="false" aria-controls="landing-menu" aria-label="Open menu">
                    <i class="bi bi-list" aria-hidden="true"></i>
                </button>
            </div>
        </div>

        <div class="hf-lp-mobile" id="landing-menu" hidden>
            <nav class="hf-lp-mobile__nav" aria-label="Mobile landing">
                <a href="#features">Features</a>
                <a href="#flow">Matchday</a>
                <a href="#roles">Roles</a>
                <a href="#eligibility">Eligibility</a>
            </nav>
            <div class="hf-lp-mobile__actions">
                @auth
                    <a class="hf-btn hf-btn--primary" href="{{ route('dashboard') }}">Dashboard</a>
                @else
                    <a class="hf-btn hf-btn--ghost" href="{{ route('login') }}">Sign in</a>
                    <a class="hf-btn hf-btn--primary" href="{{ route('register') }}">Create account</a>
                @endauth
            </div>
        </div>
    </header>

    <main id="main" tabindex="-1">
        @yield('content')
    </main>
</body>
</html>
```

- [ ] **Step 6: Create the crest component**

Create `src/resources/views/components/landing/crest.blade.php`:

```blade
@props(['team' => null, 'label' => null, 'size' => 'sm'])

@php
    $short = $team['short'] ?? $label ?? 'TBD';
    $color = $team['color'] ?? '#64748b';
@endphp

<span
    {{ $attributes->class(['hf-lp-crest', 'hf-lp-crest--'.$size]) }}
    style="--hf-crest: {{ $color }}"
    aria-hidden="true"
>{{ $short }}</span>
```

- [ ] **Step 7: Create the hero**

Replace `src/resources/views/pages/home.blade.php`:

```blade
@extends('layouts.landing')

@section('title', 'HoopFlow — Run the league, not the spreadsheet')

@section('content')
    @php
        $teams = collect($showcase->teams())->keyBy('id');
    @endphp

    @include('landing.hero', [
        'tournament' => $showcase->tournament(),
        'live' => $showcase->liveGame(),
        'next' => $showcase->nextGame(),
        'teams' => $teams,
    ])
@endsection
```

Create `src/resources/views/landing/hero.blade.php`:

```blade
@php
    $liveHome = $live['home'] ? $teams->get($live['home']) : null;
    $liveAway = $live['away'] ? $teams->get($live['away']) : null;
    $nextHome = $next['home'] ? $teams->get($next['home']) : null;
    $nextAway = $next['away'] ? $teams->get($next['away']) : null;
@endphp

<section class="hf-lp-hero" data-testid="landing-hero" aria-labelledby="landing-hero-title">
    <div class="hf-lp-hero__art" aria-hidden="true">
        <svg class="hf-lp-court" viewBox="0 0 900 900" fill="none">
            <rect x="40" y="40" width="820" height="820" rx="16" />
            <path d="M450 40v820M40 450h820" />
            <circle cx="450" cy="450" r="118" />
            <circle cx="450" cy="450" r="300" />
            <path d="M40 260h176v380H40M860 260H684v380h176" />
        </svg>
        <span class="hf-lp-ball"></span>
    </div>

    <div class="hf-lp-shell hf-lp-hero__inner">
        <div class="hf-lp-hero__copy">
            <span class="hf-eyebrow hf-lp-eyebrow">
                <span class="hf-lp-ping" aria-hidden="true"></span>
                Barangay &amp; youth basketball
            </span>

            <h1 id="landing-hero-title" class="hf-lp-hero__title">
                <span>Run the league,</span>
                <span>not the spreadsheet.</span>
            </h1>

            <p class="hf-lp-hero__lede">
                Registration, eligibility, brackets, schedules, and standings — one clean flow,
                built for the covered court.
            </p>

            <div class="hf-lp-hero__actions">
                <a class="hf-btn hf-btn--primary hf-lp-btn-lg" href="{{ route('register') }}">
                    Create your account
                    <i class="bi bi-arrow-up-right" aria-hidden="true"></i>
                </a>
                <a class="hf-btn hf-btn--ghost hf-lp-btn-lg" href="#features">
                    Explore the demo
                    <i class="bi bi-arrow-down" aria-hidden="true"></i>
                </a>
            </div>

            <p class="hf-lp-hero__micro">
                Built for organizers, coaches, and the parents on the bleachers.
            </p>
        </div>

        <div class="hf-lp-hero__scene" aria-label="Sample live scoreboard">
            <article class="hf-lp-scoreboard">
                <div class="hf-lp-scoreboard__top">
                    <span class="hf-lp-scoreboard__tournament">{{ $tournament['name'] }}</span>
                    <span class="hf-lp-live"><span class="hf-lp-live__dot" aria-hidden="true"></span> Live</span>
                </div>
                <p class="hf-lp-scoreboard__meta">
                    {{ $tournament['division'] }} · {{ $live['round'] }} · {{ $live['court'] }}
                </p>
                <div class="hf-lp-scoreboard__clock">
                    <span>Q4</span>
                    <strong>02:14</strong>
                </div>
                <div class="hf-lp-scoreboard__teams">
                    <div class="hf-lp-scoreboard__team is-leading">
                        <x-landing.crest :team="$liveHome" size="md" />
                        <span class="hf-lp-scoreboard__name">{{ $liveHome['name'] ?? 'TBD' }}</span>
                        <strong class="hf-lp-scoreboard__score">{{ $live['home_score'] ?? '–' }}</strong>
                    </div>
                    <div class="hf-lp-scoreboard__team">
                        <x-landing.crest :team="$liveAway" size="md" />
                        <span class="hf-lp-scoreboard__name">{{ $liveAway['name'] ?? 'TBD' }}</span>
                        <strong class="hf-lp-scoreboard__score">{{ $live['away_score'] ?? '–' }}</strong>
                    </div>
                </div>
                <div class="hf-lp-scoreboard__foot">
                    <span>{{ $live['code'] }} · {{ $live['day_label'] }}</span>
                    <span>Game 4 of 7</span>
                </div>
            </article>

            <article class="hf-lp-nextup">
                <span class="hf-lp-nextup__eyebrow">Next up · {{ $next['round'] }}</span>
                <strong>{{ $next['day_label'] }} · {{ $next['time'] }}</strong>
                <span class="hf-lp-nextup__teams">
                    {{ $nextHome['name'] ?? 'TBD' }} <span>vs</span> {{ $nextAway['name'] ?? $next['away_label'] ?? 'TBD' }}
                </span>
                <span class="hf-lp-nextup__court">{{ $next['court'] }}</span>
            </article>

            <span class="hf-lp-stamp" aria-hidden="true">
                <span>Matchday</span>
                <strong>10 teams · 2 courts</strong>
            </span>
        </div>
    </div>

    <a class="hf-lp-scroll" href="#features">
        See it in motion
        <i class="bi bi-arrow-down" aria-hidden="true"></i>
    </a>
</section>
```

- [ ] **Step 8: Write the base, header, and hero styles**

Create `src/resources/css/landing/_base.scss` with the tokens and shell:

```scss
.hf-lp {
  --hf-lp-paper: #f4f0e6;
  --hf-lp-paper-2: #ece5d6;
  --hf-lp-card: #fffdf8;
  --hf-lp-ink: #101820;
  --hf-lp-muted: #5f665f;
  --hf-lp-line: rgba(16, 24, 32, 0.14);
  --hf-lp-orange: #ea580c;
  --hf-lp-orange-ink: #9a3412;
  --hf-lp-gold: #b77900;
  --hf-lp-green: #0e7a5f;
  --hf-lp-red: #c0263d;
  --hf-lp-arena: #0b1220;
  --hf-lp-arena-2: #111b2a;
  --hf-lp-arena-ink: #f3f5f9;
  --hf-lp-arena-muted: #9aa6b8;
  --hf-lp-display: 'Barlow Condensed', 'Arial Narrow', sans-serif;
  --hf-lp-body: 'Inter', system-ui, -apple-system, 'Segoe UI', sans-serif;
  --hf-lp-radius: 18px;
  --hf-lp-radius-sm: 12px;
  --hf-lp-shadow: 0 24px 60px rgba(11, 18, 32, 0.14);
  --hf-lp-ease: cubic-bezier(0.22, 1, 0.36, 1);
  background: var(--hf-lp-paper);
  color: var(--hf-lp-ink);
  font-family: var(--hf-lp-body);
}

[data-theme='dark'] .hf-lp {
  --hf-lp-paper: #0c1017;
  --hf-lp-paper-2: #121925;
  --hf-lp-card: #161e2b;
  --hf-lp-ink: #f3f5f9;
  --hf-lp-muted: #a4acb9;
  --hf-lp-line: rgba(243, 245, 249, 0.14);
  --hf-lp-orange-ink: #fdba74;
  --hf-lp-gold: #f5b301;
  --hf-lp-green: #34d399;
  --hf-lp-red: #ff5c7a;
  --hf-lp-shadow: 0 24px 70px rgba(0, 0, 0, 0.35);
}

.hf-lp-shell {
  width: min(1240px, calc(100% - 40px));
  margin-inline: auto;
}

.hf-lp section[id] {
  scroll-margin-top: 96px;
}

.hf-lp-eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  color: var(--hf-lp-orange-ink);
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.16em;
  text-transform: uppercase;
}

.hf-lp-section-head {
  max-width: 760px;
  margin-bottom: clamp(32px, 5vw, 56px);
}

.hf-lp-section-head h2 {
  margin: 10px 0 14px;
  font-family: var(--hf-lp-display);
  font-size: clamp(42px, 6vw, 76px);
  font-weight: 800;
  letter-spacing: -0.02em;
  line-height: 0.9;
  text-transform: uppercase;
}

.hf-lp-section-head p {
  max-width: 62ch;
  margin: 0;
  color: var(--hf-lp-muted);
  font-size: clamp(15px, 1.4vw, 18px);
  line-height: 1.6;
}

.hf-lp-btn-lg {
  min-height: 52px;
  padding-inline: 22px;
  font-size: 15px;
}

.hf-lp-reveal {
  --i: 0;
}

.hf-js .hf-lp-reveal {
  opacity: 0;
  transform: translateY(24px);
  transition:
    opacity 0.7s var(--hf-lp-ease),
    transform 0.7s var(--hf-lp-ease);
  transition-delay: calc(var(--i) * 70ms);
}

.hf-js .hf-lp-reveal.is-in {
  opacity: 1;
  transform: none;
}

.hf-lp-crest {
  display: inline-grid;
  place-items: center;
  flex: none;
  width: 26px;
  height: 26px;
  border: 1px solid color-mix(in srgb, var(--hf-crest) 70%, white);
  border-radius: 8px;
  background: color-mix(in srgb, var(--hf-crest) 18%, transparent);
  color: var(--hf-crest);
  font-family: var(--hf-lp-display);
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.04em;
}

.hf-lp-crest--md {
  width: 34px;
  height: 34px;
  border-radius: 10px;
  font-size: 12px;
}

.hf-lp-ping {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--hf-lp-orange);
  box-shadow: 0 0 0 0 color-mix(in srgb, var(--hf-lp-orange) 50%, transparent);
  animation: hf-lp-ping 2.4s ease-out infinite;
}

@keyframes hf-lp-ping {
  0% { box-shadow: 0 0 0 0 color-mix(in srgb, var(--hf-lp-orange) 45%, transparent); }
  70% { box-shadow: 0 0 0 10px transparent; }
  100% { box-shadow: 0 0 0 0 transparent; }
}

.hf-lp-live {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 8px;
  border-radius: 999px;
  background: color-mix(in srgb, var(--hf-lp-orange) 18%, transparent);
  color: #fdba74;
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.12em;
  text-transform: uppercase;
}

.hf-lp-live__dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: var(--hf-lp-orange);
  animation: hf-lp-blink 1.6s ease-in-out infinite;
}

@keyframes hf-lp-blink {
  50% { opacity: 0.35; }
}

@media (max-width: 767.98px) {
  .hf-lp-shell {
    width: min(1240px, calc(100% - 28px));
  }

  .hf-lp-hide-sm {
    display: none !important;
  }
}

@media (prefers-reduced-motion: reduce) {
  .hf-lp-ping,
  .hf-lp-live__dot {
    animation: none;
  }
}
```

Create `src/resources/css/landing/_header.scss`:

```scss
.hf-lp-header {
  position: sticky;
  top: 0;
  z-index: 50;
  transition:
    background-color 0.25s ease,
    box-shadow 0.25s ease,
    border-color 0.25s ease;
  border-bottom: 1px solid transparent;
}

.hf-lp-header.is-scrolled {
  border-bottom-color: var(--hf-lp-line);
  background: color-mix(in srgb, var(--hf-lp-paper) 84%, transparent);
  box-shadow: 0 12px 30px rgba(11, 18, 32, 0.06);
  backdrop-filter: blur(16px);
}

.hf-lp-header__inner {
  display: flex;
  align-items: center;
  gap: 24px;
  min-height: 76px;
}

.hf-lp-header__brand {
  display: inline-flex;
  align-items: center;
  text-decoration: none;
}

.hf-lp-nav {
  display: flex;
  align-items: center;
  gap: 26px;
  margin-inline: auto;
}

.hf-lp-nav a {
  color: var(--hf-lp-muted);
  font-size: 13px;
  font-weight: 600;
  text-decoration: none;
  transition: color 0.2s ease;
}

.hf-lp-nav a:hover {
  color: var(--hf-lp-ink);
  text-decoration: none;
}

.hf-lp-header__actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

.hf-lp-menu-btn {
  display: none;
  width: 44px;
  height: 44px;
  border: 1px solid var(--hf-lp-line);
  border-radius: 10px;
  background: var(--hf-lp-card);
  color: var(--hf-lp-ink);
  font-size: 20px;
  cursor: pointer;
}

.hf-lp-mobile {
  border-top: 1px solid var(--hf-lp-line);
  background: var(--hf-lp-paper);
  padding: 12px 20px 20px;
}

.hf-lp-mobile__nav {
  display: grid;
  gap: 4px;
  margin-bottom: 12px;
}

.hf-lp-mobile__nav a {
  padding: 12px 8px;
  border-radius: 8px;
  color: var(--hf-lp-ink);
  font-weight: 600;
  text-decoration: none;
}

.hf-lp-mobile__nav a:hover {
  background: var(--hf-lp-paper-2);
  text-decoration: none;
}

.hf-lp-mobile__actions {
  display: grid;
  gap: 8px;
}

@media (max-width: 991.98px) {
  .hf-lp-nav {
    display: none;
  }

  .hf-lp-menu-btn {
    display: inline-grid;
    place-items: center;
  }
}

@media (min-width: 992px) {
  .hf-lp-mobile {
    display: none !important;
  }
}
```

Create `src/resources/css/landing/_hero.scss` with these exact anchors and behaviors:

```scss
.hf-lp-hero {
  position: relative;
  overflow: hidden;
  padding: clamp(64px, 8vw, 104px) 0 clamp(72px, 9vw, 120px);
  background:
    radial-gradient(circle at 78% 22%, color-mix(in srgb, var(--hf-lp-orange) 16%, transparent), transparent 34%),
    var(--hf-lp-paper);
}

.hf-lp-hero__art {
  position: absolute;
  inset: 0;
  pointer-events: none;
  color: var(--hf-lp-line);
}

.hf-lp-court {
  position: absolute;
  top: -18%;
  right: -12%;
  width: min(860px, 75vw);
  opacity: 0.55;
  stroke: currentColor;
  stroke-width: 1.5;
}

.hf-lp-ball {
  position: absolute;
  top: 12%;
  right: -80px;
  width: min(520px, 48vw);
  aspect-ratio: 1;
  border-radius: 50%;
  background-image: radial-gradient(circle, color-mix(in srgb, var(--hf-lp-orange) 42%, transparent) 1.5px, transparent 1.7px);
  background-size: 12px 12px;
  mask-image: radial-gradient(circle, #000 54%, transparent 70%);
  transform: rotate(-12deg);
  opacity: 0.55;
}

.hf-lp-hero__inner {
  position: relative;
  z-index: 1;
  display: grid;
  grid-template-columns: minmax(0, 1.05fr) minmax(380px, 0.95fr);
  gap: clamp(32px, 5vw, 72px);
  align-items: center;
}

.hf-lp-hero__title {
  margin: 18px 0 22px;
  font-family: var(--hf-lp-display);
  font-size: clamp(56px, 8.4vw, 118px);
  font-weight: 800;
  letter-spacing: -0.025em;
  line-height: 0.84;
  text-transform: uppercase;
}

.hf-lp-hero__title span {
  display: block;
}

.hf-lp-hero__title span:last-child {
  color: var(--hf-lp-orange-ink);
}

.hf-lp-hero__lede {
  max-width: 56ch;
  margin: 0 0 28px;
  color: var(--hf-lp-muted);
  font-size: clamp(16px, 1.5vw, 19px);
  line-height: 1.6;
}

.hf-lp-hero__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.hf-lp-hero__micro {
  margin: 18px 0 0;
  color: var(--hf-lp-muted);
  font-size: 12px;
}

.hf-lp-hero__scene {
  position: relative;
  min-height: 470px;
}

.hf-lp-scoreboard {
  position: relative;
  z-index: 2;
  width: min(100%, 390px);
  margin-left: auto;
  padding: 22px;
  border: 1px solid rgba(243, 245, 249, 0.16);
  border-radius: 20px;
  background:
    radial-gradient(circle at 100% 0%, rgba(234, 88, 12, 0.22), transparent 42%),
    var(--hf-lp-arena);
  box-shadow: 0 30px 70px rgba(11, 18, 32, 0.3);
  color: var(--hf-lp-arena-ink);
  transform: rotate(-2deg);
  animation: hf-lp-float 7s ease-in-out infinite;
}

.hf-lp-scoreboard__top,
.hf-lp-scoreboard__foot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.hf-lp-scoreboard__tournament {
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.12em;
  text-transform: uppercase;
}

.hf-lp-scoreboard__meta {
  margin: 6px 0 20px;
  color: var(--hf-lp-arena-muted);
  font-size: 11px;
}

.hf-lp-scoreboard__clock {
  display: flex;
  align-items: baseline;
  gap: 8px;
  margin-bottom: 14px;
  color: var(--hf-lp-arena-muted);
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.12em;
  text-transform: uppercase;
}

.hf-lp-scoreboard__clock strong {
  color: var(--hf-lp-arena-ink);
  font-family: var(--hf-lp-display);
  font-size: 42px;
  line-height: 1;
}

.hf-lp-scoreboard__teams {
  display: grid;
  gap: 8px;
  padding: 12px 0;
  border-block: 1px solid rgba(243, 245, 249, 0.12);
}

.hf-lp-scoreboard__team {
  display: flex;
  align-items: center;
  gap: 10px;
  min-height: 44px;
}

.hf-lp-scoreboard__team.is-leading {
  color: #fdba74;
}

.hf-lp-scoreboard__name {
  flex: 1;
  font-size: 14px;
  font-weight: 600;
}

.hf-lp-scoreboard__score {
  font-family: var(--hf-lp-display);
  font-size: 34px;
  font-weight: 800;
  line-height: 1;
}

.hf-lp-scoreboard__foot {
  padding-top: 14px;
  color: var(--hf-lp-arena-muted);
  font-size: 10px;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.hf-lp-nextup {
  position: absolute;
  bottom: 24px;
  left: 0;
  z-index: 3;
  display: grid;
  gap: 4px;
  width: min(240px, 70%);
  padding: 16px 18px;
  border: 1px solid var(--hf-lp-line);
  border-radius: 14px;
  background: var(--hf-lp-card);
  box-shadow: var(--hf-lp-shadow);
  transform: rotate(2deg);
}

.hf-lp-nextup__eyebrow,
.hf-lp-nextup__court {
  color: var(--hf-lp-muted);
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.1em;
  text-transform: uppercase;
}

.hf-lp-nextup strong {
  font-family: var(--hf-lp-display);
  font-size: 20px;
  letter-spacing: 0.02em;
}

.hf-lp-nextup__teams {
  font-size: 12px;
  line-height: 1.4;
}

.hf-lp-nextup__teams span {
  color: var(--hf-lp-muted);
}

.hf-lp-stamp {
  position: absolute;
  top: 10px;
  right: 0;
  z-index: 1;
  display: grid;
  gap: 2px;
  padding: 10px 14px;
  border: 1px solid color-mix(in srgb, var(--hf-lp-orange) 50%, transparent);
  color: var(--hf-lp-orange-ink);
  transform: rotate(11deg);
  text-transform: uppercase;
}

.hf-lp-stamp span {
  font-family: var(--hf-lp-display);
  font-size: 22px;
  font-weight: 800;
  line-height: 1;
}

.hf-lp-stamp strong {
  font-size: 9px;
  letter-spacing: 0.12em;
}

.hf-lp-scroll {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: max-content;
  margin: 48px auto 0;
  color: var(--hf-lp-muted);
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.14em;
  text-decoration: none;
  text-transform: uppercase;
}

.hf-lp-scroll:hover {
  color: var(--hf-lp-ink);
  text-decoration: none;
}

@keyframes hf-lp-float {
  0%, 100% { transform: rotate(-2deg) translateY(0); }
  50% { transform: rotate(-1deg) translateY(-8px); }
}

@media (max-width: 991.98px) {
  .hf-lp-hero__inner {
    grid-template-columns: 1fr;
  }

  .hf-lp-hero__scene {
    min-height: 430px;
    max-width: 560px;
    margin-inline: auto;
    width: 100%;
  }

  .hf-lp-scoreboard {
    margin-inline: auto;
  }
}

@media (max-width: 575.98px) {
  .hf-lp-hero__scene {
    min-height: 460px;
  }

  .hf-lp-nextup {
    left: 8px;
    bottom: 0;
  }

  .hf-lp-stamp {
    right: 8px;
  }
}

@media (prefers-reduced-motion: reduce) {
  .hf-lp-scoreboard {
    animation: none;
    transform: none;
  }
}
```

- [ ] **Step 9: Add the header and reveal scripts**

Create `src/resources/js/landing/header.js`:

```js
export default function initHeader() {
    const header = document.querySelector('[data-landing-header]');

    if (header) {
        const update = () => header.classList.toggle('is-scrolled', window.scrollY > 24);
        update();
        window.addEventListener('scroll', update, { passive: true });
    }

    const button = document.querySelector('[data-landing-menu]');
    const menu = document.getElementById('landing-menu');

    if (!button || !menu) {
        return;
    }

    const close = () => {
        menu.hidden = true;
        button.setAttribute('aria-expanded', 'false');
        button.setAttribute('aria-label', 'Open menu');
    };

    button.addEventListener('click', () => {
        const open = menu.hidden;
        menu.hidden = !open;
        button.setAttribute('aria-expanded', String(open));
        button.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
    });

    menu.querySelectorAll('a').forEach((link) => link.addEventListener('click', close));

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            close();
        }
    });

    document.addEventListener('click', (event) => {
        if (!menu.hidden && !menu.contains(event.target) && !button.contains(event.target)) {
            close();
        }
    });
}
```

Create `src/resources/js/landing/reveal.js`:

```js
export default function initReveal() {
    const elements = [...document.querySelectorAll('.hf-lp-reveal')];
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (!elements.length) {
        return;
    }

    if (reduced || !('IntersectionObserver' in window)) {
        elements.forEach((element) => element.classList.add('is-in'));
        return;
    }

    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-in');
                    observer.unobserve(entry.target);
                }
            });
        },
        { threshold: 0.12, rootMargin: '0px 0px -8% 0px' },
    );

    elements.forEach((element) => observer.observe(element));
}
```

Create `src/resources/js/landing.js`:

```js
import initHeader from './landing/header';
import initReveal from './landing/reveal';

initHeader();
initReveal();
```

- [ ] **Step 10: Build and run the feature test**

```powershell
cd src
npm run build
php artisan test tests/Feature/Pages/LandingTest.php
```

Expected: build succeeds; 2 feature tests pass.

- [ ] **Step 11: Browser-check the hero**

Start the app (`php artisan serve --port=8000`) and open `http://127.0.0.1:8000`. Check:

- The header sticks and gains blur after scrolling 24 px.
- The mobile menu opens, closes on Escape and outside click.
- The hero headline is legible at 1440, 768, and 390 px.
- The scoreboard and next-up cards do not overlap the copy on mobile.
- Light/dark toggle changes the page and survives reload.
- No console errors and no horizontal scrollbar.

- [ ] **Step 12: Format and commit**

```powershell
cd src
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
git add app/Http/Controllers/LandingController.php routes/web.php resources/css resources/js resources/views/layouts/landing.blade.php resources/views/landing resources/views/components/landing resources/views/pages/home.blade.php tests/Feature/Pages/LandingTest.php vite.config.js
git commit -m "feat(landing): add landing shell and hero"
```

## Task 3: Feature lab shell and bracket panel

**Files:**
- Create: `src/resources/views/landing/lab.blade.php`
- Create: `src/resources/views/landing/bracket-panel.blade.php`
- Create: `src/resources/css/landing/_lab.scss`
- Create: `src/resources/css/landing/_bracket.scss`
- Create: `src/resources/js/landing/tabs.js`
- Create: `src/resources/js/landing/glow.js`
- Modify: `src/resources/css/landing.scss`
- Modify: `src/resources/js/landing.js`
- Modify: `src/resources/views/pages/home.blade.php`
- Test: `src/tests/Feature/Pages/LandingTest.php`

**Interfaces:**
- Consumes: `$showcase->bracket()`, `$showcase->tournament()`, `$teams`, `x-landing.crest`.
- Produces: tab hooks `data-demo-tab` / `data-demo-panel`, bracket hook `data-bracket`, `initTabs()`, `initGlow()`.

- [ ] **Step 1: Extend the feature test**

Append to `src/tests/Feature/Pages/LandingTest.php`:

```php
it('renders the read-only feature lab with a bracket', function () {
    $this->get('/')
        ->assertOk()
        ->assertSeeText('Poke around.')
        ->assertSeeText('Nothing breaks.')
        ->assertSeeText('Preview only')
        ->assertSeeText('San Isidro Vipers')
        ->assertSeeText('Quarterfinals')
        ->assertSee('data-testid="landing-lab"', false)
        ->assertSee('data-testid="demo-tab-bracket"', false)
        ->assertSee('data-testid="demo-tab-schedule"', false)
        ->assertSee('data-testid="demo-tab-standings"', false)
        ->assertSee('data-testid="demo-panel-bracket"', false);
});
```

- [ ] **Step 2: Run it and confirm it fails**

Run: `cd src; php artisan test tests/Feature/Pages/LandingTest.php --filter="read-only feature lab"`

Expected: FAIL (no lab section).

- [ ] **Step 3: Create the lab partial**

Create `src/resources/views/landing/lab.blade.php`:

```blade
<section id="features" class="hf-lp-lab" data-testid="landing-lab" data-landing-lab aria-labelledby="landing-lab-title">
    <div class="hf-lp-lab__glow" aria-hidden="true"></div>

    <div class="hf-lp-shell">
        <header class="hf-lp-section-head hf-lp-section-head--dark">
            <span class="hf-eyebrow hf-lp-eyebrow">The feature lab · read-only preview</span>
            <h2 id="landing-lab-title">Poke around. <em>Nothing breaks.</em></h2>
            <p>
                The real interface, loaded with a sample barangay league. Switch between the bracket,
                schedule, and standings. Editing stays off.
            </p>
            <span class="hf-lp-badge"><i class="bi bi-lock" aria-hidden="true"></i> Preview only · changes disabled</span>
        </header>

        <div class="hf-lp-lab__body">
            <div class="hf-lp-lab__rail">
                <div class="hf-lp-tabs" role="tablist" aria-label="Product preview">
                    <button type="button" class="hf-lp-tab is-active" id="tab-bracket" role="tab" aria-selected="true" aria-controls="panel-bracket" tabindex="0" data-demo-tab="bracket" data-testid="demo-tab-bracket">
                        <span class="hf-lp-tab__icon"><i class="bi bi-diagram-3" aria-hidden="true"></i></span>
                        <span><strong>Bracket</strong><small>Every path to the final, settled.</small></span>
                    </button>
                    <button type="button" class="hf-lp-tab" id="tab-schedule" role="tab" aria-selected="false" aria-controls="panel-schedule" tabindex="-1" data-demo-tab="schedule" data-testid="demo-tab-schedule">
                        <span class="hf-lp-tab__icon"><i class="bi bi-calendar3" aria-hidden="true"></i></span>
                        <span><strong>Schedule</strong><small>Courts, rest, and tip-off times in one view.</small></span>
                    </button>
                    <button type="button" class="hf-lp-tab" id="tab-standings" role="tab" aria-selected="false" aria-controls="panel-standings" tabindex="-1" data-demo-tab="standings" data-testid="demo-tab-standings">
                        <span class="hf-lp-tab__icon"><i class="bi bi-bar-chart" aria-hidden="true"></i></span>
                        <span><strong>Standings</strong><small>The cut line, without the manual math.</small></span>
                    </button>
                </div>

                <p class="hf-lp-lab__note">This is a read-only sample. Sign in to run your own league.</p>
            </div>

            <div class="hf-lp-lab__stage">
                <div class="hf-lp-lab__bar">
                    <span><i class="bi bi-lock" aria-hidden="true"></i> Read-only sample</span>
                    <span>{{ $tournament['name'] }} · {{ $tournament['division'] }}</span>
                </div>

                <div id="panel-bracket" class="hf-lp-panel is-active" role="tabpanel" aria-labelledby="tab-bracket" tabindex="0" data-demo-panel="bracket" data-testid="demo-panel-bracket">
                    @include('landing.bracket-panel', ['bracket' => $bracket, 'teams' => $teams])
                </div>

                <div id="panel-schedule" class="hf-lp-panel" role="tabpanel" aria-labelledby="tab-schedule" tabindex="0" data-demo-panel="schedule" data-testid="demo-panel-schedule">
                    {{-- Task 4 replaces this with the schedule include --}}
                </div>

                <div id="panel-standings" class="hf-lp-panel" role="tabpanel" aria-labelledby="tab-standings" tabindex="0" data-demo-panel="standings" data-testid="demo-panel-standings">
                    {{-- Task 4 replaces this with the standings include --}}
                </div>
            </div>
        </div>

        <p class="hf-lp-lab__cta">
            <a href="{{ route('login') }}">Sign in to manage</a>
            <span>or</span>
            <a href="{{ route('register') }}">create an account</a>
        </p>
    </div>
</section>
```

The schedule and standings panels are intentionally empty until Task 4; that task replaces both comments with their includes. Do not render the comments in the final page.

- [ ] **Step 4: Create the bracket panel**

Create `src/resources/views/landing/bracket-panel.blade.php`:

```blade
<div class="hf-lp-bracket" data-bracket>
    @foreach ($bracket['rounds'] as $round)
        <div class="hf-lp-bracket__round hf-lp-bracket__round--{{ $round['key'] }}">
            <h3 class="hf-lp-bracket__title">{{ $round['label'] }}</h3>

            <div class="hf-lp-bracket__matches">
                @foreach ($round['matches'] as $match)
                    @php
                        $home = $match['home'] ? $teams->get($match['home']) : null;
                        $away = $match['away'] ? $teams->get($match['away']) : null;
                    @endphp

                    <article class="hf-lp-bracket__match hf-lp-bracket__match--{{ $match['status'] }}" data-bracket-match="{{ $match['id'] }}">
                        <header class="hf-lp-bracket__head">
                            <span class="hf-lp-bracket__code">{{ $match['code'] }}</span>
                            @if ($match['status'] === 'live')
                                <span class="hf-lp-live hf-lp-live--sm"><span class="hf-lp-live__dot" aria-hidden="true"></span> Live</span>
                            @else
                                <x-status-chip :status="$match['status']" />
                            @endif
                        </header>

                        <div class="hf-lp-bracket__team {{ $match['winner'] !== null && (int) $match['winner'] === (int) $match['home'] ? 'is-winner' : '' }}">
                            <x-landing.crest :team="$home" :label="$match['home_label']" />
                            <span class="hf-lp-bracket__name">{{ $home['name'] ?? $match['home_label'] ?? 'TBD' }}</span>
                            <strong class="hf-lp-bracket__score">{{ $match['home_score'] ?? '–' }}</strong>
                        </div>

                        <div class="hf-lp-bracket__team {{ $match['winner'] !== null && (int) $match['winner'] === (int) $match['away'] ? 'is-winner' : '' }}">
                            <x-landing.crest :team="$away" :label="$match['away_label']" />
                            <span class="hf-lp-bracket__name">{{ $away['name'] ?? $match['away_label'] ?? 'TBD' }}</span>
                            <strong class="hf-lp-bracket__score">{{ $match['away_score'] ?? '–' }}</strong>
                        </div>

                        <footer class="hf-lp-bracket__foot">
                            <span>{{ $match['day_label'] }} · {{ $match['time'] }}</span>
                            <span>{{ $match['court'] }}</span>
                        </footer>
                    </article>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
```

Add the lab include to `pages/home.blade.php` after the hero include:

```blade
@include('landing.lab', [
    'tournament' => $showcase->tournament(),
    'bracket' => $showcase->bracket(),
    'teams' => $teams,
])
```

- [ ] **Step 5: Create the lab and bracket styles**

Add `@use "landing/lab";` and `@use "landing/bracket";` to `landing.scss`.

Create `src/resources/css/landing/_lab.scss` with the shell rules:

```scss
.hf-lp-lab {
  position: relative;
  overflow: hidden;
  padding: clamp(72px, 9vw, 120px) 0;
  background:
    radial-gradient(circle at 85% 0%, rgba(234, 88, 12, 0.14), transparent 36%),
    var(--hf-lp-arena);
  color: var(--hf-lp-arena-ink);
}

.hf-lp-lab__glow {
  position: absolute;
  inset: 0;
  pointer-events: none;
  background: radial-gradient(
    440px circle at var(--mx, 72%) var(--my, 30%),
    rgba(234, 88, 12, 0.14),
    transparent 62%
  );
  opacity: 0;
  transition: opacity 0.35s ease;
}

.hf-lp-lab:hover .hf-lp-lab__glow {
  opacity: 1;
}

.hf-lp-section-head--dark .hf-lp-eyebrow {
  color: #fdba74;
}

.hf-lp-section-head--dark p {
  color: var(--hf-lp-arena-muted);
}

.hf-lp-section-head--dark h2 em {
  color: #fdba74;
  font-style: normal;
}

.hf-lp-badge {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  margin-top: 18px;
  padding: 7px 11px;
  border: 1px solid rgba(243, 245, 249, 0.18);
  border-radius: 999px;
  background: rgba(243, 245, 249, 0.06);
  color: var(--hf-lp-arena-muted);
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.hf-lp-lab__body {
  display: grid;
  grid-template-columns: minmax(220px, 280px) minmax(0, 1fr);
  gap: clamp(20px, 3vw, 36px);
  align-items: start;
}

.hf-lp-tabs {
  display: grid;
  gap: 8px;
}

.hf-lp-tab {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  width: 100%;
  padding: 14px;
  border: 1px solid transparent;
  border-radius: 12px;
  background: transparent;
  color: var(--hf-lp-arena-muted);
  cursor: pointer;
  text-align: left;
  transition:
    background-color 0.2s ease,
    border-color 0.2s ease,
    color 0.2s ease;
}

.hf-lp-tab:hover,
.hf-lp-tab.is-active {
  border-color: rgba(243, 245, 249, 0.14);
  background: rgba(243, 245, 249, 0.06);
  color: var(--hf-lp-arena-ink);
}

.hf-lp-tab.is-active {
  border-left: 3px solid var(--hf-lp-orange);
}

.hf-lp-tab__icon {
  display: grid;
  place-items: center;
  flex: none;
  width: 32px;
  height: 32px;
  border-radius: 9px;
  background: rgba(234, 88, 12, 0.16);
  color: #fdba74;
}

.hf-lp-tab strong {
  display: block;
  font-size: 14px;
}

.hf-lp-tab small {
  display: block;
  margin-top: 2px;
  font-size: 11px;
  line-height: 1.4;
}

.hf-lp-lab__note {
  margin: 20px 4px 0;
  color: var(--hf-lp-arena-muted);
  font-size: 11px;
  line-height: 1.5;
}

.hf-lp-lab__stage {
  min-width: 0;
  padding: 16px;
  border: 1px solid rgba(243, 245, 249, 0.12);
  border-radius: 20px;
  background: #080d17;
  box-shadow: 0 30px 80px rgba(0, 0, 0, 0.3);
}

.hf-lp-lab__bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 0 4px 14px;
  color: var(--hf-lp-arena-muted);
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.hf-lp-panel {
  padding: 0;
}

.hf-lp-panel:focus-visible {
  outline: 3px solid var(--hf-lp-orange);
  outline-offset: 4px;
}

.hf-js .hf-lp-panel:not(.is-active) {
  display: none;
}

.hf-lp-lab__cta {
  margin: 28px 0 0;
  color: var(--hf-lp-arena-muted);
  font-size: 13px;
  text-align: right;
}

.hf-lp-lab__cta a {
  color: #fdba74;
  font-weight: 700;
}

@media (max-width: 991.98px) {
  .hf-lp-lab__body {
    grid-template-columns: 1fr;
  }

  .hf-lp-tabs {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }

  .hf-lp-tab {
    display: grid;
    gap: 8px;
    padding: 12px;
  }

  .hf-lp-lab__note {
    display: none;
  }
}

@media (max-width: 767.98px) {
  .hf-lp-lab__stage {
    padding: 12px;
  }

  .hf-lp-tabs {
    display: flex;
    overflow-x: auto;
    padding-bottom: 4px;
    scrollbar-width: none;
  }

  .hf-lp-tabs::-webkit-scrollbar {
    display: none;
  }

  .hf-lp-tab {
    min-width: 160px;
  }

  .hf-lp-lab__bar {
    flex-direction: column;
    align-items: flex-start;
    gap: 4px;
  }
}
```

Create `src/resources/css/landing/_bracket.scss`:

```scss
.hf-lp-bracket {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 24px;
  min-height: 530px;
}

.hf-lp-bracket__round {
  position: relative;
  display: grid;
  grid-auto-rows: min-content;
  align-content: space-around;
  gap: 12px;
  min-width: 0;
}

.hf-lp-bracket__round:not(:last-child)::after {
  position: absolute;
  top: 16%;
  right: -12px;
  bottom: 16%;
  border-right: 1px dashed rgba(243, 245, 249, 0.18);
  content: '';
  transform: scaleY(0);
  transform-origin: top;
  transition: transform 0.8s var(--hf-lp-ease);
}

.hf-js .hf-lp-bracket.is-in .hf-lp-bracket__round::after {
  transform: scaleY(1);
}

.hf-lp-bracket__title {
  margin: 0;
  color: var(--hf-lp-arena-muted);
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.14em;
  text-transform: uppercase;
}

.hf-lp-bracket__matches {
  display: grid;
  gap: 12px;
}

.hf-lp-bracket__match {
  position: relative;
  padding: 10px;
  border: 1px solid rgba(243, 245, 249, 0.12);
  border-radius: 12px;
  background: var(--hf-lp-arena-2);
  transition:
    border-color 0.2s ease,
    transform 0.2s ease,
    box-shadow 0.2s ease;
}

.hf-lp-bracket__match:hover {
  z-index: 2;
  border-color: rgba(234, 88, 12, 0.55);
  box-shadow: 0 12px 28px rgba(0, 0, 0, 0.28);
  transform: translateY(-2px);
}

.hf-lp-bracket__match--live {
  border-color: rgba(234, 88, 12, 0.7);
  box-shadow: 0 0 0 1px rgba(234, 88, 12, 0.25), 0 12px 30px rgba(234, 88, 12, 0.12);
}

.hf-lp-bracket__head,
.hf-lp-bracket__foot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.hf-lp-bracket__head {
  margin-bottom: 8px;
}

.hf-lp-bracket__code {
  color: var(--hf-lp-arena-muted);
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  font-size: 9px;
  letter-spacing: 0.08em;
}

.hf-lp-bracket__team {
  display: flex;
  align-items: center;
  gap: 8px;
  min-height: 32px;
  color: var(--hf-lp-arena-muted);
}

.hf-lp-bracket__team.is-winner {
  color: var(--hf-lp-arena-ink);
}

.hf-lp-bracket__name {
  flex: 1;
  overflow: hidden;
  font-size: 11px;
  font-weight: 600;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.hf-lp-bracket__score {
  font-family: var(--hf-lp-display);
  font-size: 20px;
  line-height: 1;
}

.hf-lp-bracket__team.is-winner .hf-lp-bracket__score {
  color: #fdba74;
}

.hf-lp-bracket__foot {
  margin-top: 8px;
  padding-top: 7px;
  border-top: 1px solid rgba(243, 245, 249, 0.08);
  color: var(--hf-lp-arena-muted);
  font-size: 8px;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

@media (max-width: 991.98px) {
  .hf-lp-bracket {
    grid-template-columns: repeat(3, minmax(220px, 1fr));
    min-height: 0;
    overflow-x: auto;
    padding-bottom: 10px;
    scroll-snap-type: x mandatory;
  }

  .hf-lp-bracket__round {
    align-content: start;
    min-height: 0;
    scroll-snap-align: start;
  }

  .hf-lp-bracket__round:nth-child(2) {
    padding-top: 44px;
  }

  .hf-lp-bracket__round:nth-child(3) {
    padding-top: 88px;
  }

  .hf-lp-bracket__round::after {
    display: none;
  }
}
```

- [ ] **Step 6: Add tabs and glow scripts**

Create `src/resources/js/landing/tabs.js`:

```js
export default function initTabs() {
    const tabs = [...document.querySelectorAll('[data-demo-tab]')];
    const panels = [...document.querySelectorAll('[data-demo-panel]')];

    if (!tabs.length || !panels.length) {
        return;
    }

    const activate = (name, focus = false) => {
        tabs.forEach((tab) => {
            const selected = tab.dataset.demoTab === name;
            tab.classList.toggle('is-active', selected);
            tab.setAttribute('aria-selected', String(selected));
            tab.tabIndex = selected ? 0 : -1;

            if (selected && focus) {
                tab.focus();
            }
        });

        panels.forEach((panel) => {
            panel.classList.toggle('is-active', panel.dataset.demoPanel === name);
        });
    };

    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => activate(tab.dataset.demoTab));

        tab.addEventListener('keydown', (event) => {
            let next = null;

            if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
                next = (index + 1) % tabs.length;
            }
            if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
                next = (index - 1 + tabs.length) % tabs.length;
            }
            if (event.key === 'Home') {
                next = 0;
            }
            if (event.key === 'End') {
                next = tabs.length - 1;
            }

            if (next !== null) {
                event.preventDefault();
                activate(tabs[next].dataset.demoTab, true);
            }
        });
    });

    document.querySelectorAll('[data-demo-target]').forEach((link) => {
        link.addEventListener('click', () => activate(link.dataset.demoTarget));
    });

    const fromHash = window.location.hash.replace('#', '');

    if (['bracket', 'schedule', 'standings'].includes(fromHash)) {
        activate(fromHash);
    } else {
        activate('bracket');
    }
}
```

Create `src/resources/js/landing/glow.js`:

```js
export default function initGlow() {
    const lab = document.querySelector('[data-landing-lab]');

    if (!lab || !window.matchMedia('(pointer: fine)').matches) {
        return;
    }

    lab.addEventListener('pointermove', (event) => {
        const rect = lab.getBoundingClientRect();
        lab.style.setProperty('--mx', `${event.clientX - rect.left}px`);
        lab.style.setProperty('--my', `${event.clientY - rect.top}px`);
    });
}
```

Update `src/resources/js/landing.js`:

```js
import initHeader from './landing/header';
import initReveal from './landing/reveal';
import initTabs from './landing/tabs';
import initGlow from './landing/glow';

initHeader();
initReveal();
initTabs();
initGlow();
```

- [ ] **Step 7: Draw the bracket connectors when visible**

Add to `src/resources/js/landing/reveal.js` a second observer for `[data-bracket]`:

```js
    const bracket = document.querySelector('[data-bracket]');

    if (bracket) {
        if (reduced || !('IntersectionObserver' in window)) {
            bracket.classList.add('is-in');
        } else {
            const bracketObserver = new IntersectionObserver(
                (entries) => {
                    entries.forEach((entry) => {
                        if (entry.isIntersecting) {
                            entry.target.classList.add('is-in');
                            bracketObserver.unobserve(entry.target);
                        }
                    });
                },
                { threshold: 0.2 },
            );

            bracketObserver.observe(bracket);
        }
    }
```

- [ ] **Step 8: Build and test**

```powershell
cd src
npm run build
php artisan test tests/Feature/Pages/LandingTest.php
```

Expected: build succeeds; 3 feature tests pass.

- [ ] **Step 9: Browser-check the lab**

- Tabs switch instantly; the URL hash `#schedule` and `#standings` opens the right panel.
- Arrow keys move between tabs; Home/End work.
- The bracket shows 7 matches across 3 rounds, with the live quarterfinal highlighted.
- The connectors draw once when scrolled into view.
- On mobile, the bracket scrolls horizontally inside the stage without causing page overflow.
- The pointer glow appears on desktop and not on touch emulation.
- The page still shows all panels with JavaScript disabled (progressive enhancement).

- [ ] **Step 10: Commit**

```powershell
cd src
./vendor/bin/pint --test
git add resources/css resources/js resources/views/landing resources/views/pages/home.blade.php tests/Feature/Pages/LandingTest.php
git commit -m "feat(landing): add feature lab and bracket preview"
```

## Task 4: Schedule and standings panels

**Files:**
- Create: `src/resources/views/landing/schedule-panel.blade.php`
- Create: `src/resources/views/landing/standings-panel.blade.php`
- Create: `src/resources/css/landing/_schedule.scss`
- Create: `src/resources/css/landing/_standings.scss`
- Create: `src/resources/js/landing/schedule.js`
- Create: `src/resources/js/landing/countup.js`
- Modify: `src/resources/views/landing/lab.blade.php`
- Modify: `src/resources/views/pages/home.blade.php`
- Modify: `src/resources/css/landing.scss`
- Modify: `src/resources/js/landing.js`
- Test: `src/tests/Feature/Pages/LandingTest.php`

**Interfaces:**
- Consumes: `$showcase->schedule()`, `$showcase->standings()`, `$teams`, bracket match shape.
- Produces: schedule hooks `data-schedule`, `data-schedule-day`, `data-schedule-court`, `data-schedule-match`; count-up hook `data-count` with optional `data-format="signed"`; `initSchedule()`, `initCountUp()`.

- [ ] **Step 1: Extend the feature test**

Append:

```php
it('renders the schedule and standings previews', function () {
    $this->get('/')
        ->assertOk()
        ->assertSeeText('Sat, Nov 14')
        ->assertSeeText('Sat, Nov 21')
        ->assertSeeText('Sun, Nov 22')
        ->assertSeeText('Top 8 advance to the quarterfinals.')
        ->assertSeeText('Bayanihan Ballers')
        ->assertSee('data-testid="demo-panel-schedule"', false)
        ->assertSee('data-testid="demo-panel-standings"', false)
        ->assertSee('data-schedule-day="day1"', false)
        ->assertSee('data-count="78"', false);
});
```

- [ ] **Step 2: Run it and confirm it fails**

Run: `cd src; php artisan test tests/Feature/Pages/LandingTest.php --filter="schedule and standings"`

Expected: FAIL (the panels are empty).

- [ ] **Step 3: Create the schedule panel**

Create `src/resources/views/landing/schedule-panel.blade.php`:

```blade
<div class="hf-lp-schedule" data-schedule>
    <div class="hf-lp-schedule__controls">
        <div class="hf-lp-chip-group" role="group" aria-label="Schedule day">
            @foreach ($schedule as $day)
                <button
                    type="button"
                    class="hf-lp-chip {{ $loop->first ? 'is-active' : '' }}"
                    data-schedule-day="{{ $day['key'] }}"
                    aria-pressed="{{ $loop->first ? 'true' : 'false' }}"
                >
                    <strong>{{ $day['short'] }}</strong>
                    <span>{{ $day['subtitle'] }}</span>
                </button>
            @endforeach
        </div>

        <div class="hf-lp-chip-group" role="group" aria-label="Court filter">
            <button type="button" class="hf-lp-chip is-active" data-schedule-court="all" aria-pressed="true">All courts</button>
            <button type="button" class="hf-lp-chip" data-schedule-court="Court A" aria-pressed="false">Court A</button>
            <button type="button" class="hf-lp-chip" data-schedule-court="Court B" aria-pressed="false">Court B</button>
        </div>
    </div>

    <div class="hf-lp-schedule__list">
        @foreach ($schedule as $day)
            <div class="hf-lp-schedule__group" data-schedule-group="{{ $day['key'] }}">
                <h4 class="hf-lp-schedule__day">{{ $day['label'] }} <span>{{ $day['subtitle'] }}</span></h4>

                @foreach ($day['matches'] as $match)
                    @php
                        $home = $match['home'] ? $teams->get($match['home']) : null;
                        $away = $match['away'] ? $teams->get($match['away']) : null;
                    @endphp

                    <article
                        class="hf-lp-schedule__match"
                        data-schedule-match
                        data-day="{{ $day['key'] }}"
                        data-court="{{ $match['court'] }}"
                    >
                        <div class="hf-lp-schedule__time">
                            <strong>{{ $match['time'] }}</strong>
                            <span>{{ $match['court'] }}</span>
                        </div>

                        <div class="hf-lp-schedule__matchup">
                            <span class="hf-lp-schedule__round">{{ $match['code'] }} · {{ $match['round'] }}</span>
                            <span class="hf-lp-schedule__teams">
                                <span>{{ $home['name'] ?? $match['home_label'] ?? 'TBD' }}</span>
                                <span class="hf-lp-schedule__vs">vs</span>
                                <span>{{ $away['name'] ?? $match['away_label'] ?? 'TBD' }}</span>
                            </span>
                        </div>

                        <div class="hf-lp-schedule__status">
                            @if ($match['status'] === 'live')
                                <span class="hf-lp-live hf-lp-live--sm"><span class="hf-lp-live__dot" aria-hidden="true"></span> Live</span>
                            @else
                                <x-status-chip :status="$match['status']" />
                            @endif
                            @if ($match['home_score'] !== null)
                                <strong class="hf-lp-schedule__score">{{ $match['home_score'] }}–{{ $match['away_score'] }}</strong>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @endforeach

        <p class="hf-lp-schedule__empty" data-schedule-empty hidden>
            No games on this court for this day.
        </p>
    </div>
</div>
```

- [ ] **Step 4: Create the standings panel**

Create `src/resources/views/landing/standings-panel.blade.php`:

```blade
<div class="hf-lp-standings">
    <div class="hf-lp-standings__caption">
        <strong>Regular season</strong>
        <span>Top 8 advance to the quarterfinals.</span>
    </div>

    <div class="hf-lp-table-wrap">
        <table class="hf-lp-standings__table">
            <thead>
                <tr>
                    <th scope="col">#</th>
                    <th scope="col">Team</th>
                    <th scope="col" class="hf-lp-num">W</th>
                    <th scope="col" class="hf-lp-num">L</th>
                    <th scope="col" class="hf-lp-num">PF</th>
                    <th scope="col" class="hf-lp-num">PA</th>
                    <th scope="col" class="hf-lp-num">+/−</th>
                    <th scope="col" class="hf-lp-num">Seed</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($standings as $row)
                    @php $team = $teams->get($row['team_id']); @endphp
                    <tr class="{{ $row['qualified'] ? 'is-qualified' : '' }}" data-standings-row="{{ $row['rank'] }}">
                        <td class="hf-lp-num">{{ $row['rank'] }}</td>
                        <td>
                            <span class="hf-lp-standings__team">
                                <x-landing.crest :team="$team" />
                                <strong>{{ $team['name'] ?? 'Team' }}</strong>
                            </span>
                        </td>
                        <td class="hf-lp-num"><span data-count="{{ $row['wins'] }}">{{ $row['wins'] }}</span></td>
                        <td class="hf-lp-num"><span data-count="{{ $row['losses'] }}">{{ $row['losses'] }}</span></td>
                        <td class="hf-lp-num"><span data-count="{{ $row['points_for'] }}">{{ $row['points_for'] }}</span></td>
                        <td class="hf-lp-num"><span data-count="{{ $row['points_against'] }}">{{ $row['points_against'] }}</span></td>
                        <td class="hf-lp-num"><span data-count="{{ $row['point_diff'] }}" data-format="signed">{{ $row['point_diff'] > 0 ? '+' : ($row['point_diff'] < 0 ? '−'.abs($row['point_diff']) : '0') }}</span></td>
                        <td class="hf-lp-num">{{ $row['seed'] ?? '–' }}</td>
                    </tr>

                    @if ($row['rank'] === 8)
                        <tr class="hf-lp-standings__cut">
                            <td colspan="8"><span>Top 8 advance to the quarterfinals</span></td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="hf-lp-standings__cards">
        @foreach ($standings as $row)
            @php $team = $teams->get($row['team_id']); @endphp
            <article class="hf-lp-standings__card {{ $row['qualified'] ? 'is-qualified' : '' }}">
                <div class="hf-lp-standings__card-head">
                    <span class="hf-lp-standings__rank">#{{ $row['rank'] }}</span>
                    <x-landing.crest :team="$team" />
                    <strong>{{ $team['name'] ?? 'Team' }}</strong>
                    @if ($row['qualified'])
                        <span class="hf-lp-standings__tick"><i class="bi bi-check2" aria-hidden="true"></i></span>
                    @endif
                </div>
                <div class="hf-lp-standings__card-stats">
                    <span><strong data-count="{{ $row['wins'] }}">{{ $row['wins'] }}</strong> W</span>
                    <span><strong data-count="{{ $row['losses'] }}">{{ $row['losses'] }}</strong> L</span>
                    <span><strong data-count="{{ $row['point_diff'] }}" data-format="signed">{{ $row['point_diff'] > 0 ? '+' : '' }}{{ $row['point_diff'] }}</strong> DIFF</span>
                </div>
            </article>

            @if ($row['rank'] === 8)
                <div class="hf-lp-standings__cut-card"><span>Top 8 advance to the quarterfinals</span></div>
            @endif
        @endforeach
    </div>
</div>
```

- [ ] **Step 5: Wire both panels into the lab**

In `src/resources/views/pages/home.blade.php`, extend the lab include:

```blade
@include('landing.lab', [
    'tournament' => $showcase->tournament(),
    'bracket' => $showcase->bracket(),
    'schedule' => $showcase->schedule(),
    'standings' => $showcase->standings(),
    'teams' => $teams,
])
```

In `src/resources/views/landing/lab.blade.php`, replace the schedule panel comment with:

```blade
@include('landing.schedule-panel', ['schedule' => $schedule, 'teams' => $teams])
```

Replace the standings panel comment with:

```blade
@include('landing.standings-panel', ['standings' => $standings, 'teams' => $teams])
```

- [ ] **Step 6: Add schedule and standings styles**

Add `@use "landing/schedule";` and `@use "landing/standings";` to `landing.scss`.

Create `src/resources/css/landing/_schedule.scss`:

```scss
.hf-lp-schedule__controls {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 16px;
}

.hf-lp-chip-group {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.hf-lp-chip {
  display: inline-flex;
  align-items: baseline;
  gap: 6px;
  min-height: 38px;
  padding: 8px 11px;
  border: 1px solid rgba(243, 245, 249, 0.14);
  border-radius: 999px;
  background: transparent;
  color: var(--hf-lp-arena-muted);
  font-size: 10px;
  font-weight: 600;
  cursor: pointer;
}

.hf-lp-chip strong,
.hf-lp-chip span {
  font: inherit;
}

.hf-lp-chip span {
  opacity: 0.7;
}

.hf-lp-chip:hover,
.hf-lp-chip.is-active {
  border-color: rgba(234, 88, 12, 0.6);
  background: rgba(234, 88, 12, 0.12);
  color: #fdba74;
}

.hf-lp-schedule__group + .hf-lp-schedule__group {
  margin-top: 18px;
}

.hf-lp-schedule__day {
  display: flex;
  align-items: baseline;
  gap: 8px;
  margin: 0 0 8px;
  color: var(--hf-lp-arena-ink);
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.hf-lp-schedule__day span {
  color: var(--hf-lp-arena-muted);
  font-size: 9px;
  font-weight: 600;
}

.hf-lp-schedule__match {
  display: grid;
  grid-template-columns: 92px minmax(0, 1fr) auto;
  gap: 14px;
  align-items: center;
  padding: 12px;
  border: 1px solid rgba(243, 245, 249, 0.1);
  border-radius: 12px;
  background: var(--hf-lp-arena-2);
}

.hf-lp-schedule__match + .hf-lp-schedule__match {
  margin-top: 8px;
}

.hf-lp-schedule__time {
  display: grid;
  gap: 2px;
  border-right: 1px solid rgba(243, 245, 249, 0.12);
}

.hf-lp-schedule__time strong {
  font-family: var(--hf-lp-display);
  font-size: 20px;
  line-height: 1;
}

.hf-lp-schedule__time span {
  color: var(--hf-lp-arena-muted);
  font-size: 9px;
  text-transform: uppercase;
}

.hf-lp-schedule__matchup {
  display: grid;
  gap: 3px;
  min-width: 0;
}

.hf-lp-schedule__round {
  color: var(--hf-lp-arena-muted);
  font-size: 8px;
  font-weight: 700;
  letter-spacing: 0.1em;
  text-transform: uppercase;
}

.hf-lp-schedule__teams {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  font-size: 12px;
  font-weight: 600;
}

.hf-lp-schedule__vs {
  color: var(--hf-lp-arena-muted);
  font-weight: 400;
}

.hf-lp-schedule__status {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 4px;
}

.hf-lp-schedule__score {
  font-family: var(--hf-lp-display);
  font-size: 18px;
  line-height: 1;
}

.hf-lp-schedule__match[hidden],
.hf-lp-schedule__group[hidden],
.hf-lp-schedule__empty[hidden] {
  display: none !important;
}

.hf-lp-schedule__empty {
  margin: 16px 0 0;
  padding: 24px;
  border: 1px dashed rgba(243, 245, 249, 0.18);
  border-radius: 12px;
  color: var(--hf-lp-arena-muted);
  font-size: 12px;
  text-align: center;
}

@media (max-width: 575.98px) {
  .hf-lp-schedule__match {
    grid-template-columns: 1fr auto;
  }

  .hf-lp-schedule__time {
    grid-column: 1 / -1;
    grid-template-columns: auto auto;
    justify-content: start;
    gap: 10px;
    padding-bottom: 8px;
    border-right: 0;
    border-bottom: 1px solid rgba(243, 245, 249, 0.12);
  }
}
```

Create `src/resources/css/landing/_standings.scss`:

```scss
.hf-lp-standings__caption {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 8px;
  margin-bottom: 12px;
  color: var(--hf-lp-arena-muted);
  font-size: 11px;
}

.hf-lp-standings__caption strong {
  color: var(--hf-lp-arena-ink);
  font-size: 12px;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.hf-lp-table-wrap {
  max-height: 470px;
  overflow: auto;
  border: 1px solid rgba(243, 245, 249, 0.1);
  border-radius: 12px;
}

.hf-lp-standings__table {
  width: 100%;
  border-collapse: collapse;
  font-size: 12px;
  font-variant-numeric: tabular-nums;
}

.hf-lp-standings__table th {
  position: sticky;
  top: 0;
  z-index: 1;
  padding: 10px 12px;
  background: #0d1522;
  color: var(--hf-lp-arena-muted);
  font-size: 9px;
  font-weight: 800;
  letter-spacing: 0.1em;
  text-align: left;
  text-transform: uppercase;
}

.hf-lp-standings__table td {
  padding: 9px 12px;
  border-top: 1px solid rgba(243, 245, 249, 0.07);
  white-space: nowrap;
}

.hf-lp-standings__table tbody tr:hover {
  background: rgba(243, 245, 249, 0.04);
}

.hf-lp-standings__table tr.is-qualified td:first-child {
  box-shadow: inset 3px 0 0 var(--hf-lp-gold);
}

.hf-lp-standings__team {
  display: flex;
  align-items: center;
  gap: 8px;
}

.hf-lp-standings__team strong {
  font-weight: 600;
}

.hf-lp-num {
  text-align: right;
  font-variant-numeric: tabular-nums;
}

.hf-lp-standings__cut td {
  padding: 0;
  border-top: 0;
  background: rgba(183, 121, 0, 0.08);
}

.hf-lp-standings__cut span,
.hf-lp-standings__cut-card span {
  display: block;
  padding: 6px 12px;
  border-top: 1px dashed var(--hf-lp-gold);
  color: #f5b301;
  font-size: 9px;
  font-weight: 800;
  letter-spacing: 0.1em;
  text-align: right;
  text-transform: uppercase;
}

.hf-lp-standings__cards {
  display: none;
}

@media (max-width: 767.98px) {
  .hf-lp-table-wrap {
    display: none;
  }

  .hf-lp-standings__cards {
    display: grid;
    gap: 6px;
  }

  .hf-lp-standings__card {
    display: grid;
    gap: 8px;
    padding: 10px 12px;
    border: 1px solid rgba(243, 245, 249, 0.1);
    border-radius: 10px;
    background: var(--hf-lp-arena-2);
  }

  .hf-lp-standings__card.is-qualified {
    border-left: 3px solid var(--hf-lp-gold);
  }

  .hf-lp-standings__card-head {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
  }

  .hf-lp-standings__rank {
    color: var(--hf-lp-arena-muted);
    font-family: var(--hf-lp-display);
    font-size: 18px;
  }

  .hf-lp-standings__tick {
    margin-left: auto;
    color: #f5b301;
  }

  .hf-lp-standings__card-stats {
    display: flex;
    gap: 14px;
    color: var(--hf-lp-arena-muted);
    font-size: 10px;
  }

  .hf-lp-standings__card-stats strong {
    color: var(--hf-lp-arena-ink);
  }

  .hf-lp-standings__cut-card {
    padding: 2px 0;
  }
}
```

- [ ] **Step 7: Add schedule filtering and count-up scripts**

Create `src/resources/js/landing/schedule.js`:

```js
export default function initSchedule() {
    const root = document.querySelector('[data-schedule]');

    if (!root) {
        return;
    }

    const dayButtons = [...root.querySelectorAll('[data-schedule-day]')];
    const courtButtons = [...root.querySelectorAll('[data-schedule-court]')];
    const groups = [...root.querySelectorAll('[data-schedule-group]')];
    const matches = [...root.querySelectorAll('[data-schedule-match]')];
    const empty = root.querySelector('[data-schedule-empty]');

    let day = dayButtons[0]?.dataset.scheduleDay ?? 'day1';
    let court = 'all';

    const apply = () => {
        let visible = 0;

        groups.forEach((group) => {
            group.hidden = group.dataset.scheduleGroup !== day;
        });

        matches.forEach((match) => {
            const show = match.dataset.day === day && (court === 'all' || match.dataset.court === court);
            match.hidden = !show;

            if (show) {
                visible += 1;
            }
        });

        if (empty) {
            empty.hidden = visible > 0;
        }

        dayButtons.forEach((button) => {
            const active = button.dataset.scheduleDay === day;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-pressed', String(active));
        });

        courtButtons.forEach((button) => {
            const active = button.dataset.scheduleCourt === court;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-pressed', String(active));
        });
    };

    dayButtons.forEach((button) => {
        button.addEventListener('click', () => {
            day = button.dataset.scheduleDay;
            apply();
        });
    });

    courtButtons.forEach((button) => {
        button.addEventListener('click', () => {
            court = button.dataset.scheduleCourt;
            apply();
        });
    });

    apply();
}
```

Create `src/resources/js/landing/countup.js`:

```js
function formatValue(element, value) {
    if (element.dataset.format !== 'signed') {
        return String(value);
    }

    if (value > 0) {
        return `+${value}`;
    }

    if (value < 0) {
        return `−${Math.abs(value)}`;
    }

    return '0';
}

export default function initCountUp() {
    const counters = [...document.querySelectorAll('[data-count]')];

    if (!counters.length) {
        return;
    }

    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const run = (element) => {
        const target = Number(element.dataset.count);

        if (!Number.isFinite(target) || reduced) {
            element.textContent = formatValue(element, target);
            return;
        }

        const duration = 900;
        const start = performance.now();

        const tick = (now) => {
            const progress = Math.min((now - start) / duration, 1);
            const eased = 1 - Math.pow(1 - progress, 3);
            element.textContent = formatValue(element, Math.round(target * eased));

            if (progress < 1) {
                requestAnimationFrame(tick);
            }
        };

        requestAnimationFrame(tick);
    };

    if (!('IntersectionObserver' in window)) {
        counters.forEach(run);
        return;
    }

    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    run(entry.target);
                    observer.unobserve(entry.target);
                }
            });
        },
        { threshold: 0.5 },
    );

    counters.forEach((counter) => observer.observe(counter));
}
```

Update `src/resources/js/landing.js`:

```js
import initHeader from './landing/header';
import initReveal from './landing/reveal';
import initTabs from './landing/tabs';
import initGlow from './landing/glow';
import initSchedule from './landing/schedule';
import initCountUp from './landing/countup';

initHeader();
initReveal();
initTabs();
initGlow();
initSchedule();
initCountUp();
```

- [ ] **Step 8: Build and test**

```powershell
cd src
npm run build
php artisan test tests/Feature/Pages/LandingTest.php
```

Expected: build succeeds; 4 feature tests pass.

- [ ] **Step 9: Browser-check the panels**

- Day chips switch the visible group; court filters hide non-matching games.
- `Final + Court B` shows the empty state.
- Standings count up once; values settle on the server values.
- Top 8 rows have the gold edge; the cut line follows rank 8.
- Mobile shows standings cards, not the table.
- JavaScript disabled: all days, matches, and both table/card layouts render (cards hidden by CSS only on mobile; table visible).

- [ ] **Step 10: Commit**

```powershell
cd src
./vendor/bin/pint --test
git add resources/css resources/js resources/views/landing resources/views/pages/home.blade.php tests/Feature/Pages/LandingTest.php
git commit -m "feat(landing): add schedule and standings previews"
```

## Task 5: Matchday flow and role cards

**Files:**
- Create: `src/resources/views/landing/flow.blade.php`
- Create: `src/resources/views/landing/roles.blade.php`
- Create: `src/resources/css/landing/_flow.scss`
- Create: `src/resources/css/landing/_roles.scss`
- Modify: `src/resources/css/landing.scss`
- Modify: `src/resources/views/pages/home.blade.php`
- Test: `src/tests/Feature/Pages/LandingTest.php`

**Interfaces:**
- Consumes: `$showcase->flow()` (5 items with `title`, `copy`, `tag`, `icon`) and `$showcase->roles()` (3 items with `key`, `eyebrow`, `title`, `copy`, `accent`).

- [ ] **Step 1: Extend the feature test**

```php
it('explains the matchday flow and each role', function () {
    $this->get('/')
        ->assertOk()
        ->assertSeeText('Set the stage')
        ->assertSeeText('Register & vet')
        ->assertSeeText('Seed the bracket')
        ->assertSeeText('Schedule the floor')
        ->assertSeeText('Run the night')
        ->assertSeeText('See the whole floor.')
        ->assertSeeText('Know before tip-off.')
        ->assertSeeText('Follow from the bleachers.')
        ->assertSee('data-testid="landing-flow"', false)
        ->assertSee('data-testid="landing-roles"', false);
});
```

- [ ] **Step 2: Run it and confirm it fails**

Run: `cd src; php artisan test tests/Feature/Pages/LandingTest.php --filter="matchday flow"`

Expected: FAIL.

- [ ] **Step 3: Create the flow partial**

Create `src/resources/views/landing/flow.blade.php`:

```blade
<section id="flow" class="hf-lp-flow-section" data-testid="landing-flow" aria-labelledby="landing-flow-title">
    <div class="hf-lp-shell">
        <header class="hf-lp-section-head">
            <span class="hf-eyebrow hf-lp-eyebrow">From sign-up to final buzzer</span>
            <h2 id="landing-flow-title">One flow, from registration to the final buzzer.</h2>
            <p>Every step keeps the same tournament record moving forward — no re-typing, no lost sheets.</p>
        </header>

        <ol class="hf-lp-flow">
            @foreach ($steps as $step)
                <li class="hf-lp-flow__step hf-lp-reveal" style="--i: {{ $loop->index }}">
                    <span class="hf-lp-flow__num">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                    <span class="hf-lp-flow__dot"><i class="bi bi-{{ $step['icon'] }}" aria-hidden="true"></i></span>
                    <h3>{{ $step['title'] }}</h3>
                    <p>{{ $step['copy'] }}</p>
                    <span class="hf-lp-flow__tag">{{ $step['tag'] }}</span>
                </li>
            @endforeach
        </ol>
    </div>
</section>
```

- [ ] **Step 4: Create the roles partial**

Create `src/resources/views/landing/roles.blade.php`:

```blade
<section id="roles" class="hf-lp-roles-section" data-testid="landing-roles" aria-labelledby="landing-roles-title">
    <div class="hf-lp-shell">
        <header class="hf-lp-section-head">
            <span class="hf-eyebrow hf-lp-eyebrow">Built for the whole court</span>
            <h2 id="landing-roles-title">Everyone sees the game they need.</h2>
        </header>

        <div class="hf-lp-roles">
            @foreach ($roles as $role)
                <article class="hf-lp-role hf-lp-role--{{ $role['key'] }} hf-lp-reveal" style="--i: {{ $loop->index }}; --hf-role-accent: {{ $role['accent'] }}">
                    <div class="hf-lp-role__copy">
                        <span class="hf-eyebrow hf-lp-eyebrow">{{ $role['eyebrow'] }}</span>
                        <h3>{{ $role['title'] }}</h3>
                        <p>{{ $role['copy'] }}</p>
                    </div>

                    <div class="hf-lp-role__vignette" aria-hidden="true">
                        @switch($role['key'])
                            @case('organizer')
                                <div class="hf-lp-role__panel">
                                    <div class="hf-lp-role__row"><span>Vipers</span><span class="hf-lp-role__state is-ok">Roster verified</span></div>
                                    <div class="hf-lp-role__row"><span>Saints</span><span class="hf-lp-role__state is-warn">Consent pending</span></div>
                                    <div class="hf-lp-role__row"><span>Bolts</span><span class="hf-lp-role__state is-ok">Payment received</span></div>
                                    <span class="hf-lp-role__link">Open review <i class="bi bi-arrow-up-right"></i></span>
                                </div>
                                @break

                            @case('coach')
                                <div class="hf-lp-role__panel">
                                    <div class="hf-lp-role__meta"><span>Roster</span><strong>12/12</strong></div>
                                    <div class="hf-lp-role__progress"><span style="width: 100%"></span></div>
                                    <div class="hf-lp-role__meta"><span>Consent</span><strong>11/12</strong></div>
                                    <div class="hf-lp-role__progress"><span style="width: 92%"></span></div>
                                    <div class="hf-lp-role__next">Next: Sat 1:00 PM <strong>vs Vipers</strong></div>
                                </div>
                                @break

                            @case('fan')
                                <div class="hf-lp-role__phone">
                                    <span class="hf-lp-role__phone-label">Q4 · 02:14</span>
                                    <strong>66–50</strong>
                                    <span>Panthers lead Ravens</span>
                                    <span class="hf-lp-role__phone-next">Semifinal · Sat 1:00 PM</span>
                                </div>
                                @break
                        @endswitch
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</section>
```

- [ ] **Step 5: Add both sections to the page**

After the lab include in `pages/home.blade.php`:

```blade
@include('landing.flow', ['steps' => $showcase->flow()])
@include('landing.roles', ['roles' => $showcase->roles()])
```

- [ ] **Step 6: Add the flow styles**

Add `@use "landing/flow";` to `landing.scss`. Create `src/resources/css/landing/_flow.scss`:

```scss
.hf-lp-flow-section {
  padding: clamp(72px, 9vw, 120px) 0;
  background: var(--hf-lp-paper);
}

.hf-lp-flow {
  position: relative;
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 18px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.hf-lp-flow::before {
  position: absolute;
  top: 27px;
  right: 7%;
  left: 7%;
  border-top: 1px dashed var(--hf-lp-line);
  content: '';
}

.hf-lp-flow__step {
  position: relative;
  display: grid;
  align-content: start;
  gap: 8px;
  padding: 0 8px;
}

.hf-lp-flow__num {
  color: var(--hf-lp-muted);
  font-family: var(--hf-lp-display);
  font-size: 14px;
  font-weight: 800;
  letter-spacing: 0.08em;
}

.hf-lp-flow__dot {
  display: grid;
  place-items: center;
  width: 54px;
  height: 54px;
  margin-bottom: 4px;
  border: 1px solid var(--hf-lp-line);
  border-radius: 50%;
  background: var(--hf-lp-card);
  box-shadow: 0 6px 18px rgba(16, 24, 32, 0.06);
  color: var(--hf-lp-orange-ink);
  font-size: 20px;
}

.hf-lp-flow__step h3 {
  margin: 0;
  font-family: var(--hf-lp-display);
  font-size: 24px;
  line-height: 1;
  text-transform: uppercase;
}

.hf-lp-flow__step p {
  margin: 0;
  color: var(--hf-lp-muted);
  font-size: 12px;
  line-height: 1.55;
}

.hf-lp-flow__tag {
  width: max-content;
  margin-top: 4px;
  padding: 4px 7px;
  border: 1px solid var(--hf-lp-line);
  border-radius: 999px;
  color: var(--hf-lp-muted);
  font-size: 9px;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}

@media (max-width: 991.98px) {
  .hf-lp-flow {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 28px 18px;
  }

  .hf-lp-flow::before {
    display: none;
  }
}

@media (max-width: 575.98px) {
  .hf-lp-flow {
    grid-template-columns: 1fr;
  }

  .hf-lp-flow__step {
    grid-template-columns: 54px 1fr;
    column-gap: 14px;
  }

  .hf-lp-flow__num {
    grid-column: 2;
    grid-row: 1;
  }

  .hf-lp-flow__dot {
    grid-column: 1;
    grid-row: 1 / span 3;
  }

  .hf-lp-flow__step h3,
  .hf-lp-flow__step p,
  .hf-lp-flow__tag {
    grid-column: 2;
  }
}
```

- [ ] **Step 7: Add the role styles**

Add `@use "landing/roles";` to `landing.scss`. Create `src/resources/css/landing/_roles.scss`:

```scss
.hf-lp-roles-section {
  padding: clamp(72px, 9vw, 120px) 0;
  background:
    linear-gradient(180deg, transparent, color-mix(in srgb, var(--hf-lp-orange) 4%, transparent)),
    var(--hf-lp-paper-2);
}

.hf-lp-roles {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 16px;
}

.hf-lp-role {
  display: flex;
  flex-direction: column;
  gap: 22px;
  padding: 22px;
  border: 1px solid var(--hf-lp-line);
  border-radius: var(--hf-lp-radius);
  background: var(--hf-lp-card);
  box-shadow: 0 8px 24px rgba(16, 24, 32, 0.04);
  transition:
    transform 0.25s var(--hf-lp-ease),
    box-shadow 0.25s var(--hf-lp-ease),
    border-color 0.25s ease;
}

.hf-lp-role:hover {
  border-color: color-mix(in srgb, var(--hf-role-accent) 45%, var(--hf-lp-line));
  box-shadow: 0 18px 40px rgba(16, 24, 32, 0.1);
  transform: translateY(-4px);
}

.hf-lp-role__copy h3 {
  margin: 8px 0 8px;
  font-family: var(--hf-lp-display);
  font-size: 32px;
  line-height: 0.95;
  text-transform: uppercase;
}

.hf-lp-role__copy p {
  margin: 0;
  color: var(--hf-lp-muted);
  font-size: 13px;
  line-height: 1.55;
}

.hf-lp-role__vignette {
  margin-top: auto;
  padding: 14px;
  border-radius: 12px;
  background: color-mix(in srgb, var(--hf-role-accent) 7%, var(--hf-lp-paper-2));
}

.hf-lp-role__panel {
  display: grid;
  gap: 8px;
  padding: 12px;
  border: 1px solid var(--hf-lp-line);
  border-radius: 10px;
  background: var(--hf-lp-card);
}

.hf-lp-role__row,
.hf-lp-role__meta {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  font-size: 10px;
}

.hf-lp-role__state {
  padding: 3px 6px;
  border-radius: 999px;
  font-size: 8px;
  font-weight: 700;
}

.hf-lp-role__state.is-ok {
  background: color-mix(in srgb, var(--hf-lp-green) 14%, transparent);
  color: var(--hf-lp-green);
}

.hf-lp-role__state.is-warn {
  background: color-mix(in srgb, var(--hf-lp-gold) 16%, transparent);
  color: var(--hf-lp-gold);
}

.hf-lp-role__link {
  margin-top: 4px;
  color: var(--hf-role-accent);
  font-size: 9px;
  font-weight: 700;
  text-transform: uppercase;
}

.hf-lp-role__progress {
  height: 5px;
  overflow: hidden;
  border-radius: 999px;
  background: var(--hf-lp-paper-2);
}

.hf-lp-role__progress span {
  display: block;
  height: 100%;
  border-radius: inherit;
  background: var(--hf-role-accent);
}

.hf-lp-role__next {
  margin-top: 4px;
  padding-top: 8px;
  border-top: 1px solid var(--hf-lp-line);
  font-size: 10px;
}

.hf-lp-role__next strong {
  color: var(--hf-role-accent);
}

.hf-lp-role__phone {
  display: grid;
  gap: 3px;
  max-width: 190px;
  margin-inline: auto;
  padding: 16px;
  border: 1px solid var(--hf-lp-line);
  border-radius: 20px;
  background: var(--hf-lp-arena);
  color: var(--hf-lp-arena-ink);
  text-align: center;
}

.hf-lp-role__phone-label {
  color: #fdba74;
  font-size: 9px;
  font-weight: 800;
  letter-spacing: 0.1em;
  text-transform: uppercase;
}

.hf-lp-role__phone strong {
  font-family: var(--hf-lp-display);
  font-size: 38px;
  line-height: 1;
}

.hf-lp-role__phone > span:not(.hf-lp-role__phone-label):not(.hf-lp-role__phone-next) {
  color: var(--hf-lp-arena-muted);
  font-size: 9px;
}

.hf-lp-role__phone-next {
  margin-top: 8px;
  padding-top: 8px;
  border-top: 1px solid rgba(243, 245, 249, 0.14);
  color: var(--hf-lp-arena-muted);
  font-size: 8px;
}

@media (max-width: 991.98px) {
  .hf-lp-roles {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 767.98px) {
  .hf-lp-roles {
    grid-template-columns: 1fr;
  }
}
```

- [ ] **Step 8: Build, test, browse**

```powershell
cd src
npm run build
php artisan test tests/Feature/Pages/LandingTest.php
```

Expected: 5 feature tests pass. Check the flow timeline at desktop and mobile, and that role cards stack without clipping.

- [ ] **Step 9: Commit**

```powershell
cd src
./vendor/bin/pint --test
git add resources/css resources/views/landing resources/views/pages/home.blade.php tests/Feature/Pages/LandingTest.php
git commit -m "feat(landing): add matchday flow and roles"
```

## Task 6: Eligibility spotlight, matchday wire, closing, and footer

**Files:**
- Create: `src/resources/views/landing/eligibility.blade.php`
- Create: `src/resources/views/landing/wire.blade.php`
- Create: `src/resources/views/landing/closing.blade.php`
- Create: `src/resources/css/landing/_eligibility.scss`
- Create: `src/resources/css/landing/_wire.scss`
- Create: `src/resources/css/landing/_closing.scss`
- Create: `src/resources/css/landing/_footer.scss`
- Modify: `src/resources/views/pages/home.blade.php`
- Modify: `src/resources/views/layouts/landing.blade.php`
- Modify: `src/resources/css/landing.scss`
- Test: `src/tests/Feature/Pages/LandingTest.php`

**Interfaces:**
- Consumes: `$showcase->eligibility()`, `$showcase->announcements()`.
- Produces: `#eligibility`, `landing-wire`, `landing-cta`, footer links, `data-demo-target` footer shortcuts.

- [ ] **Step 1: Extend the feature test**

```php
it('renders the eligibility spotlight, wire, closing CTA, and footer', function () {
    $this->get('/')
        ->assertOk()
        ->assertSeeText('The paperwork is part of the tournament.')
        ->assertSeeText('Consent pending')
        ->assertSeeText('Document missing')
        ->assertSeeText('Entry fee')
        ->assertSeeText('GCash')
        ->assertSeeText('QF4 is live on Court B')
        ->assertSeeText('Your league deserves a better scoreboard.')
        ->assertSeeText('Let the games run themselves.')
        ->assertSee('data-testid="landing-wire"', false)
        ->assertSee('data-testid="landing-cta"', false)
        ->assertSee('data-demo-target="standings"', false);
});
```

- [ ] **Step 2: Run it and confirm it fails**

Run: `cd src; php artisan test tests/Feature/Pages/LandingTest.php --filter="eligibility spotlight"`

Expected: FAIL.

- [ ] **Step 3: Create the eligibility partial**

Create `src/resources/views/landing/eligibility.blade.php`:

```blade
@php
    $cleared = collect($eligibility['players'])->where('status', 'verified')->count();
    $total = count($eligibility['players']);
@endphp

<section id="eligibility" class="hf-lp-elig-section" data-testid="landing-eligibility" aria-labelledby="landing-elig-title">
    <div class="hf-lp-shell hf-lp-elig">
        <div class="hf-lp-elig__copy">
            <span class="hf-eyebrow hf-lp-eyebrow">The unglamorous part, handled</span>
            <h2 id="landing-elig-title">The paperwork is part of the tournament.</h2>
            <p>
                Age and residency checks, guardian consent, and cash or GCash payments are first-class
                steps in HoopFlow. Review them in one queue, with a reason for every decision.
            </p>

            <ul class="hf-lp-elig__checklist">
                <li><i class="bi bi-check2-circle" aria-hidden="true"></i> Age &amp; residency checks</li>
                <li><i class="bi bi-check2-circle" aria-hidden="true"></i> Guardian consent</li>
                <li><i class="bi bi-check2-circle" aria-hidden="true"></i> Cash &amp; GCash references</li>
                <li><i class="bi bi-check2-circle" aria-hidden="true"></i> A reason for every decision</li>
            </ul>
        </div>

        <div class="hf-lp-elig__scene">
            <article class="hf-lp-review">
                <header class="hf-lp-review__head">
                    <div>
                        <span class="hf-lp-review__eyebrow">Roster check</span>
                        <strong>San Isidro Vipers · U16</strong>
                    </div>
                    <span class="hf-lp-review__count">{{ $cleared }} of {{ $total }} cleared</span>
                </header>

                <div class="hf-lp-review__rows">
                    @foreach ($eligibility['players'] as $player)
                        <div class="hf-lp-review__row">
                            <span class="hf-lp-review__player">
                                <strong>{{ $player['name'] }}</strong>
                                <small>Age {{ $player['age'] }}</small>
                            </span>
                            <x-status-chip :status="$player['status']" :label="$player['label']" />
                        </div>
                    @endforeach
                </div>

                <div class="hf-lp-review__progress" aria-hidden="true">
                    <span style="width: {{ ($cleared / max($total, 1)) * 100 }}%"></span>
                </div>
            </article>

            <article class="hf-lp-payment">
                <span class="hf-lp-payment__eyebrow">Payment record</span>
                @foreach ($eligibility['payments'] as $payment)
                    <div class="hf-lp-payment__row">
                        <div>
                            <strong>{{ $payment['label'] }} · {{ $payment['amount'] }}</strong>
                            <span>{{ $payment['method'] }} · {{ $payment['reference'] }}</span>
                        </div>
                        <x-status-chip :status="$payment['status']" :label="$payment['status_label']" />
                    </div>
                @endforeach
            </article>
        </div>
    </div>
</section>
```

- [ ] **Step 4: Create the wire partial**

Create `src/resources/views/landing/wire.blade.php`:

```blade
<section class="hf-lp-wire" data-testid="landing-wire" aria-label="Sample matchday announcements">
    <span class="hf-lp-wire__label"><i class="bi bi-broadcast" aria-hidden="true"></i> Matchday wire</span>

    <div class="hf-lp-wire__viewport">
        <div class="hf-lp-wire__track">
            <div class="hf-lp-wire__group">
                @foreach ($announcements as $announcement)
                    <span class="hf-lp-wire__item">
                        <span class="hf-lp-wire__ball" aria-hidden="true"></span>
                        {{ $announcement }}
                    </span>
                @endforeach
            </div>
            <div class="hf-lp-wire__group" aria-hidden="true">
                @foreach ($announcements as $announcement)
                    <span class="hf-lp-wire__item">
                        <span class="hf-lp-wire__ball"></span>
                        {{ $announcement }}
                    </span>
                @endforeach
            </div>
        </div>
    </div>
</section>
```

- [ ] **Step 5: Create the closing partial**

Create `src/resources/views/landing/closing.blade.php`:

```blade
<section class="hf-lp-closing" data-testid="landing-cta" aria-labelledby="landing-closing-title">
    <div class="hf-lp-closing__court" aria-hidden="true">
        <span></span>
        <span></span>
        <span></span>
    </div>

    <div class="hf-lp-shell hf-lp-closing__inner">
        <span class="hf-eyebrow hf-lp-eyebrow">Ready when you are</span>
        <h2 id="landing-closing-title">Your league deserves a better scoreboard.</h2>
        <p>Set up the tournament once. Let matchday flow.</p>
        <div class="hf-lp-closing__actions">
            <a class="hf-btn hf-btn--primary hf-lp-btn-lg" href="{{ route('register') }}">
                Create your account
                <i class="bi bi-arrow-up-right" aria-hidden="true"></i>
            </a>
            <a class="hf-btn hf-btn--ghost hf-lp-btn-lg" href="#features">Explore the demo</a>
        </div>
    </div>
</section>
```

- [ ] **Step 6: Add the sections to the page**

After the roles include in `pages/home.blade.php`:

```blade
@include('landing.eligibility', ['eligibility' => $showcase->eligibility()])
@include('landing.wire', ['announcements' => $showcase->announcements()])
@include('landing.closing')
```

- [ ] **Step 7: Add the footer to the landing layout**

Before `</body>` in `layouts/landing.blade.php`:

```blade
    <footer class="hf-lp-footer">
        <div class="hf-lp-shell hf-lp-footer__inner">
            <div class="hf-lp-footer__brand">
                <x-brand size="md" />
                <p class="hf-lp-footer__tagline">Let the games run themselves.</p>
                <p class="hf-lp-footer__note">Built for barangay and grassroots youth basketball.</p>
            </div>

            <div class="hf-lp-footer__columns">
                <div class="hf-lp-footer__col">
                    <strong>Product</strong>
                    <a href="#features">Features</a>
                    <a href="#features" data-demo-target="bracket">Bracket</a>
                    <a href="#features" data-demo-target="schedule">Schedule</a>
                    <a href="#features" data-demo-target="standings">Standings</a>
                </div>
                <div class="hf-lp-footer__col">
                    <strong>Account</strong>
                    @guest
                        <a href="{{ route('login') }}">Sign in</a>
                        <a href="{{ route('register') }}">Create account</a>
                    @endguest
                    @auth
                        <a href="{{ route('dashboard') }}">Dashboard</a>
                        <a href="{{ route('profile') }}">Profile</a>
                    @endauth
                </div>
                <div class="hf-lp-footer__col">
                    <strong>Matchday</strong>
                    <a href="#flow">How it flows</a>
                    <a href="#roles">Roles</a>
                    <a href="#eligibility">Eligibility</a>
                </div>
            </div>
        </div>

        <div class="hf-lp-shell hf-lp-footer__bottom">
            <span>© 2026 HoopFlow</span>
            <a href="#main">Back to top <i class="bi bi-arrow-up" aria-hidden="true"></i></a>
        </div>
    </footer>
```

- [ ] **Step 8: Add the styles**

Add `@use "landing/eligibility";`, `@use "landing/wire";`, `@use "landing/closing";`, `@use "landing/footer";` to `landing.scss`.

Create `src/resources/css/landing/_eligibility.scss`:

```scss
.hf-lp-elig-section {
  padding: clamp(72px, 9vw, 120px) 0;
  background: var(--hf-lp-paper);
}

.hf-lp-elig {
  display: grid;
  grid-template-columns: minmax(0, 0.95fr) minmax(0, 1.05fr);
  gap: clamp(32px, 5vw, 72px);
  align-items: center;
}

.hf-lp-elig__copy h2 {
  margin: 10px 0 16px;
  font-family: var(--hf-lp-display);
  font-size: clamp(42px, 5.4vw, 68px);
  font-weight: 800;
  letter-spacing: -0.02em;
  line-height: 0.92;
  text-transform: uppercase;
}

.hf-lp-elig__copy p {
  max-width: 56ch;
  margin: 0;
  color: var(--hf-lp-muted);
  font-size: 15px;
  line-height: 1.65;
}

.hf-lp-elig__checklist {
  display: grid;
  gap: 8px;
  margin: 24px 0 0;
  padding: 0;
  list-style: none;
}

.hf-lp-elig__checklist li {
  display: flex;
  align-items: center;
  gap: 9px;
  font-size: 13px;
  font-weight: 600;
}

.hf-lp-elig__checklist i {
  color: var(--hf-lp-green);
  font-size: 16px;
}

.hf-lp-elig__scene {
  position: relative;
  min-height: 430px;
}

.hf-lp-review {
  position: relative;
  z-index: 2;
  width: min(100%, 430px);
  margin-left: auto;
  padding: 20px;
  border: 1px solid var(--hf-lp-line);
  border-radius: 16px;
  background: var(--hf-lp-card);
  box-shadow: var(--hf-lp-shadow);
  transform: rotate(1.5deg);
}

.hf-lp-review__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  padding-bottom: 12px;
  border-bottom: 1px solid var(--hf-lp-line);
}

.hf-lp-review__head div {
  display: grid;
  gap: 2px;
}

.hf-lp-review__eyebrow {
  color: var(--hf-lp-muted);
  font-size: 9px;
  font-weight: 800;
  letter-spacing: 0.1em;
  text-transform: uppercase;
}

.hf-lp-review__head strong {
  font-size: 13px;
}

.hf-lp-review__count {
  color: var(--hf-lp-orange-ink);
  font-size: 10px;
  font-weight: 800;
  white-space: nowrap;
}

.hf-lp-review__rows {
  display: grid;
}

.hf-lp-review__row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 10px 0;
  border-bottom: 1px solid var(--hf-lp-line);
}

.hf-lp-review__player {
  display: grid;
  gap: 1px;
}

.hf-lp-review__player strong {
  font-size: 12px;
}

.hf-lp-review__player small {
  color: var(--hf-lp-muted);
  font-size: 9px;
}

.hf-lp-review__progress {
  height: 6px;
  margin-top: 14px;
  overflow: hidden;
  border-radius: 999px;
  background: var(--hf-lp-paper-2);
}

.hf-lp-review__progress span {
  display: block;
  height: 100%;
  border-radius: inherit;
  background: linear-gradient(90deg, var(--hf-lp-orange), var(--hf-lp-gold));
}

.hf-lp-payment {
  position: absolute;
  bottom: 10px;
  left: 0;
  z-index: 3;
  display: grid;
  gap: 10px;
  width: min(300px, 78%);
  padding: 16px;
  border: 1px solid rgba(243, 245, 249, 0.14);
  border-radius: 14px;
  background: var(--hf-lp-arena);
  box-shadow: 0 24px 60px rgba(11, 18, 32, 0.28);
  color: var(--hf-lp-arena-ink);
  transform: rotate(-2deg);
}

.hf-lp-payment__eyebrow {
  color: #fdba74;
  font-size: 9px;
  font-weight: 800;
  letter-spacing: 0.12em;
  text-transform: uppercase;
}

.hf-lp-payment__row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 10px;
  padding-top: 9px;
  border-top: 1px solid rgba(243, 245, 249, 0.12);
}

.hf-lp-payment__row div {
  display: grid;
  gap: 2px;
}

.hf-lp-payment__row strong {
  font-size: 10px;
}

.hf-lp-payment__row span:not(.hf-chip) {
  color: var(--hf-lp-arena-muted);
  font-size: 8px;
}

@media (max-width: 991.98px) {
  .hf-lp-elig {
    grid-template-columns: 1fr;
  }

  .hf-lp-elig__scene {
    max-width: 560px;
    width: 100%;
    margin-inline: auto;
  }

  .hf-lp-review {
    margin-inline: auto;
  }
}

@media (max-width: 575.98px) {
  .hf-lp-payment {
    position: relative;
    bottom: auto;
    left: auto;
    width: 100%;
    margin-top: -14px;
    transform: none;
  }
}
```

Create `src/resources/css/landing/_wire.scss`:

```scss
.hf-lp-wire {
  display: flex;
  align-items: stretch;
  overflow: hidden;
  border-block: 1px solid rgba(16, 24, 32, 0.14);
  background: var(--hf-lp-orange);
  color: #1a0f07;
}

.hf-lp-wire__label {
  display: flex;
  align-items: center;
  gap: 7px;
  flex: none;
  padding: 14px 20px;
  background: #1a0f07;
  color: #fff7ed;
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.12em;
  text-transform: uppercase;
}

.hf-lp-wire__viewport {
  overflow: hidden;
}

.hf-lp-wire__track {
  display: flex;
  width: max-content;
  animation: hf-lp-marquee 44s linear infinite;
}

.hf-lp-wire:hover .hf-lp-wire__track,
.hf-lp-wire:focus-within .hf-lp-wire__track {
  animation-play-state: paused;
}

.hf-lp-wire__group {
  display: flex;
  align-items: center;
  gap: 26px;
  padding: 14px 20px;
}

.hf-lp-wire__item {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  color: #1a0f07;
  font-size: 12px;
  font-weight: 600;
  white-space: nowrap;
}

.hf-lp-wire__ball {
  width: 9px;
  height: 9px;
  border: 2px solid currentColor;
  border-radius: 50%;
}

@keyframes hf-lp-marquee {
  to { transform: translateX(-50%); }
}

@media (max-width: 767.98px) {
  .hf-lp-wire {
    display: block;
  }

  .hf-lp-wire__label {
    width: max-content;
  }
}

@media (prefers-reduced-motion: reduce) {
  .hf-lp-wire__track {
    width: auto;
    animation: none;
  }

  .hf-lp-wire__viewport {
    overflow: visible;
  }

  .hf-lp-wire__track,
  .hf-lp-wire__group {
    flex-wrap: wrap;
  }

  .hf-lp-wire__group[aria-hidden='true'] {
    display: none;
  }

  .hf-lp-wire__item {
    white-space: normal;
  }
}
```

Create `src/resources/css/landing/_closing.scss`:

```scss
.hf-lp-closing {
  position: relative;
  overflow: hidden;
  padding: clamp(84px, 11vw, 150px) 0;
  background:
    radial-gradient(circle at 20% 20%, rgba(234, 88, 12, 0.24), transparent 38%),
    var(--hf-lp-arena);
  color: var(--hf-lp-arena-ink);
  text-align: center;
}

.hf-lp-closing__court {
  position: absolute;
  inset: 0;
  opacity: 0.35;
}

.hf-lp-closing__court span {
  position: absolute;
  border: 1px solid rgba(243, 245, 249, 0.14);
  border-radius: 50%;
}

.hf-lp-closing__court span:nth-child(1) {
  top: 50%;
  left: 50%;
  width: 340px;
  height: 340px;
  transform: translate(-50%, -50%);
}

.hf-lp-closing__court span:nth-child(2) {
  top: -20%;
  left: 10%;
  width: 420px;
  height: 420px;
}

.hf-lp-closing__court span:nth-child(3) {
  right: 8%;
  bottom: -30%;
  width: 380px;
  height: 380px;
}

.hf-lp-closing__inner {
  position: relative;
  z-index: 1;
}

.hf-lp-closing .hf-lp-eyebrow {
  color: #fdba74;
}

.hf-lp-closing h2 {
  max-width: 900px;
  margin: 14px auto 16px;
  font-family: var(--hf-lp-display);
  font-size: clamp(48px, 8vw, 104px);
  font-weight: 800;
  letter-spacing: -0.025em;
  line-height: 0.86;
  text-transform: uppercase;
}

.hf-lp-closing p {
  margin: 0 auto;
  color: var(--hf-lp-arena-muted);
  font-size: 16px;
}

.hf-lp-closing__actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 10px;
  margin-top: 28px;
}

.hf-lp-closing .hf-btn--ghost {
  border-color: rgba(243, 245, 249, 0.2);
  color: var(--hf-lp-arena-ink);
}

.hf-lp-closing .hf-btn--ghost:hover {
  border-color: rgba(243, 245, 249, 0.4);
  background: rgba(243, 245, 249, 0.08);
}
```

Create `src/resources/css/landing/_footer.scss`:

```scss
.hf-lp-footer {
  padding: 56px 0 24px;
  border-top: 1px solid var(--hf-lp-line);
  background: var(--hf-lp-paper);
}

.hf-lp-footer__inner {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: 48px;
}

.hf-lp-footer__brand {
  max-width: 340px;
}

.hf-lp-footer__tagline {
  margin: 16px 0 4px;
  font-family: var(--hf-lp-display);
  font-size: 24px;
  font-weight: 700;
  text-transform: uppercase;
}

.hf-lp-footer__note {
  margin: 0;
  color: var(--hf-lp-muted);
  font-size: 12px;
  line-height: 1.5;
}

.hf-lp-footer__columns {
  display: flex;
  gap: clamp(28px, 5vw, 72px);
}

.hf-lp-footer__col {
  display: grid;
  align-content: start;
  gap: 8px;
}

.hf-lp-footer__col strong {
  margin-bottom: 4px;
  color: var(--hf-lp-muted);
  font-size: 9px;
  font-weight: 800;
  letter-spacing: 0.12em;
  text-transform: uppercase;
}

.hf-lp-footer__col a {
  color: var(--hf-lp-ink);
  font-size: 12px;
  text-decoration: none;
}

.hf-lp-footer__col a:hover {
  color: var(--hf-lp-orange-ink);
  text-decoration: none;
}

.hf-lp-footer__bottom {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  margin-top: 40px;
  padding-top: 16px;
  border-top: 1px solid var(--hf-lp-line);
  color: var(--hf-lp-muted);
  font-size: 10px;
}

.hf-lp-footer__bottom a {
  color: var(--hf-lp-muted);
  text-decoration: none;
}

.hf-lp-footer__bottom a:hover {
  color: var(--hf-lp-ink);
}

@media (max-width: 991.98px) {
  .hf-lp-footer__inner {
    grid-template-columns: 1fr;
    gap: 32px;
  }
}

@media (max-width: 575.98px) {
  .hf-lp-footer__columns {
    flex-wrap: wrap;
    gap: 28px;
  }
}
```

- [ ] **Step 9: Build and test**

```powershell
cd src
npm run build
php artisan test tests/Feature/Pages/LandingTest.php
```

Expected: build succeeds; 6 feature tests pass.

- [ ] **Step 10: Browser-check the final page**

- Eligibility cards overlap on desktop but stack without clipping on mobile.
- Status chips have text and icons, not color alone.
- The wire scrolls, pauses on hover/focus, and becomes a readable wrapped list under reduced motion.
- Closing CTA buttons are readable on the dark background.
- Footer links open the right anchors; Bracket/Schedule/Standings activate the matching lab tab.
- No console errors.

- [ ] **Step 11: Commit**

```powershell
cd src
./vendor/bin/pint --test
git add resources/css resources/views/landing resources/views/layouts/landing.blade.php resources/views/pages/home.blade.php tests/Feature/Pages/LandingTest.php
git commit -m "feat(landing): add eligibility, wire, closing, and footer"
```

## Task 7: Polish, accessibility, and full verification

**Files:**
- Modify: `src/resources/css/landing/_base.scss`
- Modify: `src/resources/css/landing/_header.scss`
- Modify: `src/resources/css/landing/_hero.scss`
- Modify: `src/resources/css/landing/_lab.scss`
- Modify: `src/resources/css/landing/_standings.scss`
- Modify: `src/resources/css/landing/_wire.scss`
- Modify: `src/resources/js/landing/*.js`
- Test: `src/tests/Feature/Pages/LandingTest.php`

**Interfaces:**
- No new public interfaces. This task closes accessibility, responsive, theme, and motion gaps.

- [ ] **Step 1: Add the remaining feature assertions**

Append:

```php
it('exposes the landing anchors, theme toggle, and read-only tab semantics', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('id="features"', false)
        ->assertSee('id="flow"', false)
        ->assertSee('id="roles"', false)
        ->assertSee('id="eligibility"', false)
        ->assertSee('role="tablist"', false)
        ->assertSee('aria-selected="true"', false)
        ->assertSee('aria-controls="panel-bracket"', false)
        ->assertSee('data-theme-toggle', false)
        ->assertSee('aria-label="Toggle color theme"', false);
});
```

- [ ] **Step 2: Run the test**

Run: `cd src; php artisan test tests/Feature/Pages/LandingTest.php`

Expected: PASS. If any assertion fails, fix the markup rather than weakening the test.

- [ ] **Step 3: Close the accessibility and motion gaps**

Apply these exact checks and fixes:

- Add `:focus-visible` treatment for `.hf-lp-tab`, `.hf-lp-chip`, `.hf-lp-menu-btn`, `.hf-lp-footer a`, and `.hf-lp-wire` links:
  ```scss
  .hf-lp-tab:focus-visible,
  .hf-lp-chip:focus-visible,
  .hf-lp-menu-btn:focus-visible,
  .hf-lp-footer a:focus-visible {
    outline: 3px solid var(--hf-lp-orange);
    outline-offset: 2px;
  }
  ```
- Ensure every `.hf-lp-reveal` is visible under `prefers-reduced-motion` (the JS adds `is-in`; CSS must not hide it if JS is disabled because `.hf-js` is only added by the inline script).
- Ensure the banner marquee stops under reduced motion (already in `_wire.scss`) and that the duplicate group is hidden.
- Ensure the dark theme keeps the following readable: header, hero title accent, feature lab, schedule, standings, eligibility cards, closing, footer. Add dark overrides only where a paper token leaked:
  ```scss
  [data-theme='dark'] .hf-lp-review,
  [data-theme='dark'] .hf-lp-nextup,
  [data-theme='dark'] .hf-lp-role {
    border-color: var(--hf-lp-line);
    background: var(--hf-lp-card);
  }
  ```
- Add `overflow: clip` to `.hf-lp-hero` and `.hf-lp-elig-section` so rotated cards cannot create page-level horizontal scroll. Do **not** put `overflow-x` on `body` or `.hf-lp`; it can break the sticky header.
- Ensure each section has `scroll-margin-top: 96px` (already global for `.hf-lp section[id]`; verify `#features` is on the lab section).
- Verify every tap target in the header, tabs, chips, and CTAs is at least 44×44 px. The header buttons use `.hf-btn--sm` (36 px); add:
  ```scss
  .hf-lp .hf-btn--sm {
    min-height: 44px;
  }
  ```
- Confirm no text relies on color alone: status chips include icons, standings qualification includes a gold edge plus the cut-line label, and the live state includes the word `Live`.

- [ ] **Step 4: Run the responsive and theme matrix**

Use the in-app browser at these viewports:

| Width | Check |
|---|---|
| 1440 | Hero two columns; lab rail + stage; bracket fully visible; footer columns |
| 1180 | Hero still two columns; lab rail narrows without overlap |
| 992 | Hero starts stacking; lab tabs above stage; role cards two columns |
| 768 | Single-column hero; bracket horizontal scroll inside stage; no page overflow |
| 390 | Header menu; stacked hero; bracket/standings usable; footer stacked |

Run each check in light and dark themes. For each, verify:

- No horizontal page scrollbar (`document.documentElement.scrollWidth <= window.innerWidth`).
- No console errors.
- Header theme toggle updates the icon and persists after reload.
- Tab keyboard navigation: Right/Down moves forward, Left/Up moves backward, Home/End jump.
- Schedule filters keep the empty state accurate.
- Reduced-motion emulation: no marquee movement, no float, no count-up animation, no ping pulse.

- [ ] **Step 5: Run the full quality gate**

```powershell
cd src
npm run build
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
php artisan test
```

Expected: build succeeds; Pint reports no violations; PHPStan reports no errors; all tests pass. Record the actual output in the final report.

- [ ] **Step 6: Commit the polish**

```powershell
cd src
git add resources/css resources/js resources/views tests/Feature/Pages/LandingTest.php
git commit -m "fix(landing): polish responsive, theme, and accessibility"
```

## Spec coverage check

| Spec requirement | Task |
|---|---|
| Sticky header, nav, theme toggle, mobile menu | 2 |
| Hero copy, CTAs, live scoreboard, next-up, stamp, court art | 2 |
| Feature lab shell, read-only badge, tabs | 3 |
| Bracket demo, live quarterfinal, connectors, mobile scroll | 3 |
| Schedule day/court filters, empty state | 4 |
| Standings table, top-8 cut, count-up, mobile cards | 4 |
| Five-step matchday flow | 5 |
| Organizer/coach/fan role cards | 5 |
| Eligibility queue and payment records | 6 |
| Matchday wire marquee and reduced motion | 6 |
| Closing CTA and footer | 6 |
| Light/dark themes | 2, 7 |
| Responsive breakpoints, no overflow | 2–7 |
| Accessibility, keyboard tabs, reduced motion | 3, 4, 7 |
| Curated showcase data and invariants | 1 |
| Feature/unit tests | 1–7 |
| Build, Pint, PHPStan, Pest | 7 |

## Plan self-review

- **Placeholder scan:** no TBD/TODO steps; the only intentional intermediate comments are in Task 3 and are explicitly replaced in Task 4.
- **Type consistency:** `LandingShowcase` methods and match keys are defined in Task 1 and used unchanged in Tasks 2–6. Tab hooks (`data-demo-tab`/`data-demo-panel`), schedule hooks, and count-up hooks are introduced before their scripts.
- **Scope:** one landing page, one controller, one data class, section partials, two Vite entries. No database or auth changes.
- **Risk:** the `@use "bootstrap"` resolution assumes the local `_bootstrap.scss` is found before the npm package. If Sass resolves the package instead, change the local file to `_hf-bootstrap.scss` and use `@use "hf-bootstrap"` in both entries.

## Execution handoff

Plan complete and saved to `docs/superpowers/plans/2026-11-11-hoopflow-landing-page.md`.

Two execution options:

1. **Subagent-Driven (recommended)** — dispatch a fresh subagent per task, review between tasks, fast iteration.
2. **Inline Execution** — execute tasks in this session using the executing-plans skill, with checkpoints for review.



