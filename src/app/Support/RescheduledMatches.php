<?php

namespace App\Support;

use App\Models\AuditLog;

final class RescheduledMatches
{
    /**
     * Match ids with a recorded reschedule event.
     *
     * @param  iterable<int|string>  $matchIds
     * @return list<int>
     */
    public static function ids(iterable $matchIds): array
    {
        $ids = collect($matchIds)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return [];
        }

        return AuditLog::query()
            ->where('action', 'schedule.reschedule')
            ->where('entity_type', 'match')
            ->whereIn('entity_id', $ids)
            ->pluck('entity_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
