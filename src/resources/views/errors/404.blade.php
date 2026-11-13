<x-error-page
    code="404"
    title="Page not found"
    message="That page moved, was renamed, or never existed. Check the address or head back to the dashboard."
    primary-label="Go to dashboard"
    :primary-href="auth()->check() ? route('dashboard') : route('home')"
/>
