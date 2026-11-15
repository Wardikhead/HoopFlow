<div>
    @section('title', 'Audit log')
    @section('meta-description', 'Audit log - every approval, payment, result, and schedule change recorded across your HoopFlow league.')

    @section('breadcrumbs')
        <x-breadcrumbs :items="[
            ['label' => 'Home', 'href' => route('dashboard')],
            ['label' => 'Audit log'],
        ]" />
    @endsection

    @php
        $allEntries = $this->allEntries;
        $entries = $this->entries;
        $kpis = $this->kpis;
    @endphp

    <header class="hf-page-head hf-mb-3">
        <div>
            <span class="hf-eyebrow">Operations</span>
            <h1 class="hf-page-title">Audit log</h1>
            <p class="hf-page-desc">
                A reverse-chronological record of every approval, payment, bracket, schedule, score, and announcement.
                Corrections keep their previous values.
            </p>
        </div>
        <div class="hf-page-actions">
            <a class="hf-btn" href="{{ route('profile') }}" data-testid="audit-back-profile">
                <i class="bi bi-person" aria-hidden="true"></i> Profile
            </a>
        </div>
    </header>

    <div class="hf-grid hf-grid--4 hf-mb-3">
        <x-kpi-tile label="Entries" :value="$kpis['total']" hint="Seed plus this session" tone="primary" testid="audit-kpi-total" />
        <x-kpi-tile label="Corrections" :value="$kpis['corrections']" hint="Old values retained" tone="alert" testid="audit-kpi-corrections" />
        <x-kpi-tile label="Approvals" :value="$kpis['approvals']" hint="Team decisions" tone="gold" testid="audit-kpi-approvals" />
        <x-kpi-tile label="Payments" :value="$kpis['payments']" hint="Verified references" tone="success" testid="audit-kpi-payments" />
    </div>

    <div class="hf-filter-bar hf-mb-2" data-testid="audit-filters">
        <div class="hf-filter-bar__search">
            <label class="hf-sr-only" for="auditSearch">Search audit log</label>
            <i class="bi bi-search" aria-hidden="true"></i>
            <input
                class="hf-input"
                id="auditSearch"
                type="search"
                placeholder="Summary, action, actor, or entity id"
                wire:model.live.debounce.150ms="search"
                data-testid="audit-search"
            >
        </div>
        <div class="hf-field">
            <label class="hf-sr-only" for="auditEntity">Entity</label>
            <select class="hf-select hf-select--compact" id="auditEntity" wire:model.live="entityType" data-testid="audit-entity-filter">
                <option value="all">All entities</option>
                @foreach ($this->entityTypes as $entityType)
                    <option value="{{ $entityType }}">{{ $this->entityTypeLabel($entityType) }}</option>
                @endforeach
            </select>
        </div>
        <div class="hf-field">
            <label class="hf-sr-only" for="auditActor">Actor</label>
            <select class="hf-select hf-select--compact" id="auditActor" wire:model.live="actor" data-testid="audit-actor-filter">
                <option value="all">All actors</option>
                @foreach ($this->actors as $actorOption)
                    <option value="{{ $actorOption['id'] }}">{{ $actorOption['name'] }}</option>
                @endforeach
            </select>
        </div>
        <button type="button" class="hf-btn hf-btn--sm" wire:click="clearFilters" wire:loading.class="is-loading" data-testid="audit-clear">Clear filters</button>
    </div>

    <p class="hf-results-count hf-mb-2"><span class="hf-small hf-muted" data-testid="audit-count">{{ $entries->count() }} of {{ $allEntries->count() }} entries</span></p>

    <div id="auditList" data-testid="audit-list" wire:loading.class="is-updating" wire:loading.attr="aria-busy">
        @if ($entries->isEmpty())
            <div class="hf-empty" data-testid="audit-empty">
                <i class="bi bi-clock-history" aria-hidden="true"></i>
                <h2>No audit entries match</h2>
                <p>Try another entity, actor, or search term.</p>
                <button type="button" class="hf-btn" wire:click="clearFilters" data-testid="audit-clear-inline">Clear filters</button>
            </div>
        @else
            @php
                $groupedEntries = $entries->groupBy(fn ($log) => $log->created_at?->format('Y-m-d') ?? 'unknown');
            @endphp

            @foreach ($groupedEntries as $day => $logs)
                <section class="hf-audit-day" aria-label="{{ $day === 'unknown' ? 'Undated entries' : \Carbon\Carbon::parse($day)->format('l, F j, Y') }}">
                    <h2 class="hf-audit-day__title">{{ $day === 'unknown' ? 'Undated' : \Carbon\Carbon::parse($day)->format('M j, Y') }}</h2>
                    <div class="hf-timeline">
                        @foreach ($logs as $log)
                            @php
                                $author = $log->actorUser;
                                $entity = $this->entityInfo($log);
                                $changes = $this->changes($log);
                            @endphp
                            <article class="hf-timeline-item" data-testid="audit-entry-{{ $log->id }}">
                                <div class="hf-timeline-meta">
                                    <span>
                                        <i class="bi bi-clock" aria-hidden="true"></i>
                                        {{ $log->created_at?->format('M j, Y · g:i A') ?? 'TBD' }}
                                    </span>
                                    <span>{{ $author?->name ?? 'System' }}</span>
                                    <span class="hf-chip hf-chip--slate">{{ ucfirst($this->actorRole($log)) }}</span>
                                </div>
                                <h3>{{ $this->actionLabel($log) }}</h3>
                                <p>{{ $this->summary($log) }}</p>
                                <div class="hf-flex hf-wrap hf-gap-1 hf-mt-1">
                                    @if ($entity !== null && $entity['url'] !== null)
                                        <a
                                            class="hf-section-link"
                                            href="{{ $entity['url'] }}"
                                            data-testid="audit-{{ $log->entity_type }}-link-{{ $log->id }}"
                                        >{{ $entity['label'] }}</a>
                                    @elseif ($log->entity_id !== null)
                                        <span class="hf-mono">{{ $log->entity_id }}</span>
                                    @endif

                                    @foreach ($changes as $change)
                                        <span class="hf-change">{{ $change }}</span>
                                    @endforeach
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endforeach
        @endif
    </div>
</div>
