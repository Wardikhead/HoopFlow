<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\Bracket;
use App\Models\BracketSlot;
use App\Models\Court;
use App\Models\Division;
use App\Models\EligibilityDocument;
use App\Models\GameRule;
use App\Models\MatchModel;
use App\Models\MatchSlot;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Player;
use App\Models\PlayerStat;
use App\Models\Registration;
use App\Models\Role;
use App\Models\Team;
use App\Models\TeamMembership;
use App\Models\Tournament;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PrototypeSeeder extends Seeder
{
    /** @var array<string, Team> */
    protected array $teamMap = [];

    /** @var array<string, MatchModel> */
    protected array $matchMap = [];

    /** @var array<string, Court> */
    protected array $courtMap = [];

    /** @var array<string, Tournament> */
    protected array $tournMap = [];

    /** @var array<string, Division> */
    protected array $divisionMap = [];

    /** @var array<string, User> */
    protected array $userMap = [];

    protected ?Organization $org = null;

    protected ?User $organizer = null;

    protected ?User $coach = null;

    protected ?User $fan = null;

    protected int $matchIdSeq = 0;

    protected int $auditIdSeq = 0;

    protected int $matchSlotIdSeq = 0;

    protected int $playerStatIdSeq = 0;

    protected function nextMatchId(): int
    {
        if ($this->matchIdSeq === 0) {
            $this->matchIdSeq = (int) (MatchModel::max('id') ?? 0);
        }

        return ++$this->matchIdSeq;
    }

    protected function nextAuditId(): int
    {
        if ($this->auditIdSeq === 0) {
            $this->auditIdSeq = (int) (AuditLog::max('id') ?? 0);
        }

        return ++$this->auditIdSeq;
    }

    protected function nextMatchSlotId(): int
    {
        if ($this->matchSlotIdSeq === 0) {
            $this->matchSlotIdSeq = (int) (MatchSlot::max('id') ?? 0);
        }

        return ++$this->matchSlotIdSeq;
    }

    protected function nextPlayerStatId(): int
    {
        if ($this->playerStatIdSeq === 0) {
            $this->playerStatIdSeq = (int) (PlayerStat::max('id') ?? 0);
        }

        return ++$this->playerStatIdSeq;
    }

    public function run(): void
    {
        DB::transaction(function () {
            $this->seedUsersAndRoles();
            $this->seedTournaments();
            $this->seedTeamsAndPlayers();
            $this->seedBracketsAndMatches();
            $this->seedAnnouncements();
            $this->seedAudit();
        });
    }

    protected function seedUsersAndRoles(): void
    {
        $this->org = Organization::firstOrCreate(['slug' => 'default'], [
            'name' => 'Default Organization',
            'settings' => null,
        ]);

        foreach (['organizer', 'coach', 'scorekeeper', 'admin', 'fan'] as $key) {
            Role::firstOrCreate(['key' => $key], ['name' => ucfirst($key)]);
        }

        $this->organizer = User::firstOrCreate(['email' => 'organizer@hoopflow.ph'], [
            'first_name' => 'Jeward',
            'last_name' => 'Mahinay',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
        $this->coach = User::firstOrCreate(['email' => 'coach.marcus@hoopflow.ph'], [
            'first_name' => 'Coach Marcus',
            'last_name' => 'Rivera',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
        $this->fan = User::firstOrCreate(['email' => 'fan.juan@hoopflow.ph'], [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);

        $this->userMap['user-organizer'] = $this->organizer;
        $this->userMap['user-coach'] = $this->coach;
        $this->userMap['user-fan'] = $this->fan;

        $orgId = $this->org->id;
        foreach ([
            [$this->organizer, 'organizer'],
            [$this->coach, 'coach'],
            [$this->fan, 'fan'],
        ] as [$user, $roleKey]) {
            $roleId = Role::where('key', $roleKey)->value('id');
            DB::table('user_roles')->updateOrInsert(
                ['user_id' => $user->id, 'role_id' => $roleId, 'scope' => null],
                ['organization_id' => $orgId, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    protected function seedTournaments(): void
    {
        $defs = [
            'tournament-1' => [
                'name' => 'HoopFlow Summer Cup', 'slug' => 'hoopflow-summer-cup', 'status' => 'active',
                'start' => '2026-05-15', 'end' => '2026-05-22', 'fee' => 1500, 'slots' => 16,
                'division' => 'Under 15 (U15)', 'age_max' => 15,
                'venue' => 'San Antonio Central Sports Complex',
                'courts' => [['court-1', 'Court 1 (Main Gym)'], ['court-2', 'Court 2 (Covered)'], ['court-3', 'Court 3'], ['court-4', 'Court 4']],
                'description' => 'Annual inter-barangay grassroots youth tournament with accredited SBP referees.',
            ],
            'tournament-2' => [
                'name' => 'Pasig Youth Hoopfest', 'slug' => 'pasig-youth-hoopfest', 'status' => 'registration-open',
                'start' => '2026-06-01', 'end' => '2026-06-10', 'fee' => 1500, 'slots' => 16,
                'division' => 'Under 17 (U17)', 'age_max' => 17,
                'venue' => 'Ynares Sports Arena, Pasig',
                'courts' => [['court-1', 'Court A'], ['court-2', 'Court B'], ['court-3', 'Court C']],
                'description' => 'Pre-season invitational for competitive secondary school teams.',
            ],
            'tournament-3' => [
                'name' => 'CDO Grassroots Classic', 'slug' => 'cdo-grassroots-classic', 'status' => 'draft',
                'start' => '2026-07-10', 'end' => '2026-07-18', 'fee' => 1000, 'slots' => 8,
                'division' => 'Under 14 (U14)', 'age_max' => 14,
                'venue' => 'CDO City Coliseum',
                'courts' => [['court-1', 'Main Court'], ['court-2', 'Side Court']],
                'description' => 'Regional developmental tournament focused on fundamentals and sportsmanship.',
            ],
        ];

        foreach ($defs as $protoId => $d) {
            $tournament = Tournament::firstOrCreate(['slug' => $d['slug']], [
                'organization_id' => $this->org->id,
                'name' => $d['name'],
                'description' => $d['description'],
                'start_date' => $d['start'],
                'end_date' => $d['end'],
                'status' => $d['status'],
                'payment_method' => 'GCash',
                'payment_account' => '09489666136',
                'rules_json' => ['slots' => $d['slots'], 'fee' => $d['fee'], 'format' => 'single-elimination'],
                'created_by' => $this->organizer->id,
            ]);
            $this->tournMap[$protoId] = $tournament;

            $division = Division::firstOrCreate(
                ['tournament_id' => $tournament->id, 'name' => $d['division']],
                ['age_min' => null, 'age_max' => $d['age_max'], 'gender' => null, 'max_teams' => $d['slots'], 'entry_fee' => $d['fee'], 'currency' => 'PHP']
            );
            $this->divisionMap[$protoId] = $division;

            GameRule::firstOrCreate(['division_id' => $division->id], [
                'tournament_id' => $tournament->id,
                'quarter_minutes' => 10, 'overtime_minutes' => 5, 'foul_limit' => 5,
                'rest_minutes' => 15, 'max_games_per_day' => null, 'tiebreakers_json' => null,
            ]);

            $venue = Venue::firstOrCreate(
                ['organization_id' => $this->org->id, 'name' => $d['venue']],
                ['address' => null, 'timezone' => 'Asia/Manila']
            );

            foreach ($d['courts'] as [$cid, $cname]) {
                $court = Court::firstOrCreate(['venue_id' => $venue->id, 'name' => $cname], ['notes' => null]);
                $this->courtMap[$d['slug'].':'.$cid] = $court;
            }

            $rules = is_array($tournament->rules_json) ? $tournament->rules_json : [];
            $rules['venue_id'] = $venue->id;
            $tournament->rules_json = $rules;
            $tournament->save();
        }
    }

    /** @return array<int, array<string, mixed>> */
    protected function makePlayers(int $teamIndex, int $divisionMax, int $count, bool $pending): array
    {
        $positions = ['Point Guard (PG)', 'Shooting Guard (SG)', 'Small Forward (SF)', 'Power Forward (PF)', 'Center (C)'];
        $first = ['Jayson', 'Kobe', 'Carl', 'Kai', 'June Mar', 'Gabe', 'CJ', 'Dwight', 'Calvin', 'RR', 'Marc', 'Prince', 'Andre', 'Jolo', 'Troy', 'Luis', 'Paolo', 'Nico', 'Miguel', 'Rafael'];
        $last = ['Castro', 'Ramos', 'Cruz', 'Gomez', 'Fajardo', 'Diaz', 'Santos', 'Reyes', 'Bautista', 'Villanueva', 'Macaraeg', 'Torres', 'Aquino', 'Navarro', 'Mendoza', 'Del Rosario', 'Garcia', 'Lim', 'Tan', 'Pascual'];
        $out = [];
        for ($i = 0; $i < $count; $i++) {
            $out[] = [
                'first_name' => $first[($teamIndex * 7 + $i) % count($first)],
                'last_name' => $last[($teamIndex * 3 + $i) % count($last)],
                'jersey_number' => $i + 1,
                'position' => $positions[$i % count($positions)],
                'age' => $divisionMax - ($i % 3),
                'pending' => $pending,
                'missing' => $pending && $i === $count - 1,
            ];
        }

        return $out;
    }

    protected function seedTeamsAndPlayers(): void
    {
        $activeTeams = [
            ['San Antonio Vipers', 'VPR', 'Marcus Rivera', 'Pasig City', '#ea580c', 'paid', 'GCASH-9823412'],
            ['Kapitolyo Knights', 'KNT', 'Danilo Santos', 'Pasig City', '#2563eb', 'paid', 'MAYA-4412093'],
            ['Mandaluyong Tigers', 'TGR', 'Ramon Bautista', 'Mandaluyong City', '#d97706', 'paid', 'CASH-REC-001'],
            ['Ortigas Raptors', 'RPT', 'Kenneth Cruz', 'Pasig City', '#7c3aed', 'paid', 'BANK-BDO-771'],
            ['Ugong Blue Hawks', 'HWK', 'Allan Gomez', 'Pasig City', '#0284c7', 'paid', 'GCASH-1123498'],
            ['Maybunga Titans', 'TTN', 'Eric Villanueva', 'Pasig City', '#059669', 'pending', ''],
            ['Rosario Strikers', 'STR', 'Joel Macaraeg', 'Pasig City', '#dc2626', 'paid', 'GCASH-5512034'],
            ['Pinagbuhatan Warriors', 'WRR', 'Ferdinand Reyes', 'Pasig City', '#475569', 'pending', ''],
            ['Bagong Ilog Falcons', 'BIF', 'Noel Aquino', 'Pasig City', '#0891b2', 'paid', 'MAYA-7731120'],
            ['Kalawaan Comets', 'KLC', 'Edwin Navarro', 'Pasig City', '#9333ea', 'paid', 'GCASH-4409821'],
            ['Manggahan Jets', 'MGJ', 'Rodel Mendoza', 'Pasig City', '#ca8a04', 'paid', 'CASH-REC-002'],
            ['Santolan Saints', 'STL', 'Arnel Garcia', 'Pasig City', '#16a34a', 'paid', 'GCASH-3321908'],
            ['Dela Paz Dragons', 'DPZ', 'Marlon Lim', 'Pasig City', '#e11d48', 'paid', 'BANK-BPI-204'],
            ['Caniogan Cardinals', 'CNG', 'Boyet Tan', 'Pasig City', '#be123c', 'paid', 'GCASH-9988123'],
            ['Sumilang Spartans', 'SMS', 'Dennis Pascual', 'Pasig City', '#334155', 'paid', 'MAYA-1200345'],
        ];

        $t1 = $this->tournMap['tournament-1'];
        $div1 = $this->divisionMap['tournament-1'];

        foreach ($activeTeams as $i => $row) {
            [$name, $short, $coach, $city, $color, $payStatus, $payRef] = $row;
            $team = Team::updateOrCreate(['slug' => strtolower($short)], [
                'organization_id' => $this->org->id,
                'name' => $name,
                'short_name' => $short,
                'avatar_color' => $color,
                'city' => $city,
                'coach_name' => $coach,
                'logo_url' => null,
                'coach_user_id' => null,
            ]);
            $this->teamMap['team-'.($i + 1)] = $team;

            $players = $this->makePlayers($i, 15, 10, $payStatus === 'pending');
            foreach ($players as $p) {
                $player = Player::firstOrCreate(
                    ['team_id' => $team->id, 'first_name' => $p['first_name'], 'last_name' => $p['last_name']],
                    [
                        'birthdate' => now()->subYears($p['age'])->format('Y-m-d'),
                        'jersey_number' => $p['jersey_number'],
                        'position' => $p['position'],
                        'eligibility_status' => $p['missing'] ? 'pending' : 'verified',
                        'consent_at' => $p['missing'] ? null : now(),
                        'guardian_name' => null,
                        'guardian_phone' => null,
                    ]
                );
                EligibilityDocument::firstOrCreate(
                    ['player_id' => $player->id, 'type' => 'birth_certificate'],
                    ['file_url' => 'https://example.com/doc/'.$player->id, 'status' => $p['missing'] ? 'pending' : 'approved', 'reviewed_by' => $p['missing'] ? null : $this->organizer->id, 'reviewed_at' => $p['missing'] ? null : now(), 'notes' => null]
                );
            }

            $reg = Registration::firstOrCreate(
                ['tournament_id' => $t1->id, 'team_id' => $team->id],
                [
                    'division_id' => $div1->id,
                    'status' => 'approved',
                    'submitted_at' => '2026-05-02 09:00:00',
                    'decided_by' => $this->organizer->id,
                    'decided_at' => '2026-05-02 10:00:00',
                    'rejection_reason' => null,
                    'seed' => $i + 1,
                    'roster_locked_at' => null,
                ]
            );

            Payment::firstOrCreate(['registration_id' => $reg->id], [
                'amount' => 1500, 'currency' => 'PHP',
                'method' => $payStatus === 'paid' ? 'GCash' : '',
                'reference_no' => $payRef ?: null, 'receipt_no' => null,
                'status' => $payStatus === 'paid' ? 'verified' : 'pending',
                'paid_at' => $payStatus === 'paid' ? '2026-05-02 09:00:00' : null,
                'recorded_by' => $this->organizer->id, 'remarks' => null,
                'refunded_amount' => 0, 'refunded_at' => null,
            ]);
        }

        // rejected team
        $rejected = Team::updateOrCreate(['slug' => 'npc'], [
            'organization_id' => $this->org->id,
            'name' => 'Napico Nuggets',
            'short_name' => 'NPC',
            'avatar_color' => '#64748b',
            'city' => 'Pasig City',
            'coach_name' => 'Vic Sotto',
            'logo_url' => null,
            'coach_user_id' => null,
        ]);
        $this->teamMap['team-16'] = $rejected;
        $players = $this->makePlayers(15, 15, 10, true);
        foreach ($players as $p) {
            $player = Player::firstOrCreate(
                ['team_id' => $rejected->id, 'first_name' => $p['first_name'], 'last_name' => $p['last_name']],
                [
                    'birthdate' => now()->subYears($p['age'])->format('Y-m-d'),
                    'jersey_number' => $p['jersey_number'],
                    'position' => $p['position'],
                    'eligibility_status' => 'pending',
                    'consent_at' => null,
                    'guardian_name' => null,
                    'guardian_phone' => null,
                ]
            );
            EligibilityDocument::firstOrCreate(
                ['player_id' => $player->id, 'type' => 'birth_certificate'],
                ['file_url' => 'https://example.com/doc/'.$player->id, 'status' => 'pending', 'reviewed_by' => null, 'reviewed_at' => null, 'notes' => null]
            );
        }
        $reg = Registration::firstOrCreate(
            ['tournament_id' => $t1->id, 'team_id' => $rejected->id],
            [
                'division_id' => $div1->id,
                'status' => 'rejected',
                'submitted_at' => '2026-05-02 09:00:00',
                'decided_by' => $this->organizer->id,
                'decided_at' => '2026-05-02 10:00:00',
                'rejection_reason' => 'Withdrew before seeding.',
                'seed' => 16,
                'roster_locked_at' => null,
            ]
        );
        Payment::firstOrCreate(['registration_id' => $reg->id], [
            'amount' => 1500, 'currency' => 'PHP', 'method' => '', 'reference_no' => null,
            'receipt_no' => null, 'status' => 'pending', 'paid_at' => null,
            'recorded_by' => $this->organizer->id, 'remarks' => null,
            'refunded_amount' => 0, 'refunded_at' => null,
        ]);

        // open teams
        $openTeams = [
            ['Pasig Pirates', 'PSG', 'Alvin Cruz', 'Pasig City', '#0e7490', 'approved', 'paid', 'GCASH-1010101'],
            ['Kapitolyo Kings', 'KNG', 'Bong Reyes', 'Pasig City', '#1d4ed8', 'approved', 'paid', 'MAYA-2020202'],
            ['Ugong United', 'UGU', 'Carlo Santos', 'Pasig City', '#047857', 'approved', 'pending', ''],
            ['Rosario Rockets', 'RCK', 'Dante Lim', 'Pasig City', '#b91c1c', 'approved', 'paid', 'CASH-REC-003'],
            ['Maybunga Mavericks', 'MVR', 'Elmer Tan', 'Pasig City', '#7e22ce', 'approved', 'pending', ''],
            ['Pinagbuhatan Pilots', 'PLT', 'Francisco Diaz', 'Pasig City', '#475569', 'approved', 'paid', 'GCASH-3030303'],
            ['Bagong Ilog Bears', 'BRS', 'Glen Aquino', 'Pasig City', '#a16207', 'pending', 'pending', ''],
            ['Kalawaan Kestrels', 'KST', 'Hector Navarro', 'Pasig City', '#0f766e', 'pending', 'pending', ''],
        ];
        $t2 = $this->tournMap['tournament-2'];
        $div2 = $this->divisionMap['tournament-2'];

        foreach ($openTeams as $i => $row) {
            [$name, $short, $coach, $city, $color, $status, $payStatus, $payRef] = $row;
            $team = Team::updateOrCreate(['slug' => strtolower($short).'-open'], [
                'organization_id' => $this->org->id,
                'name' => $name,
                'short_name' => $short,
                'avatar_color' => $color,
                'city' => $city,
                'coach_name' => $coach,
                'logo_url' => null,
                'coach_user_id' => null,
            ]);
            $this->teamMap['team-open-'.($i + 1)] = $team;

            $pending = $status === 'pending';
            $players = $this->makePlayers(100 + $i, 17, 10, $pending);
            foreach ($players as $p) {
                $player = Player::firstOrCreate(
                    ['team_id' => $team->id, 'first_name' => $p['first_name'], 'last_name' => $p['last_name']],
                    [
                        'birthdate' => now()->subYears($p['age'])->format('Y-m-d'),
                        'jersey_number' => $p['jersey_number'],
                        'position' => $p['position'],
                        'eligibility_status' => $p['missing'] ? 'pending' : 'verified',
                        'consent_at' => $p['missing'] ? null : now(),
                        'guardian_name' => null,
                        'guardian_phone' => null,
                    ]
                );
                EligibilityDocument::firstOrCreate(
                    ['player_id' => $player->id, 'type' => 'birth_certificate'],
                    ['file_url' => 'https://example.com/doc/'.$player->id, 'status' => $p['missing'] ? 'pending' : 'approved', 'reviewed_by' => $p['missing'] || $pending ? null : $this->organizer->id, 'reviewed_at' => $p['missing'] || $pending ? null : now(), 'notes' => null]
                );
            }

            $regStatus = $status === 'pending' ? 'submitted' : 'approved';
            $reg = Registration::firstOrCreate(
                ['tournament_id' => $t2->id, 'team_id' => $team->id],
                [
                    'division_id' => $div2->id,
                    'status' => $regStatus,
                    'submitted_at' => '2026-05-20 09:00:00',
                    'decided_by' => $regStatus === 'approved' ? $this->organizer->id : null,
                    'decided_at' => $regStatus === 'approved' ? '2026-05-20 10:00:00' : null,
                    'rejection_reason' => null,
                    'seed' => $i + 1,
                    'roster_locked_at' => null,
                ]
            );
            Payment::firstOrCreate(['registration_id' => $reg->id], [
                'amount' => 1500, 'currency' => 'PHP',
                'method' => $payStatus === 'paid' ? 'GCash' : '',
                'reference_no' => $payRef ?: null, 'receipt_no' => null,
                'status' => $payStatus === 'paid' ? 'verified' : 'pending',
                'paid_at' => $payStatus === 'paid' ? '2026-05-20 09:00:00' : null,
                'recorded_by' => $this->organizer->id, 'remarks' => null,
                'refunded_amount' => 0, 'refunded_at' => null,
            ]);
        }

        // cdo teams
        $cdoTeams = [
            ['CDO Stags', 'CDS', 'Rico Bautista', 'Cagayan de Oro'],
            ['Cagayan Cobras', 'CAG', 'Nestor Lim', 'Cagayan de Oro'],
            ['Bukidnon Braves', 'BKB', 'Oscar Reyes', 'Malaybalay'],
            ['Misamis Mariners', 'MSM', 'Paeng Cruz', 'Oroquieta'],
            ['Iligan Ironhawks', 'ILG', 'Quincy Tan', 'Iligan'],
            ['Gingoog Giants', 'GGG', 'Rene Santos', 'Gingoog'],
            ['Valencia Vipers', 'VLV', 'Sandy Gomez', 'Valencia'],
            ['Malaybalay Monarchs', 'MBM', 'Tito Diaz', 'Malaybalay'],
        ];
        $t3 = $this->tournMap['tournament-3'];
        $div3 = $this->divisionMap['tournament-3'];

        foreach ($cdoTeams as $i => $row) {
            [$name, $short, $coach, $city] = $row;
            $team = Team::updateOrCreate(['slug' => strtolower($short).'-cdo'], [
                'organization_id' => $this->org->id,
                'name' => $name,
                'short_name' => $short,
                'avatar_color' => '#64748b',
                'city' => $city,
                'coach_name' => $coach,
                'logo_url' => null,
                'coach_user_id' => null,
            ]);
            $this->teamMap['team-cdo-'.($i + 1)] = $team;

            $players = $this->makePlayers(200 + $i, 14, 10, false);
            foreach ($players as $p) {
                $player = Player::firstOrCreate(
                    ['team_id' => $team->id, 'first_name' => $p['first_name'], 'last_name' => $p['last_name']],
                    [
                        'birthdate' => now()->subYears($p['age'])->format('Y-m-d'),
                        'jersey_number' => $p['jersey_number'],
                        'position' => $p['position'],
                        'eligibility_status' => 'verified',
                        'consent_at' => now(),
                        'guardian_name' => null,
                        'guardian_phone' => null,
                    ]
                );
                EligibilityDocument::firstOrCreate(
                    ['player_id' => $player->id, 'type' => 'birth_certificate'],
                    ['file_url' => 'https://example.com/doc/'.$player->id, 'status' => 'approved', 'reviewed_by' => $this->organizer->id, 'reviewed_at' => now(), 'notes' => null]
                );
            }

            $reg = Registration::firstOrCreate(
                ['tournament_id' => $t3->id, 'team_id' => $team->id],
                [
                    'division_id' => $div3->id,
                    'status' => 'approved',
                    'submitted_at' => '2026-06-01 09:00:00',
                    'decided_by' => $this->organizer->id,
                    'decided_at' => '2026-06-01 10:00:00',
                    'rejection_reason' => null,
                    'seed' => $i + 1,
                    'roster_locked_at' => null,
                ]
            );
            Payment::firstOrCreate(['registration_id' => $reg->id], [
                'amount' => 1000, 'currency' => 'PHP', 'method' => 'GCash',
                'reference_no' => 'GCASH-CDO-'.($i + 1), 'receipt_no' => null,
                'status' => 'verified', 'paid_at' => '2026-06-01 09:00:00',
                'recorded_by' => $this->organizer->id, 'remarks' => null,
                'refunded_amount' => 0, 'refunded_at' => null,
            ]);
        }

        // coach team membership
        $vipers = $this->teamMap['team-1'];
        TeamMembership::firstOrCreate(
            ['team_id' => $vipers->id, 'user_id' => $this->coach->id],
            ['role' => 'coach', 'status' => 'active']
        );
        $vipers->update(['coach_user_id' => $this->coach->id]);
    }

    protected function teamByName(string $name): Team
    {
        foreach ($this->teamMap as $t) {
            if ($t->name === $name) {
                return $t;
            }
        }

        return Team::where('name', $name)->firstOrFail();
    }

    protected function seedBracketsAndMatches(): void
    {
        $t1 = $this->tournMap['tournament-1'];
        $div1 = $this->divisionMap['tournament-1'];

        $bracket = Bracket::firstOrCreate(
            ['division_id' => $div1->id, 'name' => 'Summer Cup Bracket'],
            [
                'format' => 'single_elim',
                'seeding_method' => 'manual',
                'status' => 'generated',
                'generated_at' => '2026-05-15 10:00:00',
            ]
        );

        $byName = [];
        foreach ($this->teamMap as $t) {
            $byName[$t->name] = $t;
        }

        $courtIds = [];
        foreach (['court-1', 'court-2', 'court-3', 'court-4'] as $cid) {
            $c = $this->courtMap['hoopflow-summer-cup:'.$cid] ?? null;
            if ($c) {
                $courtIds[] = $c->id;
            }
        }
        if (empty($courtIds)) {
            $courtIds = [1];
        }

        // R16
        $r16 = [
            ['r16-1', 1, 'San Antonio Vipers', null, null, null, 'final', true],
            ['r16-2', 2, 'Pinagbuhatan Warriors', 'Bagong Ilog Falcons', 66, 61, 'final', false],
            ['r16-3', 3, 'Ortigas Raptors', 'Dela Paz Dragons', 69, 72, 'final', false],
            ['r16-4', 4, 'Ugong Blue Hawks', 'Santolan Saints', 70, 64, 'final', false],
            ['r16-5', 5, 'Kapitolyo Knights', 'Sumilang Spartans', 77, 70, 'final', false],
            ['r16-6', 6, 'Rosario Strikers', 'Kalawaan Comets', 66, 68, 'final', false],
            ['r16-7', 7, 'Mandaluyong Tigers', 'Caniogan Cardinals', 20, 0, 'forfeit', false],
            ['r16-8', 8, 'Maybunga Titans', 'Manggahan Jets', 65, 60, 'final', false],
        ];
        $gameIndex = 0;
        foreach ($r16 as [$pid, $mslot, $t1name, $t2name, $s1, $s2, $status, $bye]) {
            $home = $byName[$t1name];
            $away = $t2name ? $byName[$t2name] : null;
            $winner = null;
            if ($bye) {
                $winner = $home->id;
            } else {
                $winner = ($s1 > $s2) ? $home->id : $away->id;
            }
            $completedAt = '2026-05-16 09:00:00';
            $m = MatchModel::firstOrCreate(
                ['bracket_id' => $bracket->id, 'round_number' => 16, 'match_number' => $mslot],
                [
                    'id' => $this->nextMatchId(),
                    'tournament_id' => $t1->id,
                    'home_team_id' => $home->id,
                    'away_team_id' => $away?->id,
                    'home_score' => $s1,
                    'away_score' => $s2,
                    'winner_team_id' => $winner,
                    'status' => $status,
                    'completed_at' => $completedAt,
                    'forfeit_reason' => null,
                ]
            );
            $this->matchMap[$pid] = $m;

            BracketSlot::firstOrCreate(
                ['bracket_id' => $bracket->id, 'round_number' => 16, 'match_number' => $mslot, 'slot' => 'home'],
                ['source_type' => 'seed', 'source_match_id' => null, 'source_outcome' => null, 'team_id' => $home->id]
            );
            BracketSlot::firstOrCreate(
                ['bracket_id' => $bracket->id, 'round_number' => 16, 'match_number' => $mslot, 'slot' => 'away'],
                ['source_type' => 'seed', 'source_match_id' => null, 'source_outcome' => null, 'team_id' => $away?->id]
            );

            if (! $bye) {
                $sched = in_array($pid, ['r16-5', 'r16-6', 'r16-7', 'r16-8']) ? '2026-05-16 09:30:00' : '2026-05-16 09:00:00';
                $courtId = $courtIds[$gameIndex % count($courtIds)];
                $gameIndex++;
                MatchSlot::firstOrCreate(['match_id' => $m->id], [
                    'id' => $this->nextMatchSlotId(),
                    'tournament_id' => $t1->id,
                    'court_id' => $courtId,
                    'start_at' => $sched,
                    'end_at' => date('Y-m-d H:i:s', strtotime($sched) + 30 * 60),
                    'status' => 'published',
                    'version' => 1,
                    'published_at' => now(),
                ]);
            }
        }

        // QF
        $qf = [
            ['qf-1', 1, 'San Antonio Vipers', 'Pinagbuhatan Warriors', 78, 70, 'final', '2026-05-18 13:00:00', ['team1' => [20, 20, 19, 19], 'team2' => [18, 18, 17, 17]]],
            ['qf-2', 2, 'Dela Paz Dragons', 'Ugong Blue Hawks', 64, 69, 'final', '2026-05-18 13:00:00', ['team1' => [16, 16, 16, 16], 'team2' => [18, 17, 17, 17]]],
            ['qf-3', 3, 'Kapitolyo Knights', 'Kalawaan Comets', null, null, 'scheduled', '2026-05-18 14:00:00', null],
            ['qf-4', 4, 'Mandaluyong Tigers', 'Maybunga Titans', null, null, 'scheduled', '2026-05-18 14:00:00', null],
        ];
        foreach ($qf as [$pid, $mslot, $t1name, $t2name, $s1, $s2, $status, $sched, $quarters]) {
            $home = $byName[$t1name];
            $away = $byName[$t2name];
            $winner = null;
            if ($status === 'final') {
                $winner = ($s1 > $s2) ? $home->id : $away->id;
            }
            $m = MatchModel::firstOrCreate(
                ['bracket_id' => $bracket->id, 'round_number' => 8, 'match_number' => $mslot],
                [
                    'id' => $this->nextMatchId(),
                    'tournament_id' => $t1->id,
                    'home_team_id' => $home->id,
                    'away_team_id' => $away->id,
                    'home_score' => $s1,
                    'away_score' => $s2,
                    'winner_team_id' => $winner,
                    'status' => $status,
                    'completed_at' => $sched,
                    'forfeit_reason' => null,
                    'quarters_json' => $quarters,
                ]
            );
            $this->matchMap[$pid] = $m;

            $courtId = $courtIds[($mslot - 1) % min(2, count($courtIds))];
            MatchSlot::firstOrCreate(['match_id' => $m->id], [
                'id' => $this->nextMatchSlotId(),
                'tournament_id' => $t1->id,
                'court_id' => $courtId,
                'start_at' => $sched,
                'end_at' => date('Y-m-d H:i:s', strtotime($sched) + 30 * 60),
                'status' => 'published',
                'version' => 1,
                'published_at' => now(),
            ]);

            BracketSlot::firstOrCreate(
                ['bracket_id' => $bracket->id, 'round_number' => 8, 'match_number' => $mslot, 'slot' => 'home'],
                ['source_type' => 'winner', 'source_match_id' => $this->matchMap['r16-'.(($mslot - 1) * 2 + 1)]->id, 'source_outcome' => 'winner', 'team_id' => $home->id]
            );
            BracketSlot::firstOrCreate(
                ['bracket_id' => $bracket->id, 'round_number' => 8, 'match_number' => $mslot, 'slot' => 'away'],
                ['source_type' => 'winner', 'source_match_id' => $this->matchMap['r16-'.(($mslot - 1) * 2 + 2)]->id, 'source_outcome' => 'winner', 'team_id' => $away->id]
            );
        }

        // SF
        $sf = [
            ['sf-1', 1, 'San Antonio Vipers', 'Ugong Blue Hawks', '2026-05-20 15:00:00'],
            ['sf-2', 2, null, null, '2026-05-20 16:00:00'],
        ];
        foreach ($sf as [$pid, $mslot, $t1name, $t2name, $sched]) {
            $home = $t1name ? $byName[$t1name] : null;
            $away = $t2name ? $byName[$t2name] : null;
            $m = MatchModel::firstOrCreate(
                ['bracket_id' => $bracket->id, 'round_number' => 4, 'match_number' => $mslot],
                [
                    'id' => $this->nextMatchId(),
                    'tournament_id' => $t1->id,
                    'home_team_id' => $home?->id,
                    'away_team_id' => $away?->id,
                    'home_score' => null,
                    'away_score' => null,
                    'winner_team_id' => null,
                    'status' => 'scheduled',
                    'completed_at' => null,
                    'forfeit_reason' => null,
                ]
            );
            $this->matchMap[$pid] = $m;
            MatchSlot::firstOrCreate(['match_id' => $m->id], [
                'id' => $this->nextMatchSlotId(),
                'tournament_id' => $t1->id,
                'court_id' => $courtIds[0],
                'start_at' => $sched,
                'end_at' => date('Y-m-d H:i:s', strtotime($sched) + 30 * 60),
                'status' => 'published',
                'version' => 1,
                'published_at' => now(),
            ]);
        }

        // Final
        $finalM = MatchModel::firstOrCreate(
            ['bracket_id' => $bracket->id, 'round_number' => 2, 'match_number' => 1],
            [
                'id' => $this->nextMatchId(),
                'tournament_id' => $t1->id,
                'home_team_id' => null,
                'away_team_id' => null,
                'home_score' => null,
                'away_score' => null,
                'winner_team_id' => null,
                'status' => 'scheduled',
                'completed_at' => null,
                'forfeit_reason' => null,
            ]
        );
        $this->matchMap['final-1'] = $finalM;
        MatchSlot::firstOrCreate(['match_id' => $finalM->id], [
            'id' => $this->nextMatchSlotId(),
            'tournament_id' => $t1->id,
            'court_id' => $courtIds[0],
            'start_at' => '2026-05-22 16:00:00',
            'end_at' => '2026-05-22 16:30:00',
            'status' => 'published',
            'version' => 1,
            'published_at' => now(),
        ]);

        $this->seedBoxScores();
    }

    protected function seedBoxScores(): void
    {
        $qf1 = $this->matchMap['qf-1'];
        $vipers = $this->teamMap['team-1'];
        $warriors = $this->teamMap['team-8'];
        $this->seedBoxScoreForMatch($qf1, $vipers, 78, $this->tournMap['tournament-1']->id);
        $this->seedBoxScoreForMatch($qf1, $warriors, 70, $this->tournMap['tournament-1']->id);

        $qf2 = $this->matchMap['qf-2'];
        $dragons = $this->teamMap['team-13'];
        $hawks = $this->teamMap['team-5'];
        $this->seedBoxScoreForMatch($qf2, $dragons, 64, $this->tournMap['tournament-1']->id);
        $this->seedBoxScoreForMatch($qf2, $hawks, 69, $this->tournMap['tournament-1']->id);
    }

    protected function seedBoxScoreForMatch(MatchModel $match, Team $team, int $total, int $tournamentId): void
    {
        $players = $team->players()->get();
        $count = $players->count();
        if ($count === 0) {
            return;
        }
        $base = intdiv($total, $count);
        $rem = $total % $count;
        $i = 0;
        foreach ($players as $player) {
            $pts = $base + ($i < $rem ? 1 : 0);
            $statAttrs = [
                'id' => $this->nextPlayerStatId(),
                'team_id' => $team->id,
                'points' => $pts,
                'assists' => ($i * 3 + 1) % 7,
                'rebounds' => ($i * 2 + 2) % 9,
                'steals' => 0,
                'blocks' => 0,
                'fouls' => 0,
            ];
            if (DB::getDriverName() === 'pgsql') {
                $statAttrs['tournament_id'] = $tournamentId;
            }
            PlayerStat::firstOrCreate(
                ['match_id' => $match->id, 'player_id' => $player->id],
                $statAttrs
            );
            $i++;
        }
    }

    protected function seedAnnouncements(): void
    {
        $t1 = $this->tournMap['tournament-1'];
        $t2 = $this->tournMap['tournament-2'];
        $t3 = $this->tournMap['tournament-3'];

        $defs = [
            [$t1, 'Championship week moves to Court 1', 'All semifinal and final games will be played on Court 1. Gate opens 45 minutes before tip-off.', 'venue', 'all', [], true, '2026-05-18 18:00:00'],
            [$t1, 'Roster verification deadline is May 19', 'Coaches must upload the final PSA birth certificate and guardian consent for every player before the semifinals.', 'eligibility', 'coaches', [], false, '2026-05-17 10:00:00'],
            [$t2, 'Pasig Youth Hoopfest registration closes June 1', 'Submit your team registration, twelve-player roster, and GCash reference before the deadline.', 'schedule', 'all', [], false, '2026-05-20 09:00:00'],
            [$t3, 'Court assignments posted for the CDO Classic', 'The preliminary court assignments are available on the tournament page. Brackets will be generated after the seeding meeting.', 'venue', 'all', [], false, '2026-06-02 11:00:00'],
        ];
        foreach ($defs as [$tourn, $title, $body, $category, $audience, $targetUserIds, $pinned, $at]) {
            Announcement::updateOrCreate(
                ['tournament_id' => $tourn->id, 'title' => $title],
                [
                    'organization_id' => $this->org->id,
                    'body' => $body,
                    'category' => $category,
                    'audience' => $audience,
                    'target_user_ids' => $targetUserIds,
                    'pinned' => $pinned,
                    'status' => 'published',
                    'published_at' => $at,
                    'created_by' => $this->organizer->id,
                ]
            );
        }
    }

    protected function seedAudit(): void
    {
        $orgId = $this->org->id;
        $vipers = $this->teamMap['team-1'];
        $t1 = $this->tournMap['tournament-1'];
        $qf1 = $this->matchMap['qf-1'];
        $ann1 = Announcement::where('title', 'Championship week moves to Court 1')->first();

        $entries = [
            ['2026-05-10 10:00:00', 'user-organizer', 'tournament.create', 'tournament', $t1->id, null, ['slots' => 16, 'division' => 'Under 15 (U15)']],
            ['2026-05-12 09:00:00', 'user-coach', 'team.register', 'team', $vipers->id, null, ['players' => 10]],
            ['2026-05-13 14:00:00', 'user-organizer', 'announcement.publish', 'announcement', $ann1?->id, null, ['pinned' => true]],
            ['2026-05-14 09:30:00', 'user-organizer', 'team.approve', 'team', $this->teamMap['team-5']->id, ['status' => 'submitted'], ['status' => 'approved']],
            ['2026-05-15 17:00:00', 'user-organizer', 'schedule.generate', 'tournament', $t1->id, null, ['courts' => 4, 'slotMinutes' => 30, 'restMinutes' => 15]],
            ['2026-05-16 08:00:00', 'user-organizer', 'bracket.generate', 'tournament', $t1->id, null, ['teams' => 15, 'byes' => 1]],
            ['2026-05-18 15:40:00', 'user-organizer', 'payment.verify', 'team', $vipers->id, ['status' => 'pending'], ['status' => 'paid', 'ref' => 'GCASH-9823412']],
            ['2026-05-18 16:20:00', 'user-organizer', 'score.correct', 'match', $qf1->id, ['previousScore1' => 74, 'previousScore2' => 70], ['score1' => 78, 'score2' => 70, 'reason' => 'Shot-clock correction after referee review.']],
        ];
        foreach ($entries as [$at, $actorProto, $action, $etype, $eid, $before, $after]) {
            $actor = $this->userMap[$actorProto] ?? $this->organizer;
            AuditLog::firstOrCreate(
                ['actor_user_id' => $actor->id, 'action' => $action, 'entity_id' => $eid, 'created_at' => $at],
                [
                    'id' => $this->nextAuditId(),
                    'organization_id' => $orgId,
                    'entity_type' => $etype,
                    'before_json' => $before,
                    'after_json' => $after,
                    'ip' => '127.0.0.1',
                    'user_agent' => 'PrototypeSeeder',
                ]
            );
        }
    }
}
