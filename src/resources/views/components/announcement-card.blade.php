@props(['announcement', 'compact' => false])

@php
    $category = \App\Models\Announcement::categoryLabel($announcement->category);
    $audience = \App\Models\Announcement::audienceLabel($announcement->audience);
    $author = $announcement->createdBy?->name ?? 'Tournament desk';
    $published = $announcement->published_at ?? $announcement->created_at;
@endphp

<article class="hf-card hf-card--flat {{ $announcement->pinned ? 'hf-announcement--pinned' : '' }}"
         data-testid="announcement-{{ $announcement->id }}">
    <div class="hf-between">
        <span class="hf-chip hf-chip--slate">{{ $category }}</span>
        @if ($announcement->pinned)
            <span class="hf-chip hf-chip--gold"><i class="bi bi-pin-angle-fill" aria-hidden="true"></i>Pinned</span>
        @endif
    </div>
    <h3 class="hf-section-title hf-mt-1">{{ $announcement->title }}</h3>
    <p class="hf-muted hf-small hf-mb-0">{{ $announcement->body }}</p>
    <div class="hf-match-head hf-mt-2">
        <span>{{ $author }}</span>
        <span>{{ $published?->format('M d, Y') ?? 'TBD' }}</span>
    </div>
    @unless ($compact)
        <div class="hf-match-head">
            <span>Audience: {{ $audience }}</span>
            <span>{{ $announcement->tournament?->name ?? 'Tournament' }}</span>
        </div>
    @endunless
</article>
