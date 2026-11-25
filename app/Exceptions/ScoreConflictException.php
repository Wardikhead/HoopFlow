<?php

/** Application class Score conflict exception. */

namespace App\Exceptions;

use App\Models\MatchModel;
use RuntimeException;

class ScoreConflictException extends RuntimeException
{
    /**
     * Initialize the instance.
     *
     * @param  MatchModel  $current  The current.
     */
    public function __construct(public readonly MatchModel $current)
    {
        parent::__construct('This score was updated somewhere else. Refresh and try again.');
    }
}
