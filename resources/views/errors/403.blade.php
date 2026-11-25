{{-- Blade view for 403. --}}
<x-error-page
    code="403"
    title="You don’t have access"
    message="This area is limited to a different role. If you think this is a mistake, ask the tournament organizer."
    primary-label="Go to dashboard"
    :primary-href="auth()->check() ? route('dashboard') : route('home')"
/>
