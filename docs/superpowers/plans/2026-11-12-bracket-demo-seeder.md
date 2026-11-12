# Bracket Demo Seeder Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a destructive demo seeder that builds three unplayed tournaments — single elimination, double elimination, and round robin — through the real bracket and scheduling engines.

**Architecture:** `BracketDemoSeeder` ensures the base organization and organizer exist, purges tournament-scoped data, creates 16 shared teams with rosters, then creates and registers three tournaments. `BracketService` generates each bracket and `SchedulingService` publishes each schedule. A Pest feature test verifies the seeded shapes, series settings, and schedules.

**Tech Stack:** Laravel 13, PHP 8.4, Pest, Eloquent, PostgreSQL/SQLite, `BracketService`, `SchedulingService`.

## Global Constraints

- Work in `src/`.
- Do not modify `PrototypeSeeder`, `DatabaseSeeder`, or production bracket/scheduling code.
- The demo seeder is destructive and must not be registered in `DatabaseSeeder`.
- Tests run with `php artisan test`; the quality gate is `composer quality`.
- Run `composer quality` with `$env:COMPOSER_PROCESS_TIMEOUT='900'`; the default Composer timeout is shorter than the full suite.
- Do not commit `.env`, `.obsidian/`, or `.kilo/`.

---

### Task 1: Seeder skeleton and tournament data

**Files:**
- Create: `src/database/seeders/BracketDemoSeeder.php`
- Create: `src/tests/Feature/Database/BracketDemoSeederTest.php`

**Interfaces:**
- Consumes: `OrganizationSeeder`, `RoleSeeder`, `DefaultOrganizerSeeder`; Eloquent models `Organization`, `User`, `Team`, `Player`, `EligibilityDocument`, `Tournament`, `Division`, `GameRule`, `Venue`, `Court`, `Registration`, `Payment`.
- Produces: `BracketDemoSeeder::run()` with three active tournaments and 16 approved registrations in each elimination tournament and 8 in round robin. No brackets or matches yet.

- [ ] **Step 1: Write the failing test**

Create `src/tests/Feature/Database/BracketDemoSeederTest.php`:

