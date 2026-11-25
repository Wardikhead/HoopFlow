<?php

/** Tests for Announcement draft service test. */

use App\Exceptions\AnnouncementDraftException;
use App\Models\Tournament;
use App\Services\AnnouncementDraftService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.gemini.key' => 'test-key',
        'services.gemini.model' => 'gemini-test',
        'services.gemini.timeout' => 12,
    ]);
});

/**
 * @return array<string, mixed>
 */
function hfGeminiTextResponse(string $text): array
{
    return [
        'candidates' => [[
            'content' => [
                'parts' => [['text' => $text]],
            ],
        ]],
    ];
}

/**
 * Hf gemini json payload.
 *
 * @param  string  $title  The title.
 * @param  string  $body  The body.
 * @return string The result.
 */
function hfGeminiJsonPayload(string $title, string $body): string
{
    return json_encode(['title' => $title, 'body' => $body], JSON_THROW_ON_ERROR);
}

it('sends notes and tournament context to the configured model', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(
            hfGeminiTextResponse(hfGeminiJsonPayload(
                'Finals move to Court 1',
                'All semifinal and final games will be played on Court 1. Gate opens 45 minutes before tip-off.',
            )),
        ),
    ]);

    $tournament = new Tournament(['name' => 'HoopFlow Summer Cup']);

    $result = (new AnnouncementDraftService)->draft(
        'gate 45 min early, finals court 1',
        $tournament,
    );

    expect($result)->toBe([
        'title' => 'Finals move to Court 1',
        'body' => 'All semifinal and final games will be played on Court 1. Gate opens 45 minutes before tip-off.',
    ]);

    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://generativelanguage.googleapis.com/v1beta/models/gemini-test:generateContent'
            && $request->method() === 'POST'
            && $request->hasHeader('x-goog-api-key', 'test-key')
            && ! str_contains($request->url(), 'test-key')
            && $request['contents'][0]['parts'][0]['text'] === "Tournament: HoopFlow Summer Cup\n\nOrganizer notes:\ngate 45 min early, finals court 1"
            && $request['generationConfig']['responseMimeType'] === 'application/json'
            && $request['generationConfig']['maxOutputTokens'] === 1024;
    });
});

it('falls back to the default model when none is configured', function () {
    config(['services.gemini.model' => null]);

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(
            hfGeminiTextResponse(hfGeminiJsonPayload(
                'Finals move to Court 1',
                'All semifinal and final games will be played on Court 1. Gate opens early.',
            )),
        ),
    ]);

    (new AnnouncementDraftService)->draft('finals court 1, gate opens early');

    Http::assertSent(fn (Request $request): bool => $request->url()
        === 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-lite:generateContent');
});

it('uses a placeholder when no tournament is selected', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(
            hfGeminiTextResponse(hfGeminiJsonPayload(
                'Gate opens early',
                'The gate opens 45 minutes before the first tip-off. Bring valid player IDs.',
            )),
        ),
    ]);

    (new AnnouncementDraftService)->draft('gate opens 45 minutes early');

    Http::assertSent(fn (Request $request): bool => str_contains(
        (string) $request['contents'][0]['parts'][0]['text'],
        'Tournament: (not specified)',
    ));
});

it('collapses whitespace and strips tags from the draft', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(
            hfGeminiTextResponse(hfGeminiJsonPayload(
                '<b>Finals</b>   move to Court 1',
                "<p>All   semifinal games</p>\nwill be played on Court 1. Bring IDs.",
            )),
        ),
    ]);

    $result = (new AnnouncementDraftService)->draft('finals court 1, bring IDs');

    expect($result['title'])->toBe('Finals move to Court 1')
        ->and($result['body'])->toBe('All semifinal games will be played on Court 1. Bring IDs.');
});

it('caps the title and body at the existing length limits', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(
            hfGeminiTextResponse(hfGeminiJsonPayload(
                str_repeat('Championship ', 10),
                str_repeat('semifinal ', 40),
            )),
        ),
    ]);

    $result = (new AnnouncementDraftService)->draft('championship week details');

    expect(mb_strlen($result['title']))->toBeLessThanOrEqual(90)
        ->and(mb_strlen($result['body']))->toBeLessThanOrEqual(320)
        ->and($result['title'])->not->toEndWith(' ')
        ->and($result['body'])->not->toEndWith(' ');
});

