<div>
@php
    $logs = $this->auditLogs;
@endphp

@section('title', 'Audit log')

<header class="hf-page-head hf-mb-3">
    <div>
        <span class="hf-eyebrow">Operations</span>
        <h1 class="hf-page-title">Audit log</h1>
        <p class="hf-page-desc">
            A reverse-chronological record of approvals, payments, brackets, schedules, scores, and announcements.
        </p>
    </div>
</header>

<div class="hf-card hf-mb-3" data-testid="audit-filters">
    <div class="hf-form-grid">
        <div class="hf-field">
            <label class="hf-label" for="auditAction">Action</label>
            <select class="hf-select" id="auditAction" wire:model.live="action" data-testid="audit-action-filter">
                <option value="all">All actions</option>
                @foreach ($this->actions as $actionOption)
                    <option value="{{ $actionOption }}">{{ $actionOption }}</option>
                @endforeach
            </select>
        </div>
        <div class="hf-field">
            <label class="hf-label" for="auditEntityType">Entity type</label>
            <select class="hf-select" id="auditEntityType" wire:model.live="entity_type" data-testid="audit-entity-filter">
                <option value="all">All entities</option>
                @foreach ($this->entityTypes as $entityOption)
                    <option value="{{ $entityOption }}">{{ $entityOption }}</option>
                @endforeach
            </select>
        </div>
        <div class="hf-field">
            <label class="hf-label" for="auditDateFrom">From</label>
            <input class="hf-input" id="auditDateFrom" type="date" wire:model.live="date_from" data-testid="audit-date-from">
        </div>
        <div class="hf-field">
            <label class="hf-label" for="auditDateTo">To</label>
            <input class="hf-input" id="auditDateTo" type="date" wire:model.live="date_to" data-testid="audit-date-to">
        </div>
    </div>
    <div class="hf-btn-row hf-mt-2">
        <button type="button" class="hf-btn hf-btn--sm" wire:click="clearFilters" data-testid="audit-clear-filters">Clear filters</button>
        <span class="hf-small hf-muted" data-testid="audit-count">{{ $logs->count() }} entries</span>
    </div>
</div>

<div class="hf-card" data-testid="audit-list">
    @if ($logs->isEmpty())
        <div class="hf-empty" data-testid="audit-empty">
            <i class="bi bi-clock-history" aria-hidden="true"></i>
            <h3>No audit entries match</h3>
            <p>Try another action, entity type, or date range.</p>
        </div>
    @else
        <div class="hf-table-wrap">
            <table class="hf-table" data-testid="audit-table">
                <thead>
                    <tr>
                        <th scope="col">When</th>
                        <th scope="col">Actor</th>
                        <th scope="col">Action</th>
                        <th scope="col">Entity</th>
                        <th scope="col">Details</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($logs as $log)
                        <tr data-testid="audit-entry-{{ $log->id }}">
                            <td>{{ $log->created_at?->format('M d, Y H:i') ?? '' }}</td>
                            <td>{{ $log->actorUser?->name ?? 'Unknown' }}</td>
                            <td>{{ $log->action }}</td>
                            <td>
                                {{ $log->entity_type }}
                                @if ($log->entity_id !== null)
                                    <span class="hf-mono">#{{ $log->entity_id }}</span>
                                @endif
                            </td>
                            <td>
                                @if (is_array($log->after_json))
                                    {{ json_encode($log->after_json) }}
                                @elseif (is_array($log->before_json))
                                    {{ json_encode($log->before_json) }}
                                @else
                                    {{ $log->ip }}
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
</div>
