<?php

namespace App\Exceptions;

use RuntimeException;

class AnnouncementDraftException extends RuntimeException
{
    private function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }

    public static function notConfigured(): self
    {
        return new self('not_configured', 'AI drafting is not configured.');
    }

    public static function busy(): self
    {
        return new self('busy', 'AI drafting is busy.');
    }

    public static function failed(): self
    {
        return new self('failed', 'The draft could not be created.');
    }
}
