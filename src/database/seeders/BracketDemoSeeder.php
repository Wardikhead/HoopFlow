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
use App\Services\BracketService;
use App\Services\SchedulingService;
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
        app(BracketService::class)->generate($tournament, 'single-elimination');
        app(SchedulingService::class)->generate($tournament);
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
            'end' => '2027-01-15',
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
        app(BracketService::class)->generate($tournament, 'double-elimination');
        app(SchedulingService::class)->generate($tournament);
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
        app(BracketService::class)->generate($tournament, 'round-robin');
        app(SchedulingService::class)->generate($tournament);
    }
}
