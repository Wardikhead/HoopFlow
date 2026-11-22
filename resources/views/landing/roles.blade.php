<section id="roles" class="hf-lp-roles-section" data-testid="landing-roles" aria-labelledby="landing-roles-title">
    <div class="hf-lp-shell">
        <header class="hf-lp-section-head">
            <span class="hf-eyebrow hf-lp-eyebrow">Built for the whole court</span>
            <h2 id="landing-roles-title">Everyone sees the game they need.</h2>
            <p>
                Organizers run the night, coaches keep the roster legal, and families follow every
                basket from the bleachers.
            </p>
        </header>

        <div class="hf-lp-roles">
            @foreach ($roles as $role)
                <article class="hf-lp-role hf-lp-role--{{ $role['key'] }} hf-lp-reveal" style="--i: {{ $loop->index }}; --hf-role-accent: {{ $role['accent'] }}">
                    <div class="hf-lp-role__copy">
                        <span class="hf-eyebrow hf-lp-eyebrow">{{ $role['eyebrow'] }}</span>
                        <h3>{{ $role['title'] }}</h3>
                        <p>{{ $role['copy'] }}</p>
                    </div>

                    <div class="hf-lp-role__vignette" aria-hidden="true">
                        @switch($role['key'])
                            @case('organizer')
                                <div class="hf-lp-role__panel">
                                    <div class="hf-lp-role__row"><span>Vipers</span><span class="hf-lp-role__state is-ok">Roster verified</span></div>
                                    <div class="hf-lp-role__row"><span>Saints</span><span class="hf-lp-role__state is-warn">Consent pending</span></div>
                                    <div class="hf-lp-role__row"><span>Bolts</span><span class="hf-lp-role__state is-ok">Payment received</span></div>
                                    <span class="hf-lp-role__link">Open review <i class="bi bi-arrow-up-right" aria-hidden="true"></i></span>
                                </div>
                                @break

                            @case('coach')
                                <div class="hf-lp-role__panel">
                                    <div class="hf-lp-role__meta"><span>Roster</span><strong>12/12</strong></div>
                                    <div class="hf-lp-role__progress"><span class="is-100"></span></div>
                                    <div class="hf-lp-role__meta"><span>Consent</span><strong>11/12</strong></div>
                                    <div class="hf-lp-role__progress"><span class="is-92"></span></div>
                                    <div class="hf-lp-role__next">Next: Sat 1:00 PM <strong>vs Vipers</strong></div>
                                </div>
                                @break

                            @case('fan')
                                <div class="hf-lp-role__phone">
                                    <span class="hf-lp-role__phone-label">Q4 · 02:14</span>
                                    <strong>66–50</strong>
                                    <span>Panthers lead Ravens</span>
                                    <span class="hf-lp-role__phone-next">Semifinal · Sat 1:00 PM</span>
                                </div>
                                @break
                        @endswitch
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</section>
