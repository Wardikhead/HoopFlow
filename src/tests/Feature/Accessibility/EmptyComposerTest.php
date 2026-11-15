<?php

use App\Livewire\Organizer\AnnouncementCreate;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('gives the tournament-less announcement composer a single level-one heading', function () {
    $organizer = User::factory()->create();
    $organizer->roles()->attach(Role::create(['key' => 'organizer', 'name' => 'Organizer']));

    $html = Livewire::actingAs($organizer)->test(AnnouncementCreate::class)->html();

    expect($html)->toContain('No tournaments yet');

    $dom = new DOMDocument;
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
    libxml_clear_errors();

    $headings = (new DOMXPath($dom))->query('//h1|//h2|//h3');

    expect($headings->length)->toBe(1)
        ->and($headings->item(0)->nodeName)->toBe('h1');
});
