<x-error-page
    code="419"
    title="Your session expired"
    message="Your sign-in timed out while the page was open. Reload and sign in again."
    primary-label="Sign in again"
    :primary-href="route('login')"
    secondary-label="Go home"
    :secondary-href="route('home')"
/>
