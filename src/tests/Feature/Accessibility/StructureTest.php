<?php

use App\Models\Team;
use App\Models\User;
use Database\Seeders\PrototypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PrototypeSeeder::class);
});

function structureXPath(string $html): DOMXPath
{
    $dom = new DOMDocument;
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
    libxml_clear_errors();

    return new DOMXPath($dom);
}

/**
 * @return array<int, string>
 */
/**
 * Headings inside the main landmark. The closed "More" sheet lives outside
 * main with its own h2, which is not in the accessibility tree until opened.
 *
 * @return array<int, string>
 */
function structureHeadings(string $html): array
{
    $xpath = structureXPath($html);
    $main = $xpath->query('//main')->item(0);

    $expression = $main instanceof DOMNode
        ? './/h1|.//h2|.//h3|.//h4|.//h5|.//h6'
        : '//h1|//h2|//h3|//h4|//h5|//h6';

    $headings = [];
    foreach ($xpath->query($expression, $main instanceof DOMNode ? $main : null) as $node) {
        $headings[] = strtolower($node->nodeName);
    }

    return $headings;
}

function structureExpectSingleH1(string $html, string $page): void
{
    $levels = structureHeadings($html);

    expect($levels)->not->toBeEmpty("{$page} rendered no headings");
    expect($levels[0])->toBe('h1', "{$page} does not start with an h1, it starts with {$levels[0]}");
    expect(array_count_values($levels)['h1'] ?? 0)->toBe(1, "{$page} does not have exactly one h1");

    $previous = 1;
    foreach ($levels as $level) {
        $depth = (int) substr($level, 1);
        expect($depth - $previous)->toBeLessThanOrEqual(1, "{$page} skips from h{$previous} to {$level}");
        $previous = $depth;
    }
}

it('gives every not-found page a single level-one heading', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $coach = User::where('email', 'coach.marcus@hoopflow.ph')->firstOrFail();

    $organizerPages = [
        'standings' => '/standings?id=999999',
        'matchups' => '/matches?id=999999',
        'schedule' => '/schedule?id=999999',
        'tournament' => '/tournaments/999999',
        'game manage' => '/game-manage?match=999999',
    ];

    foreach ($organizerPages as $page => $url) {
        $response = $this->actingAs($organizer)->get($url);
        $response->assertStatus(200);
        structureExpectSingleH1($response->getContent(), $page);
    }

    $register = $this->actingAs($coach)->get('/team-register?tournament=999999');
    $register->assertStatus(200);
    structureExpectSingleH1($register->getContent(), 'team register');
});

it('gives the team unavailable page a single level-one heading', function () {
    $fan = User::where('email', 'fan.juan@hoopflow.ph')->firstOrFail();
    $team = Team::where('slug', 'npc')->firstOrFail();

    $response = $this->actingAs($fan)->get('/teams/'.$team->id);
    $response->assertStatus(403);
    structureExpectSingleH1($response->getContent(), 'team unavailable');
});

it('gives every rendered table a caption', function () {
    $tournaments = ['landing' => '/', 'standings' => '/standings'];

    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();

    foreach ($tournaments as $page => $url) {
        $response = $url === '/'
            ? $this->get($url)
            : $this->actingAs($organizer)->get($url);

        $response->assertStatus(200);
        $xpath = structureXPath($response->getContent());

        $tables = $xpath->query('//table');
        expect($tables->length)->toBeGreaterThan(0, "{$page} rendered no tables");

        foreach ($tables as $table) {
            $captions = $xpath->query('./caption', $table);
            expect($captions->length)->toBe(1, "{$page} has a table without a caption");
        }
    }
});

it('does not use generic link text in dashboard section heads', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();

    $html = $this->actingAs($organizer)->get('/dashboard')->getContent();

    $generic = [];
    foreach (structureXPath($html)->query('//a[@href]') as $link) {
        $text = trim(preg_replace('/\s+/', ' ', $link->textContent));
        if (in_array(strtolower($text), ['all', 'more', 'view', 'here', 'open', 'link'], true)) {
            $generic[] = $text.' -> '.$link->getAttribute('href');
        }
    }

    expect($generic)->toBe([], 'Ambiguous dashboard link text: '.implode(', ', $generic));
});

it('hides decorative bootstrap icons from assistive technology', function () {
    $offenders = [];

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path('resources/views')));
    foreach ($files as $file) {
        if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
            continue;
        }

        $contents = file_get_contents($file->getPathname());
        preg_match_all('/<i\b(?![^>]*\baria-hidden\b)[^>]*\bclass="[^"]*\bbi\b[^"]*"[^>]*>/s', $contents, $matches, PREG_OFFSET_CAPTURE);

        foreach ($matches[0] as [$tag, $offset]) {
            $line = substr_count(substr($contents, 0, $offset), "\n") + 1;
            $offenders[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname()).":{$line}";
        }
    }

    expect($offenders)->toBe([], 'Icons missing aria-hidden: '.implode(', ', $offenders));
});
