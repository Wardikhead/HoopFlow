@props(['caption' => 'Setting the court'])

{{-- Full-page skeleton shown while a navigation is in flight. The overlay is
     revealed by resources/js/ui/loading.js after a short delay so fast page
     loads never flash a loading screen. --}}
<div class="hf-page-loader{{ auth()->guest() ? ' hf-page-loader--guest' : '' }}" data-hf-page-loader aria-hidden="true">
    <span class="hf-page-loader__track" aria-hidden="true"><span></span></span>

    <div class="hf-page-loader__frame" aria-hidden="true">
        @auth
            <div class="hf-page-loader__rail">
                <div class="hf-page-loader__brand">
                    <x-brand size="md" on-dark />
                </div>

                <div class="hf-page-loader__nav">
                    <span class="hf-skeleton hf-skeleton--rail hf-page-loader__label"></span>
                    @for ($i = 0; $i < 5; $i++)
                        <span class="hf-page-loader__nav-row">
                            <span class="hf-skeleton hf-skeleton--rail hf-page-loader__icon"></span>
                            <span class="hf-skeleton hf-skeleton--rail hf-page-loader__cell hf-page-loader__cell--nav"></span>
                        </span>
                    @endfor

                    <span class="hf-skeleton hf-skeleton--rail hf-page-loader__label hf-page-loader__label--second"></span>
                    @for ($i = 0; $i < 2; $i++)
                        <span class="hf-page-loader__nav-row">
                            <span class="hf-skeleton hf-skeleton--rail hf-page-loader__icon"></span>
                            <span class="hf-skeleton hf-skeleton--rail hf-page-loader__cell hf-page-loader__cell--nav"></span>
                        </span>
                    @endfor
                </div>

                <div class="hf-page-loader__account">
                    <span class="hf-skeleton hf-skeleton--rail hf-page-loader__avatar"></span>
                    <span class="hf-page-loader__account-lines">
                        <span class="hf-skeleton hf-skeleton--rail hf-page-loader__cell hf-page-loader__cell--user"></span>
                        <span class="hf-skeleton hf-skeleton--rail hf-page-loader__cell hf-page-loader__cell--role"></span>
                    </span>
                </div>
            </div>
        @endauth

        <div class="hf-page-loader__stage">
            <div class="hf-page-loader__topbar">
                <span class="hf-skeleton hf-page-loader__title"></span>
                <span class="hf-page-loader__topbar-actions">
                    <span class="hf-skeleton hf-page-loader__circle"></span>
                    <span class="hf-skeleton hf-page-loader__circle"></span>
                </span>
            </div>

            <div class="hf-page-loader__body">
                <p class="hf-page-loader__caption">{{ $caption }}</p>

                <div class="hf-page-loader__heading">
                    <span class="hf-skeleton hf-page-loader__heading-title"></span>
                    <span class="hf-skeleton hf-skeleton--text hf-page-loader__heading-sub"></span>
                </div>

                <div class="hf-page-loader__cards">
                    @for ($i = 0; $i < 3; $i++)
                        <span class="hf-page-loader__card">
                            <span class="hf-skeleton hf-page-loader__eyebrow"></span>
                            <span class="hf-skeleton hf-page-loader__figure"></span>
                            <span class="hf-skeleton hf-page-loader__line"></span>
                        </span>
                    @endfor
                </div>

                <div class="hf-page-loader__panel">
                    <div class="hf-page-loader__panel-head">
                        <span class="hf-skeleton hf-page-loader__title"></span>
                        <span class="hf-skeleton hf-page-loader__pill"></span>
                    </div>

                    @for ($i = 0; $i < 5; $i++)
                        <div class="hf-page-loader__row">
                            <span class="hf-skeleton hf-page-loader__avatar hf-page-loader__avatar--row"></span>
                            <span class="hf-skeleton hf-page-loader__cell"></span>
                            <span class="hf-skeleton hf-page-loader__cell hf-page-loader__cell--muted"></span>
                            <span class="hf-skeleton hf-page-loader__chip"></span>
                        </div>
                    @endfor
                </div>
            </div>
        </div>
    </div>

    <p class="hf-sr-only" role="status" aria-live="polite" data-hf-loader-status></p>
</div>
