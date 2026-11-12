<?php

namespace App\Livewire\Organizer;

use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\MatchModel;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * @property-read Collection<int, AuditLog> $allEntries
 * @property-read Collection<int, AuditLog> $entries
 * @property-read array{total: int, corrections: int, approvals: int, payments: int} $kpis
 * @property-read array<int, string> $entityTypes
 * @property-read array<int, array{id: int, name: string}> $actors
 * @property-read array<string, array{label: string, url: string|null}> $resolvedEntities
 */
#[Layout('layouts.public')]
#[Title('Audit log')]
class AuditLogIndex extends Component
{
    /** @var array<string, string> */
    public const ACTION_LABELS = [
        'tournament.create' => 'Tournament created',
        'tournament.activate' => 'Tournament activated',
        'tournament.complete' => 'Tournament completed',
        'team.register' => 'Team registered',
        'team.approve' => 'Team approved',
        'team.reject' => 'Team rejected',
        'team.roster.add' => 'Player added',
        'team.roster.update' => 'Player updated',
        'team.roster.remove' => 'Player removed',
        'payment.submit' => 'Payment reference submitted',
        'payment.verify' => 'Payment verified',
        'bracket.generate' => 'Bracket generated',
        'bracket.slots' => 'Bracket slots updated',
        'schedule.publish' => 'Schedule published',
        'schedule.rebuild' => 'Schedule rebuilt',
        'schedule.generate' => 'Schedule generated',
        'schedule.reschedule' => 'Match rescheduled',
        'score.save' => 'Score saved',
        'score.correct' => 'Score corrected',
        'score.forfeit' => 'Score forfeited',
        'stats.correct' => 'Box score corrected',
        'announcement.publish' => 'Announcement published',
        'announcement.pin' => 'Announcement pinned',
        'announcement.unpin' => 'Announcement unpinned',
        'announcement.delete' => 'Announcement deleted',
    ];

    /** @var array<string, string> */
    public const ENTITY_LABELS = [
        'tournament' => 'Tournament',
        'team' => 'Team',
        'match' => 'Match',
        'announcement' => 'Announcement',
    ];

    public string $entityType = 'all';

    public string $actor = 'all';

    public string $search = '';

    public function mount(): void
    {
        $entity = request()->query('entity');
        $actor = request()->query('actor');
        $search = request()->query('q');

        $this->entityType = is_string($entity) && $entity !== '' ? $entity : 'all';
        $this->actor = is_string($actor) && $actor !== '' ? $actor : 'all';
        $this->search = is_string($search) ? $search : '';
    }

    public function clearFilters(): void
    {
        $this->entityType = 'all';
        $this->actor = 'all';
        $this->search = '';
    }

