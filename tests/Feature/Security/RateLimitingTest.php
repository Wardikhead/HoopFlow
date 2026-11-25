<?php

/** Tests for Rate limiting test. */

use App\Models\User;
use Livewire\Livewire;

it('throttles page requests for a guest', function () {
    config(['rate_limits.web.max_attempts' => 2]);

    $this->get('/login')->assertOk();
    $this->get('/login')->assertOk();
    $this->get('/login')->assertStatus(429);
});

it('keys page limits by authenticated user', function () {
    config(['rate_limits.web.max_attempts' => 2]);

    $first = User::factory()->create();
    $second = User::factory()->create();

    $this->actingAs($first)->get('/')->assertOk();
    $this->actingAs($first)->get('/')->assertOk();
    $this->actingAs($first)->get('/')->assertStatus(429);
    $this->actingAs($second)->get('/')->assertOk();
});

it('throttles registration attempts per ip', function () {
    config(['rate_limits.register.per_minute' => 2]);

    $payload = [
        'name' => 'Rate Limited',
        'email' => 'rate-limited@example.com',
        'password' => 'short',
        'role' => 'coach',
    ];

    $this->post('/register', $payload)->assertSessionHasErrors('password');
    $this->post('/register', $payload)->assertSessionHasErrors('password');
    $this->post('/register', $payload)->assertStatus(429);
});

it('throttles livewire update requests', function () {
    config(['rate_limits.livewire.max_attempts' => 2]);

    $this->withHeader('X-Livewire', 'true')
        ->postJson(Livewire::getUpdateUri(), [])
        ->assertNotFound();

    $this->withHeader('X-Livewire', 'true')
        ->postJson(Livewire::getUpdateUri(), [])
        ->assertNotFound();

    $this->withHeader('X-Livewire', 'true')
        ->postJson(Livewire::getUpdateUri(), [])
        ->assertStatus(429);
});

it('throttles api requests', function () {
    config(['rate_limits.api.max_attempts' => 2]);

    $this->getJson('/api/public/tournaments/missing/live')->assertNotFound();
    $this->getJson('/api/public/tournaments/missing/live')->assertNotFound();
    $this->getJson('/api/public/tournaments/missing/live')->assertStatus(429);
});
