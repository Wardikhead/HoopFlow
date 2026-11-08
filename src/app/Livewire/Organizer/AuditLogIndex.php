<?php

namespace App\Livewire\Organizer;

use App\Models\AuditLog;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.public')]
#[Title('Audit log')]
class AuditLogIndex extends Component
{
    public string $action = 'all';

    public string $entity_type = 'all';

    public ?string $date_from = null;

    public ?string $date_to = null;

    public function clearFilters(): void
    {
        $this->action = 'all';
        $this->entity_type = 'all';
        $this->date_from = null;
        $this->date_to = null;
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function actions(): array
    {
        return AuditLog::query()
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function entityTypes(): array
    {
        return AuditLog::query()
            ->select('entity_type')
            ->distinct()
            ->orderBy('entity_type')
            ->pluck('entity_type')
            ->all();
    }

    /**
     * @return Collection<int, AuditLog>
     */
    #[Computed]
    public function auditLogs()
    {
        $query = AuditLog::query()->with('actorUser');

        if ($this->action !== '' && $this->action !== 'all') {
            $query->where('action', $this->action);
        }

        if ($this->entity_type !== '' && $this->entity_type !== 'all') {
            $query->where('entity_type', $this->entity_type);
        }

        if ($this->date_from !== null && $this->date_from !== '') {
            $query->whereDate('created_at', '>=', $this->date_from);
        }

        if ($this->date_to !== null && $this->date_to !== '') {
            $query->whereDate('created_at', '<=', $this->date_to);
        }

        return $query->orderByDesc('created_at')->get();
    }

    /**
     * @return Collection<int, AuditLog>
     */
    #[Computed]
    public function logs()
    {
        return $this->auditLogs();
    }

    /**
     * @return Collection<int, AuditLog>
     */
    #[Computed]
    public function entries()
    {
        return $this->auditLogs();
    }

    public function render(): View
    {
        return view('livewire.organizer.audit-log-index');
    }
}