    /**
     * @return Collection<int, AuditLog>
     */
    #[Computed]
    public function allEntries(): Collection
    {
        return AuditLog::query()
            ->with(['actorUser.roles'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return Collection<int, AuditLog>
     */
    #[Computed]
    public function entries(): Collection
    {
        $term = mb_strtolower(trim($this->search));

        return $this->allEntries
            ->filter(function (AuditLog $log) use ($term): bool {
                if ($this->entityType !== 'all' && $log->entity_type !== $this->entityType) {
                    return false;
                }

                if ($this->actor !== 'all' && (string) $log->actor_user_id !== $this->actor) {
                    return false;
                }

                if ($term === '') {
                    return true;
                }

                $haystack = mb_strtolower(implode(' ', [
                    $this->summary($log),
                    $log->action,
                    (string) $log->entity_id,
                    $log->actorUser->name ?? 'System',
                    $this->actorRole($log),
                ]));

                return str_contains($haystack, $term);
            })
            ->values();
    }

    /**
     * @return array{total: int, corrections: int, approvals: int, payments: int}
     */
    #[Computed]
    public function kpis(): array
    {
        $entries = $this->allEntries;

        return [
            'total' => $entries->count(),
            'corrections' => $entries->where('action', 'score.correct')->count(),
            'approvals' => $entries->whereIn('action', ['team.approve', 'team.reject'])->count(),
            'payments' => $entries->where('action', 'payment.verify')->count(),
        ];
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function entityTypes(): array
    {
        $types = [];

        foreach ($this->allEntries as $log) {
            $types[$log->entity_type] = true;
        }

        $types = array_keys($types);
        sort($types);

        return $types;
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    #[Computed]
    public function actors(): array
    {
        $actors = [];

        foreach ($this->allEntries as $log) {
            $user = $log->actorUser;

            if ($user instanceof User) {
                $actors[(int) $user->id] = [
                    'id' => (int) $user->id,
                    'name' => $user->name,
                ];
            }
        }

        $actors = array_values($actors);
        usort($actors, fn (array $left, array $right): int => strcasecmp($left['name'], $right['name']));

        return $actors;
    }

    /**
     * @return array<string, array{label: string, url: string|null}>
     */
    #[Computed]
    public function resolvedEntities(): array
    {
        $entities = [];

        $teamIds = $this->entityIds('team');

        if ($teamIds !== []) {
            foreach (Team::query()->whereIn('id', $teamIds)->get() as $team) {
                $entities['team:'.$team->id] = [
                    'label' => $team->name,
                    'url' => (string) route('teamShow', ['team' => $team->id]),
                ];
            }
        }

        $matchIds = $this->entityIds('match');

        if ($matchIds !== []) {
            $matches = MatchModel::query()
                ->with(['homeTeam', 'awayTeam'])
                ->whereIn('id', $matchIds)
                ->get();

            foreach ($matches as $match) {
                $entities['match:'.$match->id] = [
                    'label' => ($match->homeTeam?->shortLabel() ?? 'TBD').' vs '.($match->awayTeam?->shortLabel() ?? 'TBD'),
                    'url' => (string) route('gameStats', ['match' => $match->id]),
                ];
            }
        }

        $tournamentIds = $this->entityIds('tournament');

        if ($tournamentIds !== []) {
            foreach (Tournament::query()->whereIn('id', $tournamentIds)->get() as $tournament) {
                $entities['tournament:'.$tournament->id] = [
                    'label' => $tournament->name,
                    'url' => (string) route('tournamentShow', ['tournament' => $tournament->id]),
                ];
            }
        }

        $announcementIds = $this->entityIds('announcement');

        if ($announcementIds !== []) {
            foreach (Announcement::query()->whereIn('id', $announcementIds)->get() as $announcement) {
                $entities['announcement:'.$announcement->id] = [
                    'label' => $announcement->title,
                    'url' => (string) route('announcements', ['id' => $announcement->tournament_id]),
                ];
            }
        }

        return $entities;
    }

    /**
     * @return array{label: string, url: string|null}|null
     */
    public function entityInfo(AuditLog $log): ?array
    {
        if ($log->entity_id === null) {
            return null;
        }

        return $this->resolvedEntities[$log->entity_type.':'.$log->entity_id] ?? null;
    }

    public function entityTypeLabel(string $type): string
    {
        return self::ENTITY_LABELS[$type] ?? $this->humanize($type);
    }

    public function actionLabel(AuditLog $log): string
    {
        return self::ACTION_LABELS[$log->action] ?? $this->humanize(str_replace('.', ' ', $log->action));
    }

    public function actorRole(AuditLog $log): string
    {
        $user = $log->actorUser;

        if (! $user instanceof User) {
            return 'system';
        }

        $roles = $user->roles->pluck('key')->all();

        foreach (['organizer', 'admin', 'coach', 'scorekeeper', 'fan'] as $role) {
            if (in_array($role, $roles, true)) {
                return $role;
            }
        }

        return 'user';
    }

    public function summary(AuditLog $log): string
    {
        $after = is_array($log->after_json) ? $log->after_json : [];
        $before = is_array($log->before_json) ? $log->before_json : [];
        $entity = $this->entityLabel($log);
        $name = $this->value($after, 'name') ?? $this->value($before, 'name');
        $reference = $this->value($after, 'ref')
            ?? $this->value($after, 'reference')
            ?? $this->value($before, 'reference');
        $method = $this->value($after, 'method') ?? $this->value($before, 'method');

        return match ($log->action) {
            'tournament.create' => 'Created '.$entity,
            'team.register' => 'Registered '.$entity,
            'team.approve' => 'Approved '.$entity,
            'team.reject' => 'Rejected '.$entity,
            'team.roster.add' => 'Added '.($name ?? 'a player').' to the '.$entity.' roster',
            'team.roster.update' => 'Updated '.($name ?? 'a player').' on the '.$entity.' roster',
            'team.roster.remove' => 'Removed '.($name ?? 'a player').' from the '.$entity.' roster',
            'payment.submit' => 'Submitted '.($method ?? 'a').' payment reference'.($reference !== null ? ' '.$reference : '').' for '.$entity,
            'payment.verify' => 'Verified payment for '.$entity.($reference !== null ? ' · '.$reference : ''),
            'bracket.generate' => $this->bracketSummary($after),
            'bracket.slots' => 'Reassigned the '.$entity.' bracket slots',
            'schedule.publish', 'schedule.rebuild', 'schedule.generate' => $this->scheduleSummary($after),
            'schedule.reschedule' => 'Rescheduled '.$entity,
            'score.correct' => 'Corrected '.$entity.' from '.$this->score($before, 'previousScore1', 'previousScore2').' to '.$this->score($after, 'score1', 'score2'),
            'score.save' => 'Saved '.$this->score($after, 'score1', 'score2').' for '.$entity,
            'score.forfeit' => 'Recorded a forfeit for '.$entity,
            'stats.correct' => 'Corrected the '.$entity.' box score',
            'announcement.publish' => 'Published '.$entity,
            'announcement.pin' => 'Pinned '.$entity,
            'announcement.unpin' => 'Unpinned '.$entity,
            'announcement.delete' => 'Deleted '.$entity,
            default => $this->humanize(str_replace('.', ' ', $log->action)).' · '.$entity,
        };
    }

    /**
     * @return array<int, string>
     */
    public function changes(AuditLog $log): array
    {
        $after = is_array($log->after_json) ? $log->after_json : [];
        $before = is_array($log->before_json) ? $log->before_json : [];

        if ($log->action === 'score.correct') {
            $previous = $this->score($before, 'previousScore1', 'previousScore2');
            $current = $this->score($after, 'score1', 'score2');

            if ($previous !== '—' && $current !== '—') {
                return [str_replace('-', '–', $previous).' → '.str_replace('-', '–', $current)];
            }
        }

        $data = $after !== [] ? $after : $before;
        $chips = [];

        foreach ($data as $key => $value) {
            if ($key === 'reason' || $value === null) {
                continue;
            }

            if (is_array($value)) {
                $value = (string) json_encode($value);
            } elseif (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            } elseif (is_scalar($value)) {
                $value = (string) $value;
            } else {
                continue;
            }

            $chips[] = $this->humanize((string) $key).': '.$value;
        }

        return $chips;
    }

    public function humanize(string $key): string
    {
        return Str::headline(str_replace(['.', '_'], ' ', $key));
    }

    public function render(): View
    {
        return view('livewire.organizer.audit-log-index');
    }

    /**
     * @return array<int, int>
     */
    protected function entityIds(string $type): array
    {
        $ids = [];

        foreach ($this->allEntries as $log) {
            if ($log->entity_type === $type && $log->entity_id !== null) {
                $ids[(int) $log->entity_id] = true;
            }
        }

        return array_keys($ids);
    }

    protected function entityLabel(AuditLog $log): string
    {
        $info = $this->entityInfo($log);

        if ($info !== null) {
            return $info['label'];
        }

        $type = $this->entityTypeLabel($log->entity_type);

        return $log->entity_id !== null ? $type.' #'.$log->entity_id : $type;
    }

    /**
     * @param  array<string, mixed>  $after
     */
    protected function bracketSummary(array $after): string
    {
        $teams = $this->intValue($after, 'teams');
        $byes = $this->intValue($after, 'byes') ?? 0;

        if ($teams === null) {
            return 'Generated the bracket';
        }

        return 'Generated the '.($teams + $byes).'-slot bracket';
    }

    /**
     * @param  array<string, mixed>  $after
     */
    protected function scheduleSummary(array $after): string
    {
        $parts = [];

        $courts = $this->intValue($after, 'courts');

        if ($courts !== null) {
            $parts[] = $courts.' courts';
        }

        $slotMinutes = $this->intValue($after, 'slotMinutes');

        if ($slotMinutes !== null) {
            $parts[] = $slotMinutes.'-minute slots';
        }

        $restMinutes = $this->intValue($after, 'restMinutes');

        if ($restMinutes !== null) {
            $parts[] = $restMinutes.'-minute rest';
        }

        return 'Generated the court schedule'.($parts !== [] ? ' with '.implode(' · ', $parts) : '');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function score(array $data, string $first, string $second): string
    {
        $one = $this->value($data, $first);
        $two = $this->value($data, $second);

        if ($one === null || $two === null) {
            return '—';
        }

        return $one.'-'.$two;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function value(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function intValue(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }
}
