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
     * @return array{month: string, year: int, weeks: list<list<array{day: int|null, hasGames: bool, matches: list<array<string, mixed>>}>>}
     */
    public function calendar(): array
    {
        $daysByKey = [];
        foreach ($this->schedule() as $day) {
            $daysByKey[$day['key']] = $day;
        }

        $dateToDayKey = [
            '2026-11-14' => 'day1',
            '2026-11-21' => 'day2',
            '2026-11-22' => 'day3',
        ];

        $first = new \DateTimeImmutable('2026-11-01');
        $leadingBlanks = (int) $first->format('w');

        $cells = [];
        for ($i = 0; $i < $leadingBlanks; $i++) {
            $cells[] = ['day' => null, 'hasGames' => false, 'matches' => []];
        }

        for ($day = 1; $day <= 30; $day++) {
            $date = sprintf('2026-11-%02d', $day);
            $dayKey = $dateToDayKey[$date] ?? null;
            $matches = $dayKey !== null ? ($daysByKey[$dayKey]['matches'] ?? []) : [];
            $cells[] = [
                'day' => $day,
                'hasGames' => $matches !== [],
                'matches' => $matches,
            ];
        }

        while (count($cells) % 7 !== 0) {
            $cells[] = ['day' => null, 'hasGames' => false, 'matches' => []];
        }

        return [
            'month' => 'November 2026',
            'year' => 2026,
            'weeks' => array_chunk($cells, 7),
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
                'copy' => 'Final scores advance winners, update standings, and post to the matchday wire.',
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
                'copy' => 'Eligibility, payments, brackets, schedule, and the audit trail — the whole tournament in one place.',
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
                'copy' => 'Bracket, schedule, live scores, standings, and announcements — readable on the phone in your pocket.',
                'accent' => '#1d4ed8',
            ],
        ];
    }

    /**
     * @return list<array{name: string, role: string, photo: string, initials: string, accent: string, focal: string}>
     */
    public function developers(): array
    {
        return [
            [
                'name' => 'Jeward Mahinay',
                'role' => 'Student developer',
                'photo' => 'img/team/jeward-mahinay.jpg',
                'initials' => 'JM',
                'accent' => '#ea580c',
                'focal' => '50% 38%',
            ],
            [
                'name' => 'Rusty Japos',
                'role' => 'Student developer',
                'photo' => 'img/team/rusty-japos.jpg',
                'initials' => 'RJ',
                'accent' => '#0f766e',
                'focal' => '50% 28%',
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
