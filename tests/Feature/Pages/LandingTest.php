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
        ->assertDontSee(route('login'), false);
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
        ->assertSee('data-testid="demo-tab-schedule"', false)
        ->assertSee('data-testid="demo-tab-standings"', false)
        ->assertSee('data-testid="demo-panel-bracket"', false);
});
