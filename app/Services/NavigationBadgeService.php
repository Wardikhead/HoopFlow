<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\MatchSlot;
use App\Models\Registration;
use App\Models\Tournament;
use App\Models\User;

class NavigationBadgeService
{
    /**
     * Counts for destinations that currently have work or recent activity.
     * Destinations with a zero count are omitted so the shell can skip them.
     *
     * @return array<string, int>
     */
    public function forUser(User $user): array
    {
        $role = $this->role($user);

        $counts = [
            'announcements' => $this->announcementCount($user, $role),
            'tournaments' => $this->tournamentCount(),
            'schedule' => $this->scheduleCount(),
        ];

        if ($role === 'organizer') {
            $counts['submissions'] = $this->submissionCount();
            $counts['payments'] = $this->paymentCount();
        }

        return array_filter($counts, fn (int $count): bool => $count > 0);
    }

    private function role(User $user): string
    {
        return $user->hasRole('organizer')
            ? 'organizer'
            : ($user->hasRole('coach') ? 'coach' : 'fan');
    }

    private function announcementCount(User $user, string $role): int
    {
        $allowedAudiences = match ($role) {
            'organizer' => ['all', 'coaches', 'organizers', 'fans'],
            'coach' => ['all', 'coaches', 'fans'],
            default => ['all', 'fans'],
        };

        return Announcement::query()
            ->where('status', 'published')
            ->where('published_at', '>=', now()->subDays(7))
            ->where(function ($query) use ($allowedAudiences, $user): void {
                $query->whereIn('audience', $allowedAudiences)
                    ->orWhere(function ($query) use ($user): void {
                        $query->where('audience', 'specific')
                            ->whereJsonContains('target_user_ids', (int) $user->id);
                    });
            })
            ->count();
    }

    private function tournamentCount(): int
    {
        return Tournament::query()
            ->whereNull('archived_at')
            ->where('status', 'registration-open')
            ->count();
    }

    private function submissionCount(): int
    {
        return Registration::query()
            ->whereIn('status', ['submitted', 'pending'])
            ->whereHas('tournament', fn ($query) => $query->whereNull('archived_at'))
            ->count();
    }

    private function paymentCount(): int
    {
        return Registration::query()
            ->where('status', '!=', 'rejected')
            ->whereHas('tournament', fn ($query) => $query->whereNull('archived_at'))
            ->whereHas('payment', fn ($query) => $query->whereIn('status', ['pending', 'partial']))
            ->count();
    }

    private function scheduleCount(): int
    {
        return MatchSlot::query()
            ->whereDate('start_at', now()->toDateString())
            ->whereHas('tournament', fn ($query) => $query->whereNull('archived_at'))
            ->distinct()
            ->count('match_id');
    }
}
