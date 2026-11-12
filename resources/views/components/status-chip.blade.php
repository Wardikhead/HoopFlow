@props(['status', 'label' => null])

@php
    $meta = match ($status) {
        'approved' => ['tone' => 'success', 'label' => 'Approved', 'icon' => 'check-circle-fill'],
        'submitted' => ['tone' => 'warning', 'label' => 'Pending', 'icon' => 'hourglass-split'],
        'pending' => ['tone' => 'warning', 'label' => 'Pending', 'icon' => 'hourglass-split'],
        'rejected' => ['tone' => 'danger', 'label' => 'Rejected', 'icon' => 'x-circle-fill'],
        'paid' => ['tone' => 'success', 'label' => 'Paid', 'icon' => 'cash-coin'],
        'partial' => ['tone' => 'warning', 'label' => 'Partial', 'icon' => 'circle-half'],
        'final' => ['tone' => 'success', 'label' => 'Final', 'icon' => 'flag-fill'],
        'forfeit' => ['tone' => 'danger', 'label' => 'Forfeit', 'icon' => 'shield-x'],
        'scheduled' => ['tone' => 'slate', 'label' => 'Scheduled', 'icon' => 'clock'],
        'draft' => ['tone' => 'slate', 'label' => 'Draft', 'icon' => 'pencil'],
        'registration-open' => ['tone' => 'success', 'label' => 'Registration open', 'icon' => 'unlock'],
        'active' => ['tone' => 'primary', 'label' => 'Active', 'icon' => 'broadcast'],
        'completed' => ['tone' => 'gold', 'label' => 'Completed', 'icon' => 'trophy-fill'],
        'upcoming' => ['tone' => 'slate', 'label' => 'Upcoming', 'icon' => 'calendar-event'],
        'verified' => ['tone' => 'success', 'label' => 'Verified', 'icon' => 'patch-check-fill'],
        'missing' => ['tone' => 'danger', 'label' => 'Missing', 'icon' => 'file-earmark-x'],
        default => ['tone' => 'slate', 'label' => ucwords(str_replace('-', ' ', (string) $status)), 'icon' => 'circle'],
    };
@endphp

<span {{ $attributes->merge(['class' => 'hf-chip hf-chip--'.$meta['tone']]) }}>
    <i class="bi bi-{{ $meta['icon'] }}" aria-hidden="true"></i>{{ $label ?? $meta['label'] }}
</span>
