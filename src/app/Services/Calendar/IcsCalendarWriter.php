<?php

/** Domain service for Ics calendar writer. */

namespace App\Services\Calendar;

use Carbon\CarbonInterface;

class IcsCalendarWriter
{
    /**
     * Create a writer using the given event timezone.
     *
     * @param  string  $timezone  The timezone.
     */
    public function __construct(private readonly string $timezone = 'Asia/Manila') {}

    /**
     * @param  list<array{
     *     uid: string,
     *     start: CarbonInterface,
     *     end: CarbonInterface,
     *     summary: string,
     *     location: string,
     *     description: string,
     *     url: string,
     *     status: 'CONFIRMED'|'CANCELLED',
     *     sequence: int,
     *     modifiedAt: CarbonInterface
     * }>  $events
     */
    public function write(
        string $calendarName,
        string $calendarDescription,
        array $events,
        CarbonInterface $generatedAt,
    ): string {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//HoopFlow//Tournament Calendar//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:'.$this->escapeText('HoopFlow — '.$calendarName),
            'X-WR-CALDESC:'.$this->escapeText($calendarDescription),
            'X-WR-TIMEZONE:'.$this->timezone,
            'REFRESH-INTERVAL;VALUE=DURATION:PT1H',
            'X-PUBLISHED-TTL:PT1H',
        ];

        foreach ($this->timezoneLines($generatedAt) as $line) {
            $lines[] = $line;
        }

        foreach ($events as $event) {
            foreach ($this->eventLines($event, $generatedAt) as $line) {
                $lines[] = $line;
            }
        }

        $lines[] = 'END:VCALENDAR';

        $output = [];

        foreach ($lines as $line) {
            foreach ($this->fold($line) as $folded) {
                $output[] = $folded;
            }
        }

        return implode("\r\n", $output)."\r\n";
    }

    /**
     * @return list<string>
     */
    private function timezoneLines(CarbonInterface $generatedAt): array
    {
        $local = $generatedAt->copy()->setTimezone($this->timezone);
        $offset = $local->format('O');
        $name = $this->timezone === 'Asia/Manila' ? 'PHT' : $local->format('T');

        return [
            'BEGIN:VTIMEZONE',
            'TZID:'.$this->timezone,
            'BEGIN:STANDARD',
            'DTSTART:19700101T000000',
            'TZOFFSETFROM:'.$offset,
            'TZOFFSETTO:'.$offset,
            'TZNAME:'.$name,
            'END:STANDARD',
            'END:VTIMEZONE',
        ];
    }

    /**
     * @param  array{
     *     uid: string,
     *     start: CarbonInterface,
     *     end: CarbonInterface,
     *     summary: string,
     *     location: string,
     *     description: string,
     *     url: string,
     *     status: 'CONFIRMED'|'CANCELLED',
     *     sequence: int,
     *     modifiedAt: CarbonInterface
     * }  $event
     * @return list<string>
     */
    private function eventLines(array $event, CarbonInterface $generatedAt): array
    {
        return [
            'BEGIN:VEVENT',
            'UID:'.$this->escapeText($event['uid']),
            'DTSTAMP:'.$generatedAt->copy()->utc()->format('Ymd\THis\Z'),
            'DTSTART;TZID='.$this->timezone.':'.$event['start']->format('Ymd\THis'),
            'DTEND;TZID='.$this->timezone.':'.$event['end']->format('Ymd\THis'),
            'SUMMARY:'.$this->escapeText($event['summary']),
            'LOCATION:'.$this->escapeText($event['location']),
            'DESCRIPTION:'.$this->escapeText($event['description']),
            'URL:'.$event['url'],
            'STATUS:'.$event['status'],
            'TRANSP:OPAQUE',
            'SEQUENCE:'.$event['sequence'],
            'LAST-MODIFIED:'.$event['modifiedAt']->copy()->utc()->format('Ymd\THis\Z'),
            'END:VEVENT',
        ];
    }

    /**
     * Escape a value for safe inclusion in an ICS text field.
     *
     * @param  string  $value  The value.
     * @return string The result.
     */
    private function escapeText(string $value): string
    {
        return str_replace(
            ['\\', "\r\n", "\n", "\r", ';', ','],
            ['\\\\', '\\n', '\\n', '\\n', '\\;', '\\,'],
            $value,
        );
    }

    /**
     * @return list<string>
     */
    private function fold(string $line): array
    {
        if (strlen($line) <= 75) {
            return [$line];
        }

        $chunks = [];
        $offset = 0;
        $limit = 75;

        while ($offset < strlen($line)) {
            $chunk = mb_strcut($line, $offset, $limit, 'UTF-8');

            if ($chunk === '') {
                break;
            }

            $chunks[] = $offset === 0 ? $chunk : ' '.$chunk;
            $offset += strlen($chunk);
            $limit = 74;
        }

        return $chunks;
    }
}
