<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SessionRegistry
{
    /**
     * @return Collection<int, array{device: string, ip: string, last_active: string, current: bool}>
     */
    public function forUser(User $user, Request $request): Collection
    {
        $currentId = (string) $request->session()->getId();

        return DB::table($this->table())
            ->where('user_id', $user->id)
            ->orderByDesc('last_activity')
            ->get()
            ->map(fn (object $session): array => [
                'device' => $this->deviceLabel($session->user_agent),
                'ip' => is_string($session->ip_address) && $session->ip_address !== ''
                    ? $session->ip_address
                    : 'Unknown network',
                'last_active' => $this->lastActiveLabel((int) $session->last_activity),
                'current' => $currentId !== '' && hash_equals((string) $session->id, $currentId),
            ]);
    }

    public function revokeOthers(User $user, Request $request): int
    {
        $currentId = (string) $request->session()->getId();

        if ($currentId === '') {
            return 0;
        }

        return DB::table($this->table())
            ->where('user_id', $user->id)
            ->where('id', '!=', $currentId)
            ->delete();
    }

    private function table(): string
    {
        return (string) config('session.table', 'sessions');
    }

    private function deviceLabel(?string $userAgent): string
    {
        $userAgent = (string) $userAgent;

        $browser = match (true) {
            str_contains($userAgent, 'Edg') => 'Edge',
            str_contains($userAgent, 'OPR'), str_contains($userAgent, 'Opera') => 'Opera',
            str_contains($userAgent, 'Firefox') => 'Firefox',
            str_contains($userAgent, 'Chrome') => 'Chrome',
            str_contains($userAgent, 'Safari') => 'Safari',
            default => 'Unknown browser',
        };

        $platform = match (true) {
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'iPhone'), str_contains($userAgent, 'iPad') => 'iOS',
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'Mac OS X') => 'macOS',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => 'Unknown device',
        };

        return $browser.' on '.$platform;
    }

    private function lastActiveLabel(int $timestamp): string
    {
        if ($timestamp <= 0) {
            return 'Unknown';
        }

        return Carbon::createFromTimestamp($timestamp)->diffForHumans();
    }
}
