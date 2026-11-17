<?php

namespace App\Exceptions;

use App\Models\MatchModel;
use RuntimeException;

class ScoreConflictException extends RuntimeException
{
    public function __construct(public readonly MatchModel $current)
    {
        parent::__construct('This score was updated somewhere else. Refresh and try again.');
    }
}
