<?php

/** Domain service for Announcement service. */

namespace App\Services;

use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AnnouncementService
{
    /**
     * @param  array{tournament_id: int, title: string, body: string, category: string, audience: string, target_user_ids: array<int, int>, pinned: bool}  $data
     */
    public function publish(array $data, ?User $actor = null): Announcement
    {
        return DB::transaction(function () use ($data, $actor): Announcement {
            $organization = Organization::query()->firstOrCreate(
                ['slug' => 'default'],
                ['name' => 'Default Organization'],
            );

            $announcement = Announcement::create([
                'organization_id' => $organization->id,
                'tournament_id' => $data['tournament_id'],
                'title' => $data['title'],
                'body' => $data['body'],
                'category' => $data['category'],
                'audience' => $data['audience'],
                'target_user_ids' => $data['target_user_ids'],
                'pinned' => $data['pinned'],
                'status' => 'published',
                'published_at' => now(),
                'created_by' => $actor?->id,
            ]);

            $this->writeAudit($announcement, 'announcement.publish', null, [
                'category' => $announcement->category,
                'audience' => $announcement->audience,
                'pinned' => $announcement->pinned,
            ], $actor);

            return $announcement;
        });
    }

    /**
     * Pin or unpin an announcement and audit the change.
     *
     * @param  Announcement  $announcement  The announcement.
     * @param  bool  $pinned  The pinned.
     * @param  ?User  $actor  The actor.
     * @return void Nothing.
     */
    public function setPinned(Announcement $announcement, bool $pinned, ?User $actor = null): void
    {
        DB::transaction(function () use ($announcement, $pinned, $actor): void {
            $before = ['pinned' => $announcement->pinned];

            $announcement->update(['pinned' => $pinned]);

            $this->writeAudit(
                $announcement,
                $pinned ? 'announcement.pin' : 'announcement.unpin',
                $before,
                ['pinned' => $announcement->pinned],
                $actor,
            );
        });
    }

    /**
     * Delete an announcement and audit the change.
     *
     * @param  Announcement  $announcement  The announcement.
     * @param  ?User  $actor  The actor.
     * @return void Nothing.
     */
    public function delete(Announcement $announcement, ?User $actor = null): void
    {
        DB::transaction(function () use ($announcement, $actor): void {
            $this->writeAudit($announcement, 'announcement.delete', [
                'title' => $announcement->title,
            ], null, $actor);

            $announcement->delete();
        });
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    protected function writeAudit(Announcement $announcement, string $action, ?array $before, ?array $after, ?User $actor): void
    {
        $announcement->loadMissing('tournament');

        $organizationId = $announcement->organization_id ?? $announcement->tournament?->organization_id;

        AuditLog::create([
            'id' => (int) (AuditLog::max('id') ?? 0) + 1,
            'organization_id' => $organizationId,
            'actor_user_id' => $actor?->id,
            'action' => $action,
            'entity_type' => 'announcement',
            'entity_id' => $announcement->id,
            'before_json' => $before,
            'after_json' => $after,
            'ip' => null,
            'user_agent' => null,
            'created_at' => now(),
        ]);
    }
}