```php
<?php

use App\Models\Player;
use App\Models\Team;
use App\Models\Tournament;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'BracketDemoSeeder'])->assertExitCode(0);
});

it('seeds three demo tournaments with teams and approved registrations', function () {
    expect(Tournament::count())->toBe(3)
        ->and(Team::count())->toBe(16)
        ->and(Player::count())->toBe(160);

    $counts = Tournament::query()
        ->withCount(['registrations as approved_registrations_count' => fn ($query) => $query->where('status', 'approved')])
        ->get()
        ->pluck('approved_registrations_count', 'slug');

    expect($counts['demo-single-elimination'])->toBe(16)
        ->and($counts['demo-double-elimination'])->toBe(16)
        ->and($counts['demo-round-robin'])->toBe(8);
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run:

```powershell
php artisan test tests/Feature/Database/BracketDemoSeederTest.php --compact
```

Expected: FAIL with `Class "Database\Seeders\BracketDemoSeeder" not found`.

- [ ] **Step 3: Create the seeder skeleton**

Create `src/database/seeders/BracketDemoSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Models\Court;
use App\Models\Division;
use App\Models\EligibilityDocument;
use App\Models\GameRule;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Player;
use App\Models\Registration;
use App\Models\Role;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class BracketDemoSeeder extends Seeder
{
    /** @var list<array{0: string, 1: string, 2: string, 3: string, 4: string}> */
    protected array $demoTeams = [
        ['Pasig Pirates', 'PSG', 'Alvin Cruz', 'Pasig City', '#0e7490'],
        ['Kapitolyo Kings', 'KNG', 'Bong Reyes', 'Pasig City', '#1d4ed8'],
        ['Ugong United', 'UGU', 'Carlo Santos', 'Pasig City', '#047857'],
        ['Rosario Rockets', 'RCK', 'Dante Lim', 'Pasig City', '#b91c1c'],
        ['Maybunga Mavericks', 'MVR', 'Elmer Tan', 'Pasig City', '#7e22ce'],
        ['Pinagbuhatan Pilots', 'PLT', 'Francisco Diaz', 'Pasig City', '#475569'],
        ['Bagong Ilog Bears', 'BRS', 'Glen Aquino', 'Pasig City', '#a16207'],
        ['Kalawaan Kestrels', 'KST', 'Hector Navarro', 'Pasig City', '#0f766e'],
        ['San Antonio Vipers', 'VPR', 'Marcus Rivera', 'Pasig City', '#ea580c'],
        ['Mandaluyong Tigers', 'TGR', 'Ramon Bautista', 'Mandaluyong City', '#d97706'],
        ['Ortigas Raptors', 'RPT', 'Kenneth Cruz', 'Pasig City', '#7c3aed'],
        ['Ugong Blue Hawks', 'HWK', 'Allan Gomez', 'Pasig City', '#0284c7'],
        ['Dela Paz Dragons', 'DPZ', 'Marlon Lim', 'Pasig City', '#e11d48'],
        ['Caniogan Cardinals', 'CNG', 'Boyet Tan', 'Pasig City', '#be123c'],
        ['Sumilang Spartans', 'SMS', 'Dennis Pascual', 'Pasig City', '#334155'],
        ['Santolan Saints', 'STL', 'Arnel Garcia', 'Pasig City', '#16a34a'],
    ];

    protected ?Organization $org = null;

    protected ?User $organizer = null;

    public function run(): void
    {
        $this->call([OrganizationSeeder::class, RoleSeeder::class, DefaultOrganizerSeeder::class]);

        $this->org = Organization::where('slug', 'default')->firstOrFail();
        $this->organizer = $this->ensureOrganizer();

        DB::transaction(function (): void {
            $this->purgeDemoData();

            $teams = $this->seedTeams();

            $this->seedSingleElimination($teams);
            $this->seedDoubleElimination($teams);
            $this->seedRoundRobin(array_slice($teams, 0, 8));
        });
    }

    protected function ensureOrganizer(): User
    {
        $user = User::updateOrCreate(
            ['email' => 'organizer@hoopflow.ph'],
            [
                'first_name' => 'Jeward',
                'last_name' => 'Mahinay',
                'password' => Hash::make('password'),
                'status' => 'active',
            ],
        );

        $roleId = Role::where('key', 'organizer')->value('id');

        if ($roleId !== null) {
            DB::table('user_roles')->updateOrInsert(
                ['user_id' => $user->id, 'role_id' => $roleId, 'scope' => null],
                ['organization_id' => $this->org->id, 'created_at' => now(), 'updated_at' => now()],
            );
        }

        return $user;
    }

    protected function purgeDemoData(): void
    {
        foreach ([
            'player_stat_quarters',
            'player_stats',
            'match_officials',
            'match_slots',
            'bracket_slots',
            'matches',
            'brackets',
            'payments',
            'registrations',
            'announcements',
            'audit_logs',
            'game_rules',
            'divisions',
            'court_blackouts',
            'courts',
            'venues',
            'tournaments',
            'team_memberships',
            'eligibility_documents',
            'players',
            'teams',
        ] as $table) {
            DB::table($table)->delete();
        }
    }

    /**
     * @return list<Team>
     */
    protected function seedTeams(): array
    {
        $teams = [];

        foreach ($this->demoTeams as $index => [$name, $short, $coach, $city, $color]) {
            $team = Team::create([
                'organization_id' => $this->org->id,
                'name' => $name,
                'short_name' => $short,
                'slug' => strtolower($short).'-demo',
                'avatar_color' => $color,
                'city' => $city,
                'coach_name' => $coach,
                'logo_url' => null,
                'coach_user_id' => null,
            ]);

            $this->seedRoster($team, $index);
            $teams[] = $team;
        }

        return $teams;
    }

    protected function seedRoster(Team $team, int $teamIndex): void
    {
        $first = ['Jayson', 'Kobe', 'Carl', 'Kai', 'June Mar', 'Gabe', 'CJ', 'Dwight', 'Calvin', 'RR', 'Marc', 'Prince', 'Andre', 'Jolo', 'Troy', 'Luis'];
        $last = ['Castro', 'Ramos', 'Cruz', 'Gomez', 'Fajardo', 'Diaz', 'Santos', 'Reyes', 'Bautista', 'Villanueva', 'Macaraeg', 'Torres', 'Aquino', 'Navarro', 'Mendoza', 'Del Rosario'];
        $positions = ['Point Guard (PG)', 'Shooting Guard (SG)', 'Small Forward (SF)', 'Power Forward (PF)', 'Center (C)'];

        for ($i = 0; $i < 10; $i++) {
            $player = Player::create([
                'team_id' => $team->id,
                'first_name' => $first[($teamIndex * 7 + $i) % count($first)],
                'last_name' => $last[($teamIndex * 3 + $i) % count($last)],
                'birthdate' => now()->subYears(14)->subDays($i)->format('Y-m-d'),
                'jersey_number' => $i + 1,
                'position' => $positions[$i % count($positions)],
                'photo_url' => null,
                'guardian_name' => null,
                'guardian_phone' => null,
                'consent_at' => now(),
                'consent_status' => 'verified',
                'consent_recorded_by' => $this->organizer->id,
                'consent_recorded_at' => now(),
                'consent_version' => '1.0',
                'eligibility_status' => 'verified',
                'verified_by' => $this->organizer->id,
                'verified_at' => now(),
            ]);

            EligibilityDocument::create([
                'player_id' => $player->id,
                'type' => 'birth_certificate',
                'file_url' => 'https://example.com/demo/birth-certificate/'.$player->id,
                'status' => 'approved',
                'reviewed_by' => $this->organizer->id,
                'reviewed_at' => now(),
                'notes' => null,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function createTournament(array $data): Tournament
    {
        $data = array_merge([
            'description' => 'Demo tournament generated by BracketDemoSeeder.',
            'round_robin_legs' => 1,
            'playoff_teams' => 0,
            'best_of_quarterfinal' => 1,
            'best_of_semifinal' => 1,
            'best_of_final' => 1,
        ], $data);

        $tournament = Tournament::create([
            'organization_id' => $this->org->id,
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'],
            'start_date' => $data['start'],
            'end_date' => $data['end'],
            'status' => 'active',
            'payment_method' => 'GCash',
            'payment_account' => '09489666136',
            'rules_json' => [
                'slots' => $data['slots'],
                'fee' => $data['fee'],
                'format' => $data['format'],
                'round_robin_legs' => $data['round_robin_legs'],
                'playoff_teams' => $data['playoff_teams'],
                'best_of_quarterfinal' => $data['best_of_quarterfinal'],
                'best_of_semifinal' => $data['best_of_semifinal'],
                'best_of_final' => $data['best_of_final'],
            ],
            'created_by' => $this->organizer->id,
        ]);

        $division = Division::create([
            'tournament_id' => $tournament->id,
            'name' => $data['division'],
            'age_min' => null,
            'age_max' => $data['age_max'],
            'gender' => null,
            'max_teams' => $data['slots'],
            'entry_fee' => $data['fee'],
            'currency' => 'PHP',
        ]);

        GameRule::create([
            'tournament_id' => $tournament->id,
            'division_id' => $division->id,
            'quarter_minutes' => 10,
            'overtime_minutes' => 5,
            'foul_limit' => 5,
            'rest_minutes' => 15,
            'max_games_per_day' => null,
            'tiebreakers_json' => null,
        ]);

        $venue = Venue::create([
            'organization_id' => $this->org->id,
            'name' => $data['venue'],
            'address' => null,
            'timezone' => 'Asia/Manila',
        ]);

        foreach (range(1, 4) as $courtNumber) {
            Court::create([
                'venue_id' => $venue->id,
                'name' => 'Court '.$courtNumber,
                'notes' => null,
            ]);
        }

        $rules = $tournament->rules_json;
        $rules['venue_id'] = $venue->id;
        $tournament->rules_json = $rules;
        $tournament->save();
        $tournament->refresh();

        return $tournament;
    }

    /**
     * @param  list<Team>  $teams
     */
    protected function registerTeams(Tournament $tournament, array $teams, int $fee): void
    {
        $division = $tournament->divisions()->firstOrFail();

        foreach ($teams as $index => $team) {
            $registration = Registration::create([
                'tournament_id' => $tournament->id,
                'division_id' => $division->id,
                'team_id' => $team->id,
                'status' => 'approved',
                'submitted_at' => now(),
                'decided_by' => $this->organizer->id,
                'decided_at' => now(),
                'rejection_reason' => null,
                'seed' => $index + 1,
                'roster_locked_at' => null,
            ]);

            Payment::create([
                'registration_id' => $registration->id,
                'amount' => $fee,
                'currency' => 'PHP',
                'method' => 'GCash',
                'reference_no' => 'DEMO-'.strtoupper((string) $tournament->slug).'-'.($index + 1),
                'receipt_no' => null,
                'status' => 'verified',
                'paid_at' => now(),
                'recorded_by' => $this->organizer->id,
                'remarks' => null,
                'refunded_amount' => 0,
                'refunded_at' => null,
            ]);
        }
    }

    /**
     * @param  list<Team>  $teams
     */
    protected function seedSingleElimination(array $teams): void
    {
        $tournament = $this->createTournament([
            'name' => 'HoopFlow Single Elimination Showcase',
            'slug' => 'demo-single-elimination',
            'start' => '2026-12-01',
            'end' => '2026-12-04',
            'format' => 'single-elimination',
            'division' => 'Under 17 (U17)',
            'age_max' => 17,
            'slots' => 16,
            'fee' => 1500,
            'venue' => 'HoopFlow North Arena',
        ]);

        $this->registerTeams($tournament, $teams, 1500);
    }

    /**
     * @param  list<Team>  $teams
     */
    protected function seedDoubleElimination(array $teams): void
    {
        $tournament = $this->createTournament([
            'name' => 'HoopFlow Double Elimination Showcase',
            'slug' => 'demo-double-elimination',
            'start' => '2026-12-08',
            'end' => '2026-12-16',
            'format' => 'double-elimination',
            'division' => 'Under 17 (U17)',
            'age_max' => 17,
            'slots' => 16,
            'fee' => 1500,
            'venue' => 'HoopFlow South Arena',
            'best_of_quarterfinal' => 3,
            'best_of_semifinal' => 3,
            'best_of_final' => 3,
        ]);

        $this->registerTeams($tournament, $teams, 1500);
    }

    /**
     * @param  list<Team>  $teams
     */
    protected function seedRoundRobin(array $teams): void
    {
        $tournament = $this->createTournament([
            'name' => 'HoopFlow Round Robin Showcase',
            'slug' => 'demo-round-robin',
            'start' => '2026-12-15',
            'end' => '2026-12-21',
            'format' => 'round-robin',
            'division' => 'Under 15 (U15)',
            'age_max' => 15,
            'slots' => 8,
            'fee' => 1000,
            'venue' => 'HoopFlow Central Arena',
            'best_of_semifinal' => 3,
            'best_of_final' => 3,
            'playoff_teams' => 4,
        ]);

        $this->registerTeams($tournament, $teams, 1000);
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run:

```powershell
php artisan test tests/Feature/Database/BracketDemoSeederTest.php --compact
```

Expected: PASS with 1 test.

- [ ] **Step 5: Commit**

```powershell
git add database/seeders/BracketDemoSeeder.php tests/Feature/Database/BracketDemoSeederTest.php
git commit -m "feat(demo): seed three tournament formats with teams"
```

---

### Task 2: Generate brackets through BracketService

**Files:**
- Modify: `src/database/seeders/BracketDemoSeeder.php`
- Modify: `src/tests/Feature/Database/BracketDemoSeederTest.php`

**Interfaces:**
- Consumes: `App\Services\BracketService::generate(Tournament $tournament, string $format): array{matches: int, games: int, byes: int}`.
- Produces: three brackets with the shapes described below. No schedules yet.

- [ ] **Step 1: Write the failing bracket tests**

Add the `Bracket` and `MatchModel` imports at the top of the test file:

```php
use App\Models\Bracket;
use App\Models\MatchModel;
```

Append to `src/tests/Feature/Database/BracketDemoSeederTest.php`:

```php
it('generates a 15-match single-elimination bracket', function () {
    $tournament = Tournament::where('slug', 'demo-single-elimination')->firstOrFail();
    $bracket = Bracket::where('division_id', $tournament->divisions()->value('id'))->firstOrFail();

    expect($bracket->format)->toBe('single_elim')
        ->and($bracket->phase)->toBe('main')
        ->and(MatchModel::where('bracket_id', $bracket->id)->count())->toBe(15)
        ->and(MatchModel::where('bracket_id', $bracket->id)->where('status', 'scheduled')->count())->toBe(15);
});

it('generates a 30-anchor double-elimination bracket with best-of-three series and no reset', function () {
    $tournament = Tournament::where('slug', 'demo-double-elimination')->firstOrFail();
    $bracket = Bracket::where('division_id', $tournament->divisions()->value('id'))->firstOrFail();

    expect($bracket->format)->toBe('double_elim')
        ->and(MatchModel::where('bracket_id', $bracket->id)->whereNull('series_id')->count())->toBe(30)
        ->and(MatchModel::where('bracket_id', $bracket->id)->whereNull('series_id')->where('best_of', 3)->count())->toBe(8)
        ->and(MatchModel::where('bracket_id', $bracket->id)->whereNotNull('series_id')->count())->toBe(16)
        ->and(MatchModel::where('bracket_id', $bracket->id)->where('stage', 'reset')->count())->toBe(0)
        ->and(MatchModel::where('bracket_id', $bracket->id)->where('stage', 'grand_final')->whereNull('series_id')->value('best_of'))->toBe(3);
});

it('generates a complete 7-round round-robin group', function () {
    $tournament = Tournament::where('slug', 'demo-round-robin')->firstOrFail();
    $bracket = Bracket::where('division_id', $tournament->divisions()->value('id'))->firstOrFail();
    $matches = MatchModel::where('bracket_id', $bracket->id)->get();

    expect($bracket->format)->toBe('round_robin')
        ->and($bracket->phase)->toBe('group')
        ->and($matches)->toHaveCount(28)
        ->and($matches->pluck('round_number')->unique()->sort()->values()->all())->toBe(range(1, 7))
        ->and($matches->whereNull('home_team_id'))->toHaveCount(0)
        ->and($matches->whereNull('away_team_id'))->toHaveCount(0)
        ->and($matches->where('status', 'scheduled'))->toHaveCount(28);
});

it('leaves round-robin playoffs ungenerated', function () {
    $tournament = Tournament::where('slug', 'demo-round-robin')->firstOrFail();

    expect($tournament->ruleValue('playoff_teams'))->toBe(4)
        ->and(Bracket::where('division_id', $tournament->divisions()->value('id'))->where('phase', 'playoffs')->exists())->toBeFalse();
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run:

```powershell
php artisan test tests/Feature/Database/BracketDemoSeederTest.php --compact
```

Expected: FAIL because the bracket tests find no brackets.

- [ ] **Step 3: Add bracket generation**

Add this import to `src/database/seeders/BracketDemoSeeder.php`:

```php
use App\Services\BracketService;
```

Add one line after each `$this->registerTeams(...)` call:

```php
app(BracketService::class)->generate($tournament, 'single-elimination');
```

```php
app(BracketService::class)->generate($tournament, 'double-elimination');
```

```php
app(BracketService::class)->generate($tournament, 'round-robin');
```

The three methods should end like this:

```php
$this->registerTeams($tournament, $teams, 1500);
app(BracketService::class)->generate($tournament, 'single-elimination');
```

```php
$this->registerTeams($tournament, $teams, 1500);
app(BracketService::class)->generate($tournament, 'double-elimination');
```

```php
$this->registerTeams($tournament, $teams, 1000);
app(BracketService::class)->generate($tournament, 'round-robin');
```

- [ ] **Step 4: Run the tests to verify they pass**

Run:

```powershell
php artisan test tests/Feature/Database/BracketDemoSeederTest.php --compact
```

Expected: PASS with 5 tests.

- [ ] **Step 5: Commit**

```powershell
git add database/seeders/BracketDemoSeeder.php tests/Feature/Database/BracketDemoSeederTest.php
git commit -m "feat(demo): generate demo brackets through the bracket engine"
```

---

### Task 3: Generate schedules through SchedulingService

**Files:**
- Modify: `src/database/seeders/BracketDemoSeeder.php`
- Modify: `src/tests/Feature/Database/BracketDemoSeederTest.php`

**Interfaces:**
- Consumes: `App\Services\SchedulingService::generate(Tournament $tournament, array $options = []): int`.
- Produces: 15 single-elimination slots, 30 double-elimination slots, and 28 round-robin slots. Conditional series games have no slots.

- [ ] **Step 1: Write the failing scheduling test**

Append to `src/tests/Feature/Database/BracketDemoSeederTest.php`:

```php
it('schedules every playable game and no conditional series game', function () {
    $expected = [
        'demo-single-elimination' => 15,
        'demo-double-elimination' => 30,
        'demo-round-robin' => 28,
    ];

    foreach ($expected as $slug => $slotCount) {
        $tournament = Tournament::where('slug', $slug)->firstOrFail();

        expect(MatchSlot::where('tournament_id', $tournament->id)->count())->toBe($slotCount);
    }

    $conditional = MatchModel::where('status', 'conditional')->pluck('id');

    expect($conditional)->not->toBeEmpty()
        ->and(MatchSlot::whereIn('match_id', $conditional)->count())->toBe(0);
});
```

Add the `MatchSlot` import at the top of the test file:

```php
use App\Models\MatchSlot;
```

- [ ] **Step 2: Run the test to verify it fails**

Run:

```powershell
php artisan test tests/Feature/Database/BracketDemoSeederTest.php --compact
```

Expected: FAIL because no match slots exist.

- [ ] **Step 3: Add schedule generation**

Add this import to `src/database/seeders/BracketDemoSeeder.php`:

```php
use App\Services\SchedulingService;
```

Add one line after each `app(BracketService::class)->generate(...)` call:

```php
app(SchedulingService::class)->generate($tournament);
```

The three methods should end like this:

```php
$this->registerTeams($tournament, $teams, 1500);
app(BracketService::class)->generate($tournament, 'single-elimination');
app(SchedulingService::class)->generate($tournament);
```

```php
$this->registerTeams($tournament, $teams, 1500);
app(BracketService::class)->generate($tournament, 'double-elimination');
app(SchedulingService::class)->generate($tournament);
```

```php
$this->registerTeams($tournament, $teams, 1000);
app(BracketService::class)->generate($tournament, 'round-robin');
app(SchedulingService::class)->generate($tournament);
```

- [ ] **Step 4: Run the tests to verify they pass**

Run:

```powershell
php artisan test tests/Feature/Database/BracketDemoSeederTest.php --compact
```

Expected: PASS with 6 tests.

- [ ] **Step 5: Commit**

```powershell
git add database/seeders/BracketDemoSeeder.php tests/Feature/Database/BracketDemoSeederTest.php
git commit -m "feat(demo): schedule demo tournaments through the scheduling engine"
```

---

### Task 4: Local demo verification and quality gate

**Files:**
- Modify: any file needed to fix failures exposed by the full suite or quality gate.

**Interfaces:**
- No new interfaces.

- [ ] **Step 1: Run the demo seeder against the local database**

Run:

```powershell
php artisan db:seed --class=BracketDemoSeeder
```

Expected: exit code 0. This is destructive and replaces the local tournament data.

- [ ] **Step 2: Verify the seeded counts**

Run:

```powershell
php artisan tinker --execute='echo App\Models\Tournament::count()." tournaments".PHP_EOL; echo App\Models\Team::count()." teams".PHP_EOL; echo App\Models\MatchModel::whereNull("series_id")->count()." anchors".PHP_EOL; echo App\Models\MatchModel::whereNotNull("series_id")->count()." series games".PHP_EOL; echo App\Models\MatchSlot::count()." slots".PHP_EOL;'
```

Expected: `3 tournaments`, `16 teams`, `73 anchors`, `16 series games`, `73 slots`.

- [ ] **Step 3: Verify each bracket page renders**

Open the organizer bracket page for each tournament:

- `/bracket?tournament=<single-elimination-id>`
- `/bracket?tournament=<double-elimination-id>`
- `/bracket?tournament=<round-robin-id>`

Confirm:

- Single elimination shows 4 rounds.
- Double elimination shows winners, losers, and grand final sections, with best-of-3 labels on QF/SF/F/GF.
- Round robin shows 7 group rounds and the Generate playoffs action is gated until the group is complete.

- [ ] **Step 4: Run the demo seeder test**

Run:

```powershell
php artisan test tests/Feature/Database/BracketDemoSeederTest.php --compact
```

Expected: PASS with 6 tests.

- [ ] **Step 5: Run the full suite and quality gate**

Run:

```powershell
php artisan test --compact
```

Expected: all tests pass.

Run:

```powershell
$env:COMPOSER_PROCESS_TIMEOUT='900'; composer quality
```

Expected: Pint passes, PHPStan reports `[OK] No errors`, and the full test suite passes.

- [ ] **Step 6: Commit any verification fixes**

If Step 5 required changes:

```powershell
git add database/seeders/BracketDemoSeeder.php tests/Feature/Database/BracketDemoSeederTest.php
git commit -m "test(demo): verify bracket demo seeder"
```

If no changes were needed, do not create an empty commit.

---

## Self-review notes

- Spec coverage: three tournaments, 16/16/8 teams, series settings, no reset, 7-round round robin, schedules, destructive reset, and tests are all covered.
- The double-elimination bracket has 30 anchors because the best-of-3 grand final removes the reset.
- Conditional series child games are created upfront but never scheduled.
- Existing `PrototypeSeeder` and its tests are untouched.
