{{-- Blade view for 500. --}}
<x-error-page
    code="500"
    title="Something went wrong"
    message="The page hit an unexpected error. Your data is safe; try the action again or return to the dashboard."
    primary-label="Go to dashboard"
    :primary-href="auth()->check() ? route('dashboard') : route('home')"
/>
