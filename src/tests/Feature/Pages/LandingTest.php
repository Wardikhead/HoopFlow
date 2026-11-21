<?php

use App\Models\User;

it('renders the public landing page with its hero', function () {
    $this->get('/')
        ->assertOk()
        ->assertSeeText('Run the league, not the spreadsheet.')
        ->assertSeeText('Barangay & youth basketball')
        ->assertSeeText('Poblacion Panthers')
        ->assertSee('data-testid="landing-hero"', false)
        ->assertSee('data-theme-toggle', false)
        ->assertSee(route('login'), false)
        ->assertSee(route('register'), false);
});

it('shows a dashboard link instead of auth calls to action when signed in', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/')
        ->assertOk()
        ->assertSee('Dashboard')
        ->assertDontSee(route('login'), false)
        ->assertDontSee(route('register'), false);
});

it('renders the read-only feature lab with a bracket', function () {
    $this->get('/')
        ->assertOk()
        ->assertSeeText('Poke around.')
        ->assertSeeText('Nothing breaks.')
        ->assertSeeText('Preview only')
        ->assertSeeText('San Isidro Vipers')
        ->assertSeeText('Quarterfinals')
        ->assertSee('data-testid="landing-lab"', false)
        ->assertSee('data-testid="demo-tab-bracket"', false)
        ->assertSee('data-testid="demo-tab-calendar"', false)
        ->assertSee('data-testid="demo-tab-standings"', false)
        ->assertSee('data-testid="demo-panel-bracket"', false);
});

it('renders the schedule and standings previews', function () {
    $this->get('/')
        ->assertOk()
        ->assertSeeText('Sat, Nov 14')
        ->assertSeeText('Sat, Nov 21')
        ->assertSeeText('Sun, Nov 22')
        ->assertSeeText('Top 8 advance to the quarterfinals.')
        ->assertSeeText('Bayanihan Ballers')
        ->assertSee('data-testid="demo-panel-calendar"', false)
        ->assertSee('data-testid="demo-panel-standings"', false)
        ->assertSee('data-calendar', false)
        ->assertSee('data-count="78"', false);
});

it('explains the matchday flow and each role', function () {
    $this->get('/')
        ->assertOk()
        ->assertSeeText('Set the stage')
        ->assertSeeText('Register & vet')
        ->assertSeeText('Seed the bracket')
        ->assertSeeText('Schedule the floor')
        ->assertSeeText('Run the night')
        ->assertSeeText('See the whole floor.')
        ->assertSeeText('Know before tip-off.')
        ->assertSeeText('Follow from the bleachers.')
        ->assertSee('data-testid="landing-flow"', false)
        ->assertSee('data-testid="landing-roles"', false);
});

it('renders the eligibility spotlight, wire, closing CTA, and footer', function () {
    $this->get('/')
        ->assertOk()
        ->assertSeeText('The paperwork is part of the tournament.')
        ->assertSeeText('Consent pending')
        ->assertSeeText('Document missing')
        ->assertSeeText('Entry fee')
        ->assertSeeText('GCash')
        ->assertSeeText('QF4 is live on Court B')
        ->assertSeeText('Your league deserves a better scoreboard.')
        ->assertSeeText('Let the games run themselves.')
        ->assertSee('data-testid="landing-wire"', false)
        ->assertSee('data-testid="landing-cta"', false)
        ->assertSee('data-demo-target="standings"', false);
});

it('exposes the landing anchors, theme toggle, and read-only tab semantics', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('id="features"', false)
        ->assertSee('id="flow"', false)
        ->assertSee('id="roles"', false)
        ->assertSee('id="eligibility"', false)
        ->assertSee('role="tablist"', false)
        ->assertSee('aria-selected="true"', false)
        ->assertSee('aria-controls="panel-bracket"', false)
        ->assertSee('data-theme-toggle', false)
        ->assertSee('aria-label="Toggle color theme"', false);
});
