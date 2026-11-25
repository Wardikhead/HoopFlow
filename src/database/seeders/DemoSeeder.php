<?php

/** Database seeder Demo seeder. */

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\Bracket;
use App\Models\Court;
use App\Models\Division;
use App\Models\EligibilityDocument;
use App\Models\GameRule;
use App\Models\MatchModel;
use App\Models\MatchOfficial;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Player;
use App\Models\Referee;
use App\Models\Registration;
use App\Models\Role;
use App\Models\Team;
use App\Models\TeamMembership;
use App\Models\Tournament;
use App\Models\User;
use App\Models\Venue;
use App\Services\AnnouncementService;
use App\Services\BracketService;
use App\Services\GameStatsService;
use App\Services\SchedulingService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Full demonstration dataset for HoopFlow.
 *
 * The seeder is intentionally destructive: it clears the demo tables first so
 * the dataset is repeatable. Bracket, schedule, and score data are produced by
 * the same services the application uses, so the result exercises the real
 * bracket propagation, series, scheduling, and tournament lifecycle logic.
 */
class DemoSeeder extends Seeder
{
    /** @var list<array{name: string, short: string, coach: string, city: string, color: string}> */
    protected array $teamDefinitions = [
        ['name' => 'Pasig Pirates', 'short' => 'PSG', 'coach' => 'Alvin Cruz', 'city' => 'Pasig City', 'color' => '#0e7490'],
        ['name' => 'Kapitolyo Kings', 'short' => 'KNG', 'coach' => 'Bong Reyes', 'city' => 'Pasig City', 'color' => '#1d4ed8'],
        ['name' => 'Ugong United', 'short' => 'UGU', 'coach' => 'Carlo Santos', 'city' => 'Pasig City', 'color' => '#047857'],
        ['name' => 'Rosario Rockets', 'short' => 'RCK', 'coach' => 'Dante Lim', 'city' => 'Pasig City', 'color' => '#b91c1c'],
        ['name' => 'Maybunga Mavericks', 'short' => 'MVR', 'coach' => 'Elmer Tan', 'city' => 'Pasig City', 'color' => '#7e22ce'],
        ['name' => 'Pinagbuhatan Pilots', 'short' => 'PLT', 'coach' => 'Francisco Diaz', 'city' => 'Pasig City', 'color' => '#475569'],
        ['name' => 'Bagong Ilog Bears', 'short' => 'BRS', 'coach' => 'Glen Aquino', 'city' => 'Pasig City', 'color' => '#a16207'],
        ['name' => 'Kalawaan Kestrels', 'short' => 'KST', 'coach' => 'Hector Navarro', 'city' => 'Pasig City', 'color' => '#0f766e'],
        ['name' => 'San Antonio Vipers', 'short' => 'VPR', 'coach' => 'Marcus Rivera', 'city' => 'Pasig City', 'color' => '#ea580c'],
        ['name' => 'Mandaluyong Tigers', 'short' => 'TGR', 'coach' => 'Ramon Bautista', 'city' => 'Mandaluyong City', 'color' => '#d97706'],
        ['name' => 'Ortigas Raptors', 'short' => 'RPT', 'coach' => 'Kenneth Cruz', 'city' => 'Pasig City', 'color' => '#7c3aed'],
        ['name' => 'Ugong Blue Hawks', 'short' => 'HWK', 'coach' => 'Allan Gomez', 'city' => 'Pasig City', 'color' => '#0284c7'],
        ['name' => 'Dela Paz Dragons', 'short' => 'DPZ', 'coach' => 'Marlon Lim', 'city' => 'Pasig City', 'color' => '#e11d48'],
        ['name' => 'Caniogan Cardinals', 'short' => 'CNG', 'coach' => 'Boyet Tan', 'city' => 'Pasig City', 'color' => '#be123c'],
        ['name' => 'Sumilang Spartans', 'short' => 'SMS', 'coach' => 'Dennis Pascual', 'city' => 'Pasig City', 'color' => '#334155'],
        ['name' => 'Santolan Saints', 'short' => 'STL', 'coach' => 'Arnel Garcia', 'city' => 'Pasig City', 'color' => '#16a34a'],
        ['name' => 'Floodway Flames', 'short' => 'FLM', 'coach' => 'Rodel Mercado', 'city' => 'Pasig City', 'color' => '#dc2626'],
        ['name' => 'Sta. Lucia Cyclones', 'short' => 'SLC', 'coach' => 'Jomar Villanueva', 'city' => 'Pasig City', 'color' => '#2563eb'],
        ['name' => 'Pineda Panthers', 'short' => 'PND', 'coach' => 'Noli Aquino', 'city' => 'Pasig City', 'color' => '#059669'],
        ['name' => 'Napico Nuggets', 'short' => 'NPC', 'coach' => 'Vic Sotto', 'city' => 'Pasig City', 'color' => '#64748b'],
    ];

    protected ?Organization $organization = null;

    protected ?User $organizer = null;

    protected ?User $coach = null;

    protected ?User $fan = null;

    /** @var array<int, Team> */
    protected array $teams = [];

    /** @var array<int, array<int, int>> */
    protected array $seedsByTournament = [];

    /** @var list<Referee> */
    protected array $referees = [];

    /**
     * Run.
     *
     * @return void Nothing.
     */
    public function run(): void
    {
        $this->call([OrganizationSeeder::class, RoleSeeder::class]);

        $this->organization = Organization::query()->where('slug', 'default')->firstOrFail();
        $this->seedUsers();

        // Supabase (and other managed Postgres providers) impose a default
        // statement/idle-in-transaction timeout that the long-running demo seed
        // exceeds. Disable both for this session when running on Postgres.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('SET statement_timeout = 0');
            DB::statement('SET idle_in_transaction_session_timeout = 0');
        }

        // Purge in its own short transaction so a later failure does not leave
        // a half-seeded dataset behind on the next run (purge runs again first).
        DB::transaction(function (): void {
            $this->purgeDemoData();
        });

