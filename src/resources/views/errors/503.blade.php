<x-error-page
    code="503"
    title="HoopFlow is briefly offline"
    message="The service is restarting or under maintenance. Try again in a minute."
    primary-label="Try again"
    :primary-href="url()->current()"
    secondary-label="Go home"
    :secondary-href="route('home')"
/>
