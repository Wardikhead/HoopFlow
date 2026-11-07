<?php

namespace App\Services;

use App\Models\Court;
use App\Models\MatchModel;
use App\Models\MatchSlot;
use App\Models\Tournament;
use App\Models\Venue;

class SchedulingService
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function generate(Tournament $tournament, array $options = []): void
    {
        $slotMinutes = (int) ($options['slotMinutes'] ?? 30);
        $restMinutes = (int) ($options['restMinutes'] ?? 15);
        $startDate = (string) ($options['startDate'] ?? $tournament->start_date ?? now()->format('Y-m-d'));

        $matches = MatchModel::where('tournament_id', $tournament->id)->get();

        // Delete existing slots for this tournament's matches
        MatchSlot::whereIn('match_id', $matches->pluck('id'))->delete();

        $courts = $this->resolveCourts($tournament);
        if (empty($courts)) {
            $courts = [null];
        }

        $courtCount = count($courts);
        $cursor = strtotime($startDate.' 09:00:00');

        foreach ($matches as $index => $match) {
            $courtId = $courts[$index % $courtCount];
            $startAt = date('Y-m-d H:i:s', $cursor);
            $endAt = date('Y-m-d H:i:s', $cursor + ($slotMinutes * 60));

            MatchSlot::create([
                'match_id' => $match->id,
                'tournament_id' => $tournament->id,
                'court_id' => $courtId,
                'start_at' => $startAt,
                'end_at' => $endAt,
                'status' => 'published',
                'version' => 1,
                'published_at' => now(),
            ]);

            // advance cursor: after slot minutes + rest, wrap to next court row
            if (($index + 1) % $courtCount === 0) {
                $cursor += ($slotMinutes + $restMinutes) * 60;
            }
        }
    }

    /**
     * @return array<int, int|null>
     */
    protected function resolveCourts(Tournament $tournament): array
    {
        $venueIds = Venue::where('organization_id', $tournament->organization_id)->pluck('id');
        $courts = Court::whereIn('venue_id', $venueIds)->pluck('id')->all();

        return $courts;
    }
}
