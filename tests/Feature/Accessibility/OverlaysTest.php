<?php

use App\Models\User;
use Database\Seeders\PrototypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PrototypeSeeder::class);
});

function overlayXPath(string $html): DOMXPath
{
    $dom = new DOMDocument;
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
    libxml_clear_errors();

    return new DOMXPath($dom);
}

it('renders the page skeleton for guests without a logout control', function () {
    $html = $this->get('/login')->getContent();
    $xpath = overlayXPath($html);

    expect($xpath->query('//*[@data-hf-page-loader]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-hf-page-loader][contains(@class, "hf-page-loader--guest")]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-hf-logout-dialog]')->length)->toBe(0)
        ->and($xpath->query('//form[@data-hf-logout-form]')->length)->toBe(0);
});

it('renders one shared logout confirmation for signed-in pages', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $xpath = overlayXPath($this->actingAs($organizer)->get('/dashboard')->getContent());

    expect($xpath->query('//*[@data-hf-page-loader]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-hf-page-loader][contains(@class, "hf-page-loader--guest")]')->length)->toBe(0)
        ->and($xpath->query('//dialog[@data-hf-logout-dialog]')->length)->toBe(1)
        ->and($xpath->query('//form[@data-hf-logout-form]')->length)->toBe(2)
        ->and($xpath->query('//button[@data-hf-logout-confirm]')->length)->toBe(1)
        ->and($xpath->query('//button[@data-hf-logout-cancel]')->length)->toBe(1);

    $dialog = $xpath->query('//dialog[@data-hf-logout-dialog]')->item(0);

    expect($dialog->getAttribute('aria-labelledby'))->toBe('hf-logout-title')
        ->and($dialog->getAttribute('aria-describedby'))->toBe('hf-logout-copy')
        ->and($xpath->query('//*[@id="hf-logout-title"]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="hf-logout-copy"]')->length)->toBe(1);
});
