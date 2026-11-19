<?php

use App\Services\Calendar\IcsCalendarWriter;
use Carbon\CarbonImmutable;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function calendarWriterEvent(array $overrides = []): array
{
    return array_merge([
        'uid' => 'match-42@hoopflow',
        'start' => CarbonImmutable::parse('2026-11-21 09:00:00'),
        'end' => CarbonImmutable::parse('2026-11-21 10:00:00'),
        'summary' => 'San Isidro vs Poblacion — Semifinals',
        'location' => 'Court 1, Brgy. Gym',
        'description' => "Summer Cup\nSemifinals\nhttps://hoopflow.test/schedule?id=12&date=2026-11-21",
        'url' => 'https://hoopflow.test/schedule?id=12&date=2026-11-21',
        'status' => 'CONFIRMED',
        'sequence' => 1763442000,
        'modifiedAt' => CarbonImmutable::parse('2026-11-18 04:00:00', 'UTC'),
    ], $overrides);
}

/**
 * @param  list<array<string, mixed>>  $events
 */
function calendarWriterFeed(array $events): string
{
    return (new IcsCalendarWriter('Asia/Manila'))->write(
        'Summer Cup',
        'Published game schedule for Summer Cup.',
        $events,
        CarbonImmutable::parse('2026-11-18 04:00:00', 'UTC'),
    );
}

it('renders the calendar envelope and timezone', function () {
    $ics = calendarWriterFeed([calendarWriterEvent()]);

    expect($ics)
        ->toContain('BEGIN:VCALENDAR')
        ->toContain('VERSION:2.0')
        ->toContain('PRODID:-//HoopFlow//Tournament Calendar//EN')
        ->toContain('CALSCALE:GREGORIAN')
        ->toContain('X-WR-CALNAME:HoopFlow — Summer Cup')
        ->toContain('X-WR-TIMEZONE:Asia/Manila')
        ->toContain('BEGIN:VTIMEZONE')
        ->toContain('TZID:Asia/Manila')
        ->toContain('TZOFFSETTO:+0800')
        ->toContain('TZNAME:PHT');
});

it('renders a confirmed event with a stable uid and manila wall time', function () {
    $ics = calendarWriterFeed([calendarWriterEvent()]);

    expect($ics)
        ->toContain('UID:match-42@hoopflow')
        ->toContain('DTSTART;TZID=Asia/Manila:20261121T090000')
        ->toContain('DTEND;TZID=Asia/Manila:20261121T100000')
        ->toContain('SUMMARY:San Isidro vs Poblacion — Semifinals')
        ->toContain('STATUS:CONFIRMED');

    expect($ics)->not->toContain('DTSTART:20261121T010000Z');
    expect($ics)->not->toContain('DTSTART;TZID=Asia/Manila:20261121T170000');
});

it('renders a cancelled event with the cancelled status', function () {
    $ics = calendarWriterFeed([
        calendarWriterEvent(['status' => 'CANCELLED']),
    ]);

    expect($ics)->toContain('STATUS:CANCELLED');
});

it('escapes text values', function () {
    $ics = calendarWriterFeed([
        calendarWriterEvent([
            'summary' => 'San Isidro, Poblacion; A\\B',
            'description' => "Line one\nLine two",
        ]),
    ]);

    expect($ics)
        ->toContain('SUMMARY:San Isidro\\, Poblacion\\; A\\\\B')
        ->toContain('DESCRIPTION:Line one\\nLine two');
});

it('folds long lines without splitting utf-8 characters', function () {
    $ics = calendarWriterFeed([
        calendarWriterEvent(['summary' => str_repeat('Peñalosa Warriors ', 8)]),
    ]);

    expect(mb_check_encoding($ics, 'UTF-8'))->toBeTrue();
    expect($ics)->toContain("\r\n ");

    foreach (explode("\r\n", $ics) as $line) {
        expect(strlen($line))->toBeLessThanOrEqual(75);
    }
});

it('uses crlf line endings', function () {
    $ics = calendarWriterFeed([calendarWriterEvent()]);

    expect(preg_match('/(?<!\r)\n/', $ics))->toBe(0)
        ->and($ics)->toContain("BEGIN:VCALENDAR\r\n");
});

it('writes utc timestamps for dtstamp and last-modified', function () {
    $ics = calendarWriterFeed([calendarWriterEvent()]);

    expect($ics)
        ->toContain('DTSTAMP:20261118T040000Z')
        ->toContain('LAST-MODIFIED:20261118T040000Z');
});

it('renders a valid empty calendar', function () {
    $ics = calendarWriterFeed([]);

    expect($ics)
        ->toContain('BEGIN:VCALENDAR')
        ->toContain('END:VCALENDAR');

    expect($ics)->not->toContain('BEGIN:VEVENT');
});