        // Seed each phase without one long-running transaction: every match
        // publish already wraps its own work in a transaction, so holding a
        // single transaction open for minutes (which Supabase/network drops)
        // is avoided.
        $this->seedTeams();
        $this->seedReferees();
        $this->seedCompletedSingleElimination();
        $this->seedCompletedRoundRobin();
        $this->seedActiveBestOfThree();
        $this->seedCompletedDoubleElimination();
        $this->seedRegistrationOpenTournament();
        $this->attachMatchOfficials();
        $this->seedAnnouncements();
        $this->seedAuditTrail();
    }

    /**
     * Seed users.
     *
     * @return void Nothing.
     */
    protected function seedUsers(): void
    {
        $this->organizer = User::updateOrCreate(
            ['email' => 'organizer@hoopflow.ph'],
            [
                'first_name' => 'Jeward',
                'last_name' => 'Mahinay',
                'password' => Hash::make('password'),
                'status' => 'active',
            ],
        );

        $this->coach = User::updateOrCreate(
            ['email' => 'coach.marcus@hoopflow.ph'],
            [
                'first_name' => 'Coach Marcus',
                'last_name' => 'Rivera',
                'password' => Hash::make('password'),
                'status' => 'active',
            ],
        );

        $this->fan = User::updateOrCreate(
            ['email' => 'fan.juan@hoopflow.ph'],
            [
                'first_name' => 'Juan',
                'last_name' => 'Dela Cruz',
                'password' => Hash::make('password'),
                'status' => 'active',
            ],
        );

        $this->assignRole($this->organizer, 'organizer');
        $this->assignRole($this->coach, 'coach');
        $this->assignRole($this->fan, 'fan');
    }

    /**
     * Assign role.
     *
     * @param  User  $user  The user.
     * @param  string  $roleKey  The role key.
     * @return void Nothing.
     */
    protected function assignRole(User $user, string $roleKey): void
    {
        $roleId = Role::query()->where('key', $roleKey)->value('id');

        if ($roleId === null) {
            return;
        }

        DB::table('user_roles')->updateOrInsert(
            ['user_id' => $user->id, 'role_id' => $roleId, 'scope' => null],
            [
                'organization_id' => $this->organization()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    /**
     * Purge demo data.
     *
     * @return void Nothing.
     */
    protected function purgeDemoData(): void
    {
        foreach ([
            'player_stat_quarters',
            'player_stats',
            'match_officials',
            'referees',
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
     * Seed teams.
     *
     * @return void Nothing.
     */
    protected function seedTeams(): void
    {
        foreach ($this->teamDefinitions as $index => $definition) {
            $team = Team::create([
                'organization_id' => $this->organization()->id,
                'name' => $definition['name'],
                'short_name' => $definition['short'],
                'slug' => 'demo-'.strtolower($definition['short']),
                'avatar_color' => $definition['color'],
                'city' => $definition['city'],
                'coach_name' => $definition['coach'],
                'logo_url' => null,
                'coach_user_id' => null,
            ]);

            $this->teams[$index] = $team;
            $this->seedRoster($team, $index, $index < 16 ? 'verified' : 'pending');
        }

        $vipers = $this->teams[8] ?? null;

        if ($vipers instanceof Team && $this->coach instanceof User) {
            $vipers->update(['coach_user_id' => $this->coach->id]);

            TeamMembership::create([
                'team_id' => $vipers->id,
                'user_id' => $this->coach->id,
                'role' => 'coach',
                'status' => 'active',
            ]);
        }
    }

    /**
     * Seed roster.
     *
     * @param  Team  $team  The team.
     * @param  int  $teamIndex  The team index.
     * @param  string  $state  The state.
     * @return void Nothing.
     */
    protected function seedRoster(Team $team, int $teamIndex, string $state): void
    {
        $firstNames = ['Jayson', 'Kobe', 'Carl', 'Kai', 'June Mar', 'Gabe', 'CJ', 'Dwight', 'Calvin', 'RR', 'Marc', 'Prince', 'Andre', 'Jolo', 'Troy', 'Luis'];
        $lastNames = ['Castro', 'Ramos', 'Cruz', 'Gomez', 'Fajardo', 'Diaz', 'Santos', 'Reyes', 'Bautista', 'Villanueva', 'Macaraeg', 'Torres', 'Aquino', 'Navarro', 'Mendoza', 'Del Rosario'];
        $positions = ['Point Guard (PG)', 'Shooting Guard (SG)', 'Small Forward (SF)', 'Power Forward (PF)', 'Center (C)'];
        $verified = $state === 'verified';

        for ($i = 0; $i < 10; $i++) {
            $missingDocument = ! $verified && $i === 8;

            $player = Player::create([
                'team_id' => $team->id,
                'first_name' => $firstNames[($teamIndex * 7 + $i) % count($firstNames)],
                'last_name' => $lastNames[($teamIndex * 3 + $i) % count($lastNames)],
                'birthdate' => now()->subYears(14)->subDays(($teamIndex * 5) + $i)->format('Y-m-d'),
                'jersey_number' => $i + 1,
                'position' => $positions[$i % count($positions)],
                'photo_url' => null,
                'guardian_name' => $verified ? 'Guardian of '.$firstNames[($teamIndex * 7 + $i) % count($firstNames)] : null,
                'guardian_phone' => $verified ? '0917'.str_pad((string) (($teamIndex * 10) + $i), 7, '0', STR_PAD_LEFT) : null,
                'consent_at' => $verified ? now()->subMonths(3) : null,
                'consent_status' => $verified ? 'verified' : ($missingDocument ? 'missing' : 'pending'),
                'consent_recorded_by' => $verified ? $this->organizer()->id : null,
                'consent_recorded_at' => $verified ? now()->subMonths(3) : null,
                'consent_version' => $verified ? '1.0' : null,
                'eligibility_status' => $verified ? 'verified' : ($missingDocument ? 'missing' : 'pending'),
                'verified_by' => $verified ? $this->organizer()->id : null,
                'verified_at' => $verified ? now()->subMonths(3) : null,
            ]);

            if ($missingDocument) {
                continue;
            }

            EligibilityDocument::create([
                'player_id' => $player->id,
                'type' => 'birth_certificate',
                'file_url' => 'https://example.com/demo/documents/'.$player->id.'.pdf',
                'status' => $verified ? 'approved' : 'pending',
                'reviewed_by' => $verified ? $this->organizer()->id : null,
                'reviewed_at' => $verified ? now()->subMonths(3) : null,
                'notes' => $verified ? null : 'Uploaded by coach; awaiting organizer review.',
            ]);
        }
    }

    /**
     * Seed referees.
     *
     * @return void Nothing.
     */
    protected function seedReferees(): void
    {
        $this->referees = [];

        foreach ([
            ['name' => 'Reynaldo Santos', 'phone' => '0918 441 2201', 'certification' => 'SBP Certified Referee'],
            ['name' => 'Edwin Lim', 'phone' => '0917 338 9854', 'certification' => 'FIBA Level 1'],
            ['name' => 'Paolo Garcia', 'phone' => '0920 775 4432', 'certification' => 'SBP Certified Referee'],
            ['name' => 'Mario Dela Cruz', 'phone' => '0916 224 7789', 'certification' => 'Barangay League Referee'],
            ['name' => 'Arvin Tolentino', 'phone' => '0915 990 1123', 'certification' => 'SBP Certified Referee'],
        ] as $definition) {
            $this->referees[] = Referee::create([
                'user_id' => null,
                'name' => $definition['name'],
                'phone' => $definition['phone'],
                'certification' => $definition['certification'],
                'status' => 'active',
            ]);
        }
    }

    /**
     * @param  array{name: string, slug: string, description: string, start: string, end: string, status: string, format: string, division: string, age_max: int, slots: int, fee: int, venue: string, venue_address: string, courts: list<string>, best_of_quarterfinal?: int, best_of_semifinal?: int, best_of_final?: int, round_robin_legs?: int, playoff_teams?: int}  $data
     */
    protected function createTournament(array $data): Tournament
    {
        $bestOfQuarterfinal = $data['best_of_quarterfinal'] ?? 1;
        $bestOfSemifinal = $data['best_of_semifinal'] ?? 1;
        $bestOfFinal = $data['best_of_final'] ?? 1;
        $roundRobinLegs = $data['round_robin_legs'] ?? 1;
        $playoffTeams = $data['playoff_teams'] ?? 0;

        $tournament = Tournament::create([
            'organization_id' => $this->organization()->id,
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'],
            'start_date' => $data['start'],
            'end_date' => $data['end'],
            'status' => $data['status'],
            'payment_method' => 'GCash',
            'payment_account' => '0948 966 6136',
            'rules_json' => [
                'slots' => $data['slots'],
                'fee' => $data['fee'],
                'format' => $data['format'],
                'round_robin_legs' => $roundRobinLegs,
                'playoff_teams' => $playoffTeams,
                'best_of_quarterfinal' => $bestOfQuarterfinal,
                'best_of_semifinal' => $bestOfSemifinal,
                'best_of_final' => $bestOfFinal,
                'slot_minutes' => 30,
                'rest_minutes' => 15,
            ],
            'created_by' => $this->organizer()->id,
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
            'tiebreakers_json' => ['head_to_head', 'point_diff', 'points_for'],
        ]);

        $venue = Venue::create([
            'organization_id' => $this->organization()->id,
            'name' => $data['venue'],
            'address' => $data['venue_address'],
            'timezone' => 'Asia/Manila',
        ]);

        foreach ($data['courts'] as $courtName) {
            Court::create([
                'venue_id' => $venue->id,
                'name' => $courtName,
                'notes' => null,
            ]);
        }

        $rules = is_array($tournament->rules_json) ? $tournament->rules_json : [];
        $rules['venue_id'] = $venue->id;
        $tournament->rules_json = $rules;
        $tournament->save();

        return $tournament;
    }

    /**
     * @param  list<array{team: int, status: string, payment: string, submitted: string, decided?: string|null, seed?: int|null, amount?: float|int|null, method?: string|null, reference?: string|null, rejection?: string|null, remarks?: string|null}>  $specs
     */
    protected function registerTeams(Tournament $tournament, array $specs): void
    {
        $division = $tournament->divisions()->firstOrFail();
        $fee = (float) $division->entry_fee;

        foreach ($specs as $spec) {
            $team = $this->teams[$spec['team']] ?? null;

            if (! $team instanceof Team) {
                throw new RuntimeException('Unknown demo team index ['.$spec['team'].'].');
            }

            $status = $spec['status'];
            $paymentStatus = $spec['payment'];
            $decidedAt = $spec['decided'] ?? null;
            $seed = $status === 'approved' ? ($spec['seed'] ?? null) : null;

            $registration = Registration::create([
                'tournament_id' => $tournament->id,
                'division_id' => $division->id,
                'team_id' => $team->id,
                'status' => $status,
                'submitted_at' => $spec['submitted'],
                'decided_by' => $status === 'submitted' ? null : $this->organizer()->id,
                'decided_at' => $decidedAt,
                'rejection_reason' => $spec['rejection'] ?? null,
                'seed' => $seed,
                'roster_locked_at' => $status === 'approved' ? $decidedAt : null,
            ]);

            if ($seed !== null) {
                $this->seedsByTournament[$tournament->id][$team->id] = $seed;
            }

            $paidAt = in_array($paymentStatus, ['verified', 'partial'], true)
                ? ($decidedAt ?? $spec['submitted'])
                : null;

            Payment::create([
                'registration_id' => $registration->id,
                'amount' => $spec['amount'] ?? $fee,
                'currency' => 'PHP',
                'method' => $spec['method'] ?? 'GCash',
                'reference_no' => $spec['reference'] ?? null,
                'receipt_no' => in_array($paymentStatus, ['verified', 'partial'], true)
                    ? 'RCPT-'.strtoupper((string) $tournament->slug).'-'.$team->id
                    : null,
                'status' => $paymentStatus,
                'paid_at' => $paidAt,
                'recorded_by' => $this->organizer()->id,
                'remarks' => $spec['remarks'] ?? null,
                'refunded_amount' => 0,
                'refunded_at' => null,
            ]);
        }
    }

    /**
     * Seed completed single elimination.
     *
     * @return void Nothing.
     */
    protected function seedCompletedSingleElimination(): void
    {
        $tournament = $this->createTournament([
            'name' => 'HoopFlow Founders Cup 2026',
            'slug' => 'founders-cup-2026',
            'description' => 'The season-opening invitational. Sixteen teams, single elimination, one game per round from the round of 16 to the final.',
            'start' => '2026-08-03',
            'end' => '2026-08-09',
            'status' => 'registration-open',
            'format' => 'single-elimination',
            'division' => 'Under 15 (U15)',
            'age_max' => 15,
            'slots' => 16,
            'fee' => 1200,
            'venue' => 'HoopFlow Legacy Arena',
            'venue_address' => 'M. Suarez Ave, Pasig City',
            'courts' => ['Court 1', 'Court 2'],
        ]);

        $specs = [];

        foreach (range(0, 15) as $index) {
            $specs[] = [
                'team' => $index,
                'status' => 'approved',
                'payment' => 'verified',
                'submitted' => '2026-07-06 09:00:00',
                'decided' => '2026-07-08 10:00:00',
                'seed' => $index + 1,
                'reference' => sprintf('GCASH-FC26-%03d', $index + 1),
            ];
        }

        $this->registerTeams($tournament, $specs);

        app(BracketService::class)->generate($tournament, 'single-elimination');
        app(SchedulingService::class)->generate($tournament);

        $champion = $this->teams[1] ?? null;
        $championId = $champion instanceof Team ? $champion->id : null;

        foreach ([16, 8, 4, 2] as $round) {
            foreach ($this->bracketMatches($tournament, $round) as $match) {
                [$homeScore, $awayScore] = $this->eliminationScores($match, $championId);
                $this->publishBoxScore($match, $homeScore, $awayScore);
            }
        }
    }

    /**
     * Seed completed round robin.
     *
     * @return void Nothing.
     */
    protected function seedCompletedRoundRobin(): void
    {
        $tournament = $this->createTournament([
            'name' => 'HoopFlow Community League 2026',
            'slug' => 'community-league-2026',
            'description' => 'A seven-matchday round-robin regular season for neighborhood clubs, followed by a four-team best-of-three playoff. The table is decided by head-to-head, point difference, then points scored.',
            'start' => '2026-11-02',
            'end' => '2026-11-14',
            'status' => 'registration-open',
            'format' => 'round-robin',
            'division' => 'Under 15 (U15)',
            'age_max' => 15,
            'slots' => 8,
            'fee' => 1000,
            'venue' => 'HoopFlow Central Arena',
            'venue_address' => 'E. Rodriguez Jr. Ave, Pasig City',
            'courts' => ['Court 1', 'Court 2'],
            'round_robin_legs' => 1,
            'playoff_teams' => 4,
            'best_of_semifinal' => 3,
            'best_of_final' => 3,
        ]);

        $specs = [];

        foreach (range(0, 7) as $index) {
            $specs[] = [
                'team' => $index,
                'status' => 'approved',
                'payment' => 'verified',
                'submitted' => '2026-10-05 09:00:00',
                'decided' => '2026-10-07 10:00:00',
                'seed' => $index + 1,
                'reference' => sprintf('GCASH-CL26-%03d', $index + 1),
            ];
        }

        $this->registerTeams($tournament, $specs);

        app(BracketService::class)->generate($tournament, 'round-robin');
        app(SchedulingService::class)->generate($tournament);

        $matches = MatchModel::query()
            ->where('tournament_id', $tournament->id)
            ->where('stage', 'round_robin')
            ->orderBy('round_number')
            ->orderBy('match_number')
            ->get();

        foreach ($matches as $match) {
            [$homeScore, $awayScore] = $this->roundRobinScore($match);
            $this->publishBoxScore($match, $homeScore, $awayScore);
        }

        app(BracketService::class)->generatePlayoffs($tournament);
        $plan = app(SchedulingService::class)->planRemaining($tournament);
        app(SchedulingService::class)->publishRemaining($tournament, $plan);

        $changed = true;
        while ($changed) {
            $changed = false;
            $playoffMatches = MatchModel::query()
                ->where('tournament_id', $tournament->id)
                ->where('status', 'scheduled')
                ->whereNotNull('home_team_id')
                ->whereNotNull('away_team_id')
                ->orderBy('round_number')
                ->orderBy('match_number')
                ->get();

            foreach ($playoffMatches as $match) {
                if ($match->status !== 'scheduled') {
                    continue;
                }

                [$homeScore, $awayScore] = $this->eliminationScores($match, null);
                $this->publishBoxScore($match, $homeScore, $awayScore);
                $changed = true;
            }
        }
    }

    /**
     * Seed active best of three.
     *
     * @return void Nothing.
     */
    protected function seedActiveBestOfThree(): void
    {
        $tournament = $this->createTournament([
            'name' => 'Barangay Hoops Championship 2026',
            'slug' => 'barangay-hoops-2026',
            'description' => 'The flagship barangay championship. Sixteen teams, best-of-three series from the round of 16 onward, with the final series deciding the 2026 champion.',
            'start' => '2026-11-19',
            'end' => '2026-12-13',
            'status' => 'registration-open',
            'format' => 'single-elimination',
            'division' => 'Under 17 (U17)',
            'age_max' => 17,
            'slots' => 16,
            'fee' => 1500,
            'venue' => 'HoopFlow North Arena',
            'venue_address' => 'C. Raymundo Ave, Pasig City',
            'courts' => ['Court 1', 'Court 2', 'Court 3', 'Court 4'],
            'best_of_quarterfinal' => 3,
            'best_of_semifinal' => 3,
            'best_of_final' => 3,
        ]);

        $specs = [];

        foreach (range(0, 15) as $offset) {
            $specs[] = [
                'team' => $offset,
                'status' => 'approved',
                'payment' => 'verified',
                'submitted' => '2026-10-20 09:00:00',
                'decided' => '2026-10-23 10:00:00',
                'seed' => $offset + 1,
                'reference' => sprintf('GCASH-BH26-%03d', $offset + 1),
            ];
        }

        $specs[14]['payment'] = 'partial';
        $specs[14]['amount'] = 1000;
        $specs[14]['remarks'] = 'Partial payment received; PHP 500 balance due before the semifinals.';

        $specs[15]['payment'] = 'pending';
        $specs[15]['reference'] = 'GCASH-BH26-016';
        $specs[15]['remarks'] = 'Reference submitted; awaiting organizer verification.';

        $this->registerTeams($tournament, $specs);

        app(BracketService::class)->generate($tournament, 'single-elimination');
        app(SchedulingService::class)->generate($tournament);

        // Round of 16: single game (best-of-one).
        foreach ($this->bracketMatches($tournament, 16) as $anchor) {
            [$homeScore, $awayScore] = $this->eliminationScores($anchor, null);
            $this->publishBoxScore($anchor, $homeScore, $awayScore);
        }

        // Quarterfinals: best-of-three, sweep every series.
        foreach ($this->bracketMatches($tournament, 8) as $anchor) {
            [$homeScore, $awayScore] = $this->eliminationScores($anchor, null);
            $this->publishBoxScore($anchor, $homeScore, $awayScore);

            $game2 = $this->seriesGame($anchor, 2);
            [$homeScore2, $awayScore2] = $this->eliminationScores($game2, null);
            $this->publishBoxScore($game2, $homeScore2, $awayScore2);
        }

        $semifinals = $this->bracketMatches($tournament, 4);
        $firstSemifinal = $semifinals->get(0);
        $secondSemifinal = $semifinals->get(1);

        if (! $firstSemifinal instanceof MatchModel || ! $secondSemifinal instanceof MatchModel) {
            throw new RuntimeException('The Barangay Hoops semifinals were not generated.');
        }

        // First semifinal: sweep.
        [$homeScore, $awayScore] = $this->eliminationScores($firstSemifinal, null);
        $this->publishBoxScore($firstSemifinal, $homeScore, $awayScore);

        $firstGame2 = $this->seriesGame($firstSemifinal, 2);
        [$homeScore2, $awayScore2] = $this->eliminationScores($firstGame2, null);
        $this->publishBoxScore($firstGame2, $homeScore2, $awayScore2);

        // Second semifinal: tied 1-1 (game 3 stays scheduled).
        [$homeScore, $awayScore] = $this->eliminationScores($secondSemifinal, null);
        $this->publishBoxScore($secondSemifinal, $homeScore, $awayScore);

        $secondGame2 = $this->seriesGame($secondSemifinal, 2);
        [$homeScore2, $awayScore2] = $this->eliminationScores($secondGame2, null);
        $this->publishBoxScore($secondGame2, $awayScore2, $homeScore2);
    }

    /**
     * Seed completed double elimination.
     *
     * @return void Nothing.
     */
    protected function seedCompletedDoubleElimination(): void
    {
        $tournament = $this->createTournament([
            'name' => 'HoopFlow Double Elimination Cup 2026',
            'slug' => 'double-elimination-cup-2026',
            'description' => 'Sixteen teams, double elimination. One loss drops a team to the losers bracket; two losses end the run. The grand final decides the champion.',
            'start' => '2026-09-01',
            'end' => '2026-09-12',
            'status' => 'registration-open',
            'format' => 'double-elimination',
            'division' => 'Under 15 (U15)',
            'age_max' => 15,
            'slots' => 16,
            'fee' => 1200,
            'venue' => 'HoopFlow East Arena',
            'venue_address' => 'Shaw Blvd, Mandaluyong City',
            'courts' => ['Court 1', 'Court 2', 'Court 3'],
        ]);

        $specs = [];

        foreach (range(0, 15) as $index) {
            $specs[] = [
                'team' => $index,
                'status' => 'approved',
                'payment' => 'verified',
                'submitted' => '2026-08-04 09:00:00',
                'decided' => '2026-08-06 10:00:00',
                'seed' => $index + 1,
                'reference' => sprintf('GCASH-DE26-%03d', $index + 1),
            ];
        }

        $this->registerTeams($tournament, $specs);

        app(BracketService::class)->generate($tournament, 'double-elimination');
        app(SchedulingService::class)->generate($tournament);

        $changed = true;

        while ($changed) {
            $changed = false;

            $matches = MatchModel::query()
                ->where('tournament_id', $tournament->id)
                ->where('status', 'scheduled')
                ->whereNotNull('home_team_id')
                ->whereNotNull('away_team_id')
                ->orderBy('round_number')
                ->orderBy('match_number')
                ->get();

            foreach ($matches as $match) {
                if ($match->status !== 'scheduled') {
                    continue;
                }

                [$homeScore, $awayScore] = $this->eliminationScores($match, null);
                $this->publishBoxScore($match, $homeScore, $awayScore);
                $changed = true;
            }
        }
    }

    /**
     * Seed registration open tournament.
     *
     * @return void Nothing.
     */
    protected function seedRegistrationOpenTournament(): void
    {
        $tournament = $this->createTournament([
            'name' => 'HoopFlow Summer League 2027',
            'slug' => 'summer-league-2027',
            'description' => 'The next season opens with a sixteen-team U17 tournament. Registration is open while the committee reviews new rosters and payments.',
            'start' => '2027-01-10',
            'end' => '2027-02-15',
            'status' => 'registration-open',
            'format' => 'single-elimination',
            'division' => 'Under 17 (U17)',
            'age_max' => 17,
            'slots' => 16,
            'fee' => 1500,
            'venue' => 'HoopFlow South Arena',
            'venue_address' => 'Shaw Blvd, Mandaluyong City',
            'courts' => ['Court 1', 'Court 2', 'Court 3'],
        ]);

        $specs = [];

        foreach (range(0, 5) as $index) {
            $specs[] = [
                'team' => $index,
                'status' => 'approved',
                'payment' => 'verified',
                'submitted' => '2026-10-20 09:00:00',
                'decided' => '2026-10-22 10:00:00',
                'seed' => $index + 1,
                'reference' => sprintf('GCASH-SL27-%03d', $index + 1),
            ];
        }

        $specs[] = [
            'team' => 6,
            'status' => 'approved',
            'payment' => 'partial',
            'amount' => 900,
            'submitted' => '2026-10-21 09:00:00',
            'decided' => '2026-10-23 10:00:00',
            'seed' => 7,
            'reference' => 'GCASH-SL27-007',
            'remarks' => 'Partial payment received; PHP 600 balance due before the opening day.',
        ];

        $specs[] = [
            'team' => 7,
            'status' => 'approved',
            'payment' => 'pending',
            'submitted' => '2026-10-22 09:00:00',
            'decided' => '2026-10-24 10:00:00',
            'seed' => 8,
            'reference' => 'GCASH-SL27-008',
            'remarks' => 'Reference submitted; awaiting organizer verification.',
        ];

        $specs[] = [
            'team' => 8,
            'status' => 'approved',
            'payment' => 'verified',
            'submitted' => '2026-10-23 09:00:00',
            'decided' => '2026-10-25 10:00:00',
            'seed' => 9,
            'reference' => 'GCASH-SL27-009',
        ];

        $specs[] = [
            'team' => 16,
            'status' => 'submitted',
            'payment' => 'pending',
            'submitted' => '2026-10-28 09:00:00',
            'decided' => null,
            'seed' => null,
            'reference' => 'GCASH-SL27-017',
            'remarks' => 'New submission awaiting organizer review.',
        ];

        $specs[] = [
            'team' => 17,
            'status' => 'submitted',
            'payment' => 'pending',
            'submitted' => '2026-11-02 09:00:00',
            'decided' => null,
            'seed' => null,
            'reference' => 'GCASH-SL27-018',
            'remarks' => 'New submission awaiting organizer review.',
        ];

        $specs[] = [
            'team' => 18,
            'status' => 'submitted',
            'payment' => 'pending',
            'submitted' => '2026-11-06 09:00:00',
            'decided' => null,
            'seed' => null,
            'reference' => 'GCASH-SL27-019',
            'remarks' => 'New submission awaiting organizer review.',
        ];

        $specs[] = [
            'team' => 19,
            'status' => 'rejected',
            'payment' => 'pending',
            'submitted' => '2026-10-30 09:00:00',
            'decided' => '2026-11-03 10:00:00',
            'seed' => null,
            'reference' => 'GCASH-SL27-020',
            'rejection' => 'Roster and documents were incomplete before the review cutoff.',
            'remarks' => 'Registration rejected; payment was not verified.',
        ];

        $this->registerTeams($tournament, $specs);
    }

    /**
     * @return EloquentCollection<int, MatchModel>
     */
    protected function bracketMatches(Tournament $tournament, int $roundNumber): EloquentCollection
    {
        $divisionId = $tournament->divisions()->value('id');
        $bracket = Bracket::query()
            ->where('division_id', $divisionId)
            ->where('phase', 'main')
            ->firstOrFail();

        return MatchModel::query()
            ->where('bracket_id', $bracket->id)
            ->where('stage', 'winners')
            ->where('round_number', $roundNumber)
            ->whereNull('series_id')
            ->orderBy('match_number')
            ->get();
    }

    /**
     * Series game.
     *
     * @param  MatchModel  $anchor  The anchor.
     * @param  int  $gameNumber  The game number.
     * @return MatchModel The result.
     */
    protected function seriesGame(MatchModel $anchor, int $gameNumber): MatchModel
    {
        if ($gameNumber === 1) {
            return $anchor->refresh();
        }

        $game = MatchModel::query()
            ->where('series_id', $anchor->id)
            ->where('game_number', $gameNumber)
            ->first();

        if (! $game instanceof MatchModel) {
            throw new RuntimeException('Series game '.$gameNumber.' was not found for match '.$anchor->id.'.');
        }

        return $game;
    }

    /**
     * Record score.
     *
     * @param  MatchModel  $match  The match.
     * @param  int  $homeScore  The home score.
     * @param  int  $awayScore  The away score.
     * @return void Nothing.
     */
    protected function recordScore(MatchModel $match, int $homeScore, int $awayScore): void
    {
        app(BracketService::class)->recordScore($match, $homeScore, $awayScore);
    }

    /**
     * @return array{0: int, 1: int}
     */
    protected function roundRobinScore(MatchModel $match): array
    {
        $homeSeed = $this->seedsByTournament[$match->tournament_id][$match->home_team_id] ?? 99;
        $awaySeed = $this->seedsByTournament[$match->tournament_id][$match->away_team_id] ?? 99;
        $homeFavorite = $homeSeed <= $awaySeed;
        $favoriteWins = (($match->round_number * 7 + $match->match_number * 5) % 11) !== 0;
        $homeWins = $favoriteWins ? $homeFavorite : ! $homeFavorite;

        $base = 58 + (($match->round_number * 7 + $match->match_number * 11) % 26);
        $margin = 2 + (($match->id + $match->round_number * 3) % 9);
        $winnerScore = $base + $margin;
        $loserScore = $base - (($match->match_number * 2) % 5);

        if ($loserScore >= $winnerScore) {
            $loserScore = $winnerScore - 1;
        }

        return $homeWins
            ? [$winnerScore, $loserScore]
            : [$loserScore, $winnerScore];
    }

    /**
     * @return array{0: int, 1: int}
     */
    protected function eliminationScores(MatchModel $match, ?int $favoredTeamId): array
    {
        $homeTeamId = (int) $match->home_team_id;
        $awayTeamId = (int) $match->away_team_id;
        $homeSeed = $this->seedsByTournament[$match->tournament_id][$homeTeamId] ?? 99;
        $awaySeed = $this->seedsByTournament[$match->tournament_id][$awayTeamId] ?? 99;

        $homeWins = false;

        if ($favoredTeamId !== null) {
            if ($homeTeamId === $favoredTeamId) {
                $homeWins = true;
            } elseif ($awayTeamId === $favoredTeamId) {
                $homeWins = false;
            } else {
                $homeWins = $homeSeed <= $awaySeed;
            }
        } else {
            $homeWins = $homeSeed <= $awaySeed;
        }

        $base = 68 + (($match->id * 5 + $match->round_number * 3) % 16);
        $margin = 3 + ($match->id % 6);

        return $homeWins
            ? [$base + $margin, $base - 2]
            : [$base - 2, $base + $margin];
    }

    /**
     * Publish box score.
     *
     * @param  MatchModel  $match  The match.
     * @param  int  $homeScore  The home score.
     * @param  int  $awayScore  The away score.
     * @return void Nothing.
     */
    protected function publishBoxScore(MatchModel $match, int $homeScore, int $awayScore): void
    {
        $homeTeam = $match->homeTeam;
        $awayTeam = $match->awayTeam;

        if (! $homeTeam instanceof Team || ! $awayTeam instanceof Team) {
            throw new RuntimeException('Both teams must be known before publishing a box score.');
        }

        $home = $this->boxScoreFor($homeTeam, $homeScore);
        $away = $this->boxScoreFor($awayTeam, $awayScore);

        app(GameStatsService::class)->publish($match, [
            'quarters' => [
                'team1' => $home['quarters'],
                'team2' => $away['quarters'],
            ],
            'finals' => [
                'team1' => $homeScore,
                'team2' => $awayScore,
            ],
            'stats' => [
                'team1' => $home['stats'],
                'team2' => $away['stats'],
            ],
        ]);
    }

    /**
     * @return array{quarters: list<int>, stats: array<int, array{quarters: list<array<string, int>>}>}
     */
    protected function boxScoreFor(Team $team, int $totalPoints): array
    {
        $players = $team->players()->orderBy('jersey_number')->orderBy('id')->get();

        if ($players->isEmpty()) {
            throw new RuntimeException('Team '.$team->name.' has no players for a box score.');
        }

        $weights = [22, 17, 14, 12, 10, 9, 7, 5, 3, 1];
        $weights = array_slice($weights, 0, $players->count());
        $weightSum = array_sum($weights);
        $assigned = 0;
        $quarters = [0, 0, 0, 0];
        $stats = [];
        $lastIndex = $players->count() - 1;

        foreach ($players as $index => $player) {
            if ($index === $lastIndex) {
                $playerPoints = max(0, $totalPoints - $assigned);
            } else {
                $playerPoints = intdiv($totalPoints * $weights[$index], $weightSum);
                $assigned += $playerPoints;
            }

            $base = intdiv($playerPoints, 4);
            $remainder = $playerPoints % 4;
            $quarterPoints = [
                $base + ($remainder > 0 ? 1 : 0),
                $base + ($remainder > 1 ? 1 : 0),
                $base + ($remainder > 2 ? 1 : 0),
                $base,
            ];

            $quarterLines = [];

            foreach ($quarterPoints as $quarterIndex => $points) {
                $quarters[$quarterIndex] += $points;
                $quarterLines[] = $this->statLine($points, $index, $quarterIndex);
            }

            $stats[(int) $player->id] = ['quarters' => $quarterLines];
        }

        return ['quarters' => $quarters, 'stats' => $stats];
    }

    /**
     * @return array{fgm: int, fga: int, tpm: int, tpa: int, ftm: int, fta: int, reb: int, ast: int, stl: int, blk: int, to: int, pf: int}
     */
    protected function statLine(int $points, int $playerIndex, int $quarterIndex): array
    {
        $freeThrowsMade = $points % 2;
        $remaining = $points - $freeThrowsMade;
        $threePointersMade = $points >= 6 ? 2 : 0;
        $fieldGoalsMade = intdiv($remaining - $threePointersMade, 2);

        return [
            'fgm' => $fieldGoalsMade,
            'fga' => $fieldGoalsMade + max(1, intdiv($fieldGoalsMade, 2)),
            'tpm' => $threePointersMade,
            'tpa' => $threePointersMade + ($threePointersMade > 0 ? 1 : 0),
            'ftm' => $freeThrowsMade,
            'fta' => $freeThrowsMade,
            'reb' => ($playerIndex + $quarterIndex + $points) % 4,
            'ast' => ($playerIndex * 2 + $quarterIndex) % 3,
            'stl' => ($playerIndex + $quarterIndex) % 2,
            'blk' => ($playerIndex * 3 + $quarterIndex) % 2,
            'to' => ($points + $quarterIndex) % 3,
            'pf' => ($playerIndex + $quarterIndex * 2) % 4,
        ];
    }

    /**
     * Attach match officials.
     *
     * @return void Nothing.
     */
    protected function attachMatchOfficials(): void
    {
        if ($this->referees === []) {
            return;
        }

        $completed = $this->tournamentBySlug('founders-cup-2026');
        $completedMatches = MatchModel::query()
            ->where('tournament_id', $completed->id)
            ->whereIn('status', ['final', 'forfeit'])
            ->whereNull('series_id')
            ->orderBy('round_number', 'desc')
            ->orderBy('match_number')
            ->get();

        foreach ($completedMatches as $index => $match) {
            $refereeCount = count($this->referees);
            $this->attachOfficial($match, $this->referees[$index % $refereeCount], 'referee');
            $this->attachOfficial($match, $this->referees[($index + 1) % $refereeCount], 'scorekeeper');
        }

        $active = $this->tournamentBySlug('barangay-hoops-2026');
        $activeMatches = MatchModel::query()
            ->where('tournament_id', $active->id)
            ->whereNotIn('status', ['cancelled'])
            ->whereNotNull('home_team_id')
            ->whereNotNull('away_team_id')
            ->orderBy('round_number', 'desc')
            ->orderBy('match_number')
            ->orderBy('game_number')
            ->get();

        foreach ($activeMatches as $index => $match) {
            $refereeCount = count($this->referees);
            $this->attachOfficial($match, $this->referees[$index % $refereeCount], 'referee');
        }

        $league = $this->tournamentBySlug('community-league-2026');
        $leagueMatches = MatchModel::query()
            ->where('tournament_id', $league->id)
            ->where('status', 'final')
            ->orderBy('round_number')
            ->orderBy('match_number')
            ->limit(4)
            ->get();

        foreach ($leagueMatches as $index => $match) {
            $refereeCount = count($this->referees);
            $this->attachOfficial($match, $this->referees[($index + 2) % $refereeCount], 'referee');
        }
    }

    /**
     * Attach official.
     *
     * @param  MatchModel  $match  The match.
     * @param  Referee  $referee  The referee.
     * @param  string  $role  The role.
     * @return void Nothing.
     */
    protected function attachOfficial(MatchModel $match, Referee $referee, string $role): void
    {
        MatchOfficial::create([
            'match_id' => $match->id,
            'referee_id' => $referee->id,
            'role' => $role,
        ]);
    }

    /**
     * Seed announcements.
     *
     * @return void Nothing.
     */
    protected function seedAnnouncements(): void
    {
        $founders = $this->tournamentBySlug('founders-cup-2026');
        $barangay = $this->tournamentBySlug('barangay-hoops-2026');
        $league = $this->tournamentBySlug('community-league-2026');
        $summer = $this->tournamentBySlug('summer-league-2027');
        $announcements = app(AnnouncementService::class);

        $published = [
            [
                'tournament' => $founders,
                'title' => 'Founders Cup crowns a new champion',
                'body' => 'Kapitolyo Kings captured the 2026 Founders Cup with a 79-75 win over Rosario Rockets in the final. The full box score and final bracket are now available.',
                'category' => 'general',
                'audience' => 'all',
                'pinned' => false,
                'published_at' => '2026-08-06 19:30:00',
            ],
            [
                'tournament' => $barangay,
                'title' => 'Barangay Hoops semifinals resume tonight',
                'body' => 'The second semifinal series is tied 1-1. Game 3 tips off tomorrow at 3:00 PM on Court 2. The best-of-three final series follows later this week.',
                'category' => 'schedule',
                'audience' => 'all',
                'pinned' => true,
                'published_at' => '2026-11-22 18:00:00',
            ],
            [
                'tournament' => $barangay,
                'title' => 'North Arena entry and parking guide',
                'body' => 'Player and coach entry opens one hour before tip-off at the north gate. Public parking is available along C. Raymundo Ave. Please carpool where possible.',
                'category' => 'venue',
                'audience' => 'all',
                'pinned' => false,
                'published_at' => '2026-11-18 08:00:00',
            ],
            [
                'tournament' => $barangay,
                'title' => 'Coach briefing: final weekend protocols',
                'body' => 'Coaches are invited to the briefing at 5:00 PM at the North Arena media room. We will cover bench conduct, substitution rules, and the final series schedule.',
                'category' => 'general',
                'audience' => 'coaches',
                'pinned' => false,
                'published_at' => '2026-11-21 17:00:00',
            ],
            [
                'tournament' => $barangay,
                'title' => 'Roster lock reminder for the Vipers',
                'body' => 'Your roster is locked for the Barangay Hoops Championship. Contact the committee if a correction is needed before the semifinal series resumes.',
                'category' => 'eligibility',
                'audience' => 'specific',
                'pinned' => false,
                'published_at' => '2026-11-20 12:00:00',
                'targets' => [$this->coach->id ?? 0],
            ],
            [
                'tournament' => $summer,
                'title' => 'Eligibility checklist for Summer League 2027',
                'body' => 'Coaches: upload a birth certificate for every player and collect guardian consent before submitting. Incomplete rosters will be returned for correction.',
                'category' => 'eligibility',
                'audience' => 'coaches',
                'pinned' => true,
                'published_at' => '2026-11-10 09:00:00',
            ],
            [
                'tournament' => $summer,
                'title' => 'Registration opens for Summer League 2027',
                'body' => 'Team registration for the 2027 Summer League is open until December 15. The U17 division has 16 slots. Submit early to secure your seed.',
                'category' => 'general',
                'audience' => 'all',
                'pinned' => false,
                'published_at' => '2026-11-05 09:00:00',
            ],
            [
                'tournament' => $league,
                'title' => 'Community League final standings',
                'body' => 'The HoopFlow Community League wrapped its group stage. Pasig Pirates finished first on head-to-head tiebreakers. Full standings are available on the public page.',
                'category' => 'general',
                'audience' => 'fans',
                'pinned' => false,
                'published_at' => '2026-11-09 19:00:00',
            ],
        ];

        foreach ($published as $entry) {
            /** @var Tournament $tournament */
            $tournament = $entry['tournament'];
            $announcement = $announcements->publish([
                'tournament_id' => $tournament->id,
                'title' => $entry['title'],
                'body' => $entry['body'],
                'category' => $entry['category'],
                'audience' => $entry['audience'],
                'target_user_ids' => $entry['targets'] ?? [],
                'pinned' => $entry['pinned'],
            ], $this->organizer);

            $announcement->update(['published_at' => $entry['published_at']]);
        }

        Announcement::create([
            'organization_id' => $this->organization()->id,
            'tournament_id' => $barangay->id,
            'title' => 'Venue contingency plan under review',
            'body' => 'Organizers are reviewing a backup venue in case of heavy rain this weekend. This draft will be published once the committee confirms court availability.',
            'category' => 'venue',
            'audience' => 'organizers',
            'target_user_ids' => null,
            'pinned' => false,
            'status' => 'draft',
            'published_at' => null,
            'created_by' => $this->organizer()->id,
        ]);
    }

    /**
     * Seed audit trail.
     *
     * @return void Nothing.
     */
    protected function seedAuditTrail(): void
    {
        $founders = $this->tournamentBySlug('founders-cup-2026');
        $barangay = $this->tournamentBySlug('barangay-hoops-2026');
        $league = $this->tournamentBySlug('community-league-2026');
        $summer = $this->tournamentBySlug('summer-league-2027');
        $vipers = $this->teams[8] ?? null;
        $flames = $this->teams[16] ?? null;
        $nuggets = $this->teams[19] ?? null;

        foreach ([
            [$founders, '2026-06-28 09:00:00'],
            [$league, '2026-09-28 09:00:00'],
            [$barangay, '2026-10-12 09:00:00'],
            [$summer, '2026-10-15 09:00:00'],
        ] as [$tournament, $createdAt]) {
            $this->writeAudit(
                'tournament.create',
                'tournament',
                $tournament->id,
                null,
                ['name' => $tournament->name, 'status' => 'registration-open'],
                $createdAt,
            );
        }

        if ($vipers instanceof Team) {
            $this->writeAudit(
                'team.approve',
                'team',
                $vipers->id,
                ['status' => 'submitted'],
                ['status' => 'approved', 'tournament' => $summer->name],
                '2026-10-25 10:00:00',
            );
            $this->writeAudit(
                'payment.verify',
                'team',
                $vipers->id,
                ['status' => 'pending'],
                ['status' => 'verified', 'amount' => 1500],
                '2026-10-25 10:15:00',
            );
        }

        if ($flames instanceof Team) {
            $this->writeAudit(
                'team.register',
                'team',
                $flames->id,
                null,
                ['tournament' => $summer->name, 'players' => 10],
                '2026-10-28 09:00:00',
            );
        }

        if ($nuggets instanceof Team) {
            $this->writeAudit(
                'team.reject',
                'team',
                $nuggets->id,
                ['status' => 'submitted'],
                ['status' => 'rejected', 'reason' => 'Incomplete roster and documents.'],
                '2026-11-03 10:00:00',
            );
        }

        $this->writeAudit(
            'payment.submit',
            'team',
            $this->teams[15]->id,
            null,
            ['reference' => 'GCASH-BH26-008', 'amount' => 1500],
            '2026-10-29 14:00:00',
        );
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    protected function writeAudit(
        string $action,
        string $entityType,
        int $entityId,
        ?array $before,
        ?array $after,
        string $createdAt,
    ): void {
        AuditLog::create([
            'id' => (int) (AuditLog::max('id') ?? 0) + 1,
            'organization_id' => $this->organization()->id,
            'actor_user_id' => $this->organizer()->id,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'before_json' => $before,
            'after_json' => $after,
            'ip' => null,
            'user_agent' => null,
            'created_at' => Carbon::parse($createdAt),
        ]);
    }

    /**
     * Tournament by slug.
     *
     * @param  string  $slug  The slug.
     * @return Tournament The result.
     */
    protected function tournamentBySlug(string $slug): Tournament
    {
        return Tournament::query()->where('slug', $slug)->firstOrFail();
    }

    /**
     * Organization.
     *
     * @return Organization The result.
     */
    protected function organization(): Organization
    {
        if (! $this->organization instanceof Organization) {
            throw new RuntimeException('The demo organization has not been initialized.');
        }

        return $this->organization;
    }

    /**
     * Organizer.
     *
     * @return User The result.
     */
    protected function organizer(): User
    {
        if (! $this->organizer instanceof User) {
            throw new RuntimeException('The demo organizer has not been initialized.');
        }

        return $this->organizer;
    }
}
