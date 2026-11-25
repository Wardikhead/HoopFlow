<?php

/** Application class Announcement draft exception. */

namespace App\Exceptions;

use RuntimeException;

class AnnouncementDraftException extends RuntimeException
{
    /**
     * Initialize the instance.
     *
     * @param  string  $reason  The reason.
     * @param  string  $message  The message.
     */
    private function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }

    /**
     * Not configured.
     *
     * @return self The result.
     */
    public static function notConfigured(): self
    {
        return new self('not_configured', 'AI drafting is not configured.');
    }

    /**
     * Busy.
     *
     * @return self The result.
     */
    public static function busy(): self
    {
        return new self('busy', 'AI drafting is busy.');
    }

    /**
     * Failed.
     *
     * @return self The result.
     */
    public static function failed(): self
    {
        return new self('failed', 'The draft could not be created.');
    }
}
