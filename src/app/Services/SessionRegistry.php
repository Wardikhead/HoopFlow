<?php

/** Domain service for Session registry. */

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

    /**
     * Revoke all sessions except the current one and return the count.
     *
     * @param  User  $user  The user.
     * @param  Request  $request  The request.
     * @return int The result.
     */
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

    /**
     * Revoke every session for a user and return the count.
     *
     * @param  User  $user  The user.
     * @return int The result.
     */
    public function revokeAll(User $user): int
    {
        return DB::table($this->table())
            ->where('user_id', $user->id)
            ->delete();
    }

    /**
     * Return the session storage table name.
     *
     * @return string The result.
     */
    private function table(): string
    {
        return (string) config('session.table', 'sessions');
    }

    /**
     * Derive a readable device label from a user agent.
     *
     * @param  ?string  $userAgent  The user agent.
     * @return string The result.
     */
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

    /**
     * Format a session's last activity time for display.
     *
     * @param  int  $timestamp  The timestamp.
     * @return string The result.
     */
    private function lastActiveLabel(int $timestamp): string
    {
        if ($timestamp <= 0) {
            return 'Unknown';
        }

        return Carbon::createFromTimestamp($timestamp)->diffForHumans();
    }
}
