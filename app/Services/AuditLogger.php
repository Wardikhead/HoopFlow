<?php

namespace App\Services;

use App\Models\AuditLog;

class AuditLogger
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function log(
        string $action,
        string $entityType,
        int|string|null $entityId,
        ?array $before = null,
        ?array $after = null,
        ?int $organizationId = null,
        ?int $actorUserId = null,
    ): AuditLog {
        return AuditLog::create([
            'id' => (int) (AuditLog::max('id') ?? 0) + 1,
            'organization_id' => $organizationId,
            'actor_user_id' => $actorUserId ?? auth()->id(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'before_json' => $before,
            'after_json' => $after,
            'ip' => null,
            'user_agent' => null,
            'created_at' => now(),
        ]);
    }
}
