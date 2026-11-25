{{-- Blade view for 429. --}}
<x-error-page
    code="429"
    title="Too many attempts"
    message="The server is rate-limiting requests. Wait a moment, then try again."
    primary-label="Go home"
    :primary-href="route('home')"
    secondary-label="Go back"
/>
