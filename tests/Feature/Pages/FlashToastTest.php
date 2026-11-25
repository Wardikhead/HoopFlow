<?php

/** Tests for Flash toast test. */

use App\Models\User;
use Database\Seeders\PrototypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PrototypeSeeder::class);
});

it('renders the flash toast payload for the client to consume', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();

    $this->actingAs($organizer)
        ->withSession(['announcement_toast' => [
            'title' => 'Announcement published',
            'message' => 'The bulletin is live for its audience.',
            'tone' => 'success',
        ]])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('data-flash-toast', false)
        ->assertSee('Announcement published', false);
});

it('renders the page announcer region in the authenticated shell', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();

    $this->actingAs($organizer)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('data-hf-page-announcer', false);
});
