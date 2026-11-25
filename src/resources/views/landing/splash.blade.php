{{-- Blade view for Splash. --}}
<div class="hf-lp-splash" data-lp-splash aria-hidden="true">
    <div class="hf-lp-splash__court" aria-hidden="true">
        <span class="hf-lp-splash__ring is-1"></span>
        <span class="hf-lp-splash__ring is-2"></span>
        <span class="hf-lp-splash__ring is-3"></span>
    </div>

    <div class="hf-lp-splash__inner">
        <span class="hf-lp-splash__mark">
            <span class="hf-lp-splash__halo" aria-hidden="true"></span>

            <svg class="hf-lp-splash__logo" viewBox="0 0 302 292" role="img" aria-label="HoopFlow">
                <defs>
                    <mask id="hf-splash-draw" maskUnits="userSpaceOnUse" x="0" y="0" width="302" height="292">
                        <g fill="none" stroke="#fff" stroke-linecap="round" stroke-linejoin="round">
                            <circle class="is-ball" cx="151" cy="60" r="48" stroke-width="30" pathLength="1" style="--d: 120ms" />

                            <path class="is-bar" d="M57 79h46" stroke-width="26" pathLength="1" style="--d: 230ms" />
                            <path class="is-bar" d="M199 79h46" stroke-width="26" pathLength="1" style="--d: 260ms" />

                            <path class="is-arm" d="M108 100l28 38" stroke-width="26" pathLength="1" style="--d: 300ms" />
                            <path class="is-arm" d="M194 100l-28 38" stroke-width="26" pathLength="1" style="--d: 330ms" />

                            <path class="is-wing" d="M22 145h78" stroke-width="26" pathLength="1" style="--d: 370ms" />
                            <path class="is-wing" d="M202 145h78" stroke-width="26" pathLength="1" style="--d: 400ms" />
                            <path class="is-wing" d="M20 181h60" stroke-width="26" pathLength="1" style="--d: 440ms" />
                            <path class="is-wing" d="M222 181h60" stroke-width="26" pathLength="1" style="--d: 470ms" />
                            <path class="is-wing" d="M72 199h38" stroke-width="26" pathLength="1" style="--d: 510ms" />
                            <path class="is-wing" d="M192 199h38" stroke-width="26" pathLength="1" style="--d: 540ms" />
                            <path class="is-wing" d="M20 216h60" stroke-width="26" pathLength="1" style="--d: 580ms" />
                            <path class="is-wing" d="M222 216h60" stroke-width="26" pathLength="1" style="--d: 610ms" />
                            <path class="is-wing" d="M20 248h60" stroke-width="26" pathLength="1" style="--d: 650ms" />
                            <path class="is-wing" d="M222 248h60" stroke-width="26" pathLength="1" style="--d: 680ms" />

                            <path class="is-wing" d="M19 145v105" stroke-width="22" pathLength="1" style="--d: 730ms" />
                            <path class="is-wing" d="M283 145v105" stroke-width="22" pathLength="1" style="--d: 760ms" />

                            <path class="is-stem" d="M136 110v168" stroke-width="22" pathLength="1" style="--d: 820ms" />
                            <path class="is-stem" d="M162 110v168" stroke-width="22" pathLength="1" style="--d: 850ms" />
                        </g>

                        <rect class="is-fill" x="0" y="0" width="302" height="292" fill="#fff" />
                    </mask>
                </defs>

                <image
                    href="{{ asset('img/hoopflow icon without background.png') }}"
                    x="0"
                    y="0"
                    width="302"
                    height="292"
                    mask="url(#hf-splash-draw)"
                />
            </svg>

            <span class="hf-lp-splash__sheen" aria-hidden="true"></span>
        </span>

        <span class="hf-lp-splash__wordmark">
            <img
                class="hf-lp-splash__word hf-lp-splash__word--light"
                src="{{ asset('img/brand/wordmark-light.png') }}"
                alt="HoopFlow"
                width="874"
                height="175"
                decoding="async"
                fetchpriority="high"
            >
            <img
                class="hf-lp-splash__word hf-lp-splash__word--dark"
                src="{{ asset('img/brand/wordmark-dark.png') }}"
                alt=""
                aria-hidden="true"
                width="874"
                height="175"
                decoding="async"
            >
        </span>

        <span class="hf-lp-splash__tag">
            <img
                class="hf-lp-splash__motto hf-lp-splash__motto--light"
                src="{{ asset('img/brand/motto-light.png') }}"
                alt="Let the games run themselves."
                width="874"
                height="51"
                decoding="async"
            >
            <img
                class="hf-lp-splash__motto hf-lp-splash__motto--dark"
                src="{{ asset('img/brand/motto-dark.png') }}"
                alt=""
                aria-hidden="true"
                width="874"
                height="51"
                decoding="async"
            >
        </span>

        <span class="hf-lp-splash__bar" aria-hidden="true"><span></span></span>
    </div>
</div>