it('throws not configured when the key is missing', function () {
    config(['services.gemini.key' => null]);
    Http::fake();

    expect(fn () => (new AnnouncementDraftService)->draft('enough notes here'))
        ->toThrow(AnnouncementDraftException::class, 'AI drafting is not configured.');

    Http::assertNothingSent();
});

it('throws busy when the provider rate limits the request', function () {
    Http::fake(['*' => Http::response(['error' => ['message' => 'quota']], 429)]);

    expect(fn () => (new AnnouncementDraftService)->draft('enough notes here'))
        ->toThrow(AnnouncementDraftException::class, 'AI drafting is busy.');
});

it('throws failed on a server error', function () {
    Http::fake(['*' => Http::response(['error' => ['message' => 'boom']], 500)]);

    expect(fn () => (new AnnouncementDraftService)->draft('enough notes here'))
        ->toThrow(AnnouncementDraftException::class, 'The draft could not be created.');
});

it('throws failed when the model returns malformed json', function () {
    Http::fake(['*' => Http::response(hfGeminiTextResponse('not-json'))]);

    expect(fn () => (new AnnouncementDraftService)->draft('enough notes here'))
        ->toThrow(AnnouncementDraftException::class, 'The draft could not be created.');
});

it('throws failed when the candidate list is empty', function () {
    Http::fake(['*' => Http::response(['candidates' => []])]);

    expect(fn () => (new AnnouncementDraftService)->draft('enough notes here'))
        ->toThrow(AnnouncementDraftException::class, 'The draft could not be created.');
});

it('throws failed when the generated text is below the minimum lengths', function () {
    Http::fake([
        '*' => Http::response(hfGeminiTextResponse(hfGeminiJsonPayload('Hi', 'Too short'))),
    ]);

    expect(fn () => (new AnnouncementDraftService)->draft('enough notes here'))
        ->toThrow(AnnouncementDraftException::class, 'The draft could not be created.');
});

it('uses a category-tuned prompt and derives the title from the body', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(
            hfGeminiTextResponse(hfGeminiJsonPayload(
                'Gate opens early',
                'Bring your IDs and arrive 45 minutes before the first game.',
            )),
        ),
    ]);

    (new AnnouncementDraftService)->draft('gate opens early, bring IDs', null, 'schedule');

    Http::assertSent(function (Request $request): bool {
        $prompt = (string) $request['systemInstruction']['parts'][0]['text'];

        return str_contains($prompt, 'Energetic and clear')
            && str_contains($prompt, 'Draft the body from the facts supplied first, then write a title that summarizes that body');
    });
});

it('permits emojis when the category chance passes', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(
            hfGeminiTextResponse(hfGeminiJsonPayload('Gate opens early', 'Bring your IDs.')),
        ),
    ]);

    mt_srand(5); // first mt_rand(1, 100) is 12, within the schedule chance of 25

    try {
        (new AnnouncementDraftService)->draft('gate opens early, bring IDs', null, 'schedule');
    } finally {
        mt_srand();
    }

    Http::assertSent(fn (Request $request): bool => str_contains(
        (string) $request['systemInstruction']['parts'][0]['text'],
        'You may use at most one emoji in the title and one in the body',
    ));
});

it('forbids emojis when the category chance fails', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(
            hfGeminiTextResponse(hfGeminiJsonPayload('Gate opens early', 'Bring your IDs.')),
        ),
    ]);

    mt_srand(1); // first mt_rand(1, 100) is 46, above the schedule chance of 25

    try {
        (new AnnouncementDraftService)->draft('gate opens early, bring IDs', null, 'schedule');
    } finally {
        mt_srand();
    }

    Http::assertSent(fn (Request $request): bool => str_contains(
        (string) $request['systemInstruction']['parts'][0]['text'],
        'Do not use emojis.',
    ));
});

it('falls back to a general tone for an unknown category', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(
            hfGeminiTextResponse(hfGeminiJsonPayload('Gate opens early', 'Bring your IDs.')),
        ),
    ]);

    (new AnnouncementDraftService)->draft('gate opens early, bring IDs', null, 'unknown-category');

    Http::assertSent(fn (Request $request): bool => str_contains(
        (string) $request['systemInstruction']['parts'][0]['text'],
        'Upbeat and neighbourly',
    ));
});
