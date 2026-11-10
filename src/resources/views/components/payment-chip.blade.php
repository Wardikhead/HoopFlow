@props(['payment' => null, 'label' => null])

@php
    $status = match ($payment?->status) {
        'verified', 'paid' => 'paid',
        null, '' => 'pending',
        default => (string) $payment?->status,
    };
@endphp

<x-status-chip :status="$status" :label="$label" {{ $attributes }} />
