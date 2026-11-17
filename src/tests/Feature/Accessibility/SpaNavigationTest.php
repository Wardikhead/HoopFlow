<?php

use App\Livewire\Public\StandingsShow;
use App\Models\Tournament;
use App\Models\User;
use Database\Seeders\PrototypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PrototypeSeeder::class);
});

function spaXPath(string $html): DOMXPath
{
    $dom = new DOMDocument;
    $previous = libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    return new DOMXPath($dom);
}

it('marks rail and mobile navigation links for AJAX navigation', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();

    $response = $this->actingAs($organizer)->get(route('dashboard'))->assertOk();
    $xpath = spaXPath((string) $response->getContent());

    foreach (['nav-dashboard', 'nav-tournaments', 'nav-bracket'] as $testId) {
        $link = $xpath->query("//a[@data-testid='{$testId}']")->item(0);

        expect($link)->not->toBeNull("Missing {$testId}");
        expect($link->hasAttribute('wire:navigate'))->toBeTrue("{$testId} lacks wire:navigate");
    }

    $tabs = $xpath->query("//a[starts-with(@data-testid, 'mobile-nav-') and @data-testid != 'mobile-nav-more']");
    expect($tabs->length)->toBeGreaterThan(0);

    foreach ($tabs as $tab) {
        expect($tab->hasAttribute('wire:navigate'))->toBeTrue($tab->getAttribute('data-testid').' lacks wire:navigate');
    }
});

it('leaves guest pages as normal browser navigation', function () {
    $response = $this->get(route('login'))->assertOk();

    expect(substr_count((string) $response->getContent(), 'wire:navigate'))->toBe(0);
});

it('names the authenticated main element for view transitions', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();

    $response = $this->actingAs($organizer)->get(route('dashboard'))->assertOk();
    $xpath = spaXPath((string) $response->getContent());
    $main = $xpath->query("//main[@id='main']")->item(0);

    expect($main)->not->toBeNull();
    expect($main->getAttribute('wire:transition.navigate'))->toBe('hf-page');
});

it('keeps guest pages free of navigation transition hooks', function () {
    $response = $this->get(route('login'))->assertOk();

    expect(substr_count((string) $response->getContent(), 'wire:transition.navigate'))->toBe(0);
});

it('shows the signed-in account in the user menu header', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();

    $response = $this->actingAs($organizer)->get(route('dashboard'))->assertOk();
    $xpath = spaXPath((string) $response->getContent());
    $head = $xpath->query("//div[contains(@class, 'hf-user-menu__head')]")->item(0);

    expect($head)->not->toBeNull();
    expect($head->textContent)->toContain('Signed in as');
    expect($head->textContent)->toContain($organizer->name);
    expect($head->textContent)->toContain($organizer->email);
});

it('navigates after switching the standings tournament', function () {
    $tournament = Tournament::query()->firstOrFail();

    $component = Livewire::test(StandingsShow::class)
        ->call('changeTournament', $tournament->id);

    $component->assertRedirect(route('standings', ['id' => $tournament->id]));
    expect($component->effects['redirectUsingNavigate'] ?? false)->toBeTrue();
});
