<?php

/** Domain service for Slot source. */

namespace App\Services\Bracket;

final class SlotSource
{
    /**
     * Create a slot source from a seed or a prior match.
     *
     * @param  string  $type  The type.
     * @param  ?int  $teamId  The team id.
     * @param  ?string  $sourceStage  The source stage.
     * @param  ?int  $sourceRound  The source round.
     * @param  ?int  $sourceMatch  The source match.
     */
    private function __construct(
        public readonly string $type,
        public readonly ?int $teamId = null,
        public readonly ?string $sourceStage = null,
        public readonly ?int $sourceRound = null,
        public readonly ?int $sourceMatch = null,
    ) {}

    /**
     * Create a source that fills a slot with a seeded team.
     *
     * @param  ?int  $teamId  The team id.
     * @return self The result.
     */
    public static function seed(?int $teamId): self
    {
        return new self('seed', $teamId);
    }

    /**
     * Create a source that fills a slot with a prior match winner.
     *
     * @param  string  $stage  The stage.
     * @param  int  $round  The round.
     * @param  int  $match  The match.
     * @return self The result.
     */
    public static function winner(string $stage, int $round, int $match): self
    {
        return new self('winner', null, $stage, $round, $match);
    }

    /**
     * Create a source that fills a slot with a prior match loser.
     *
     * @param  string  $stage  The stage.
     * @param  int  $round  The round.
     * @param  int  $match  The match.
     * @return self The result.
     */
    public static function loser(string $stage, int $round, int $match): self
    {
        return new self('loser', null, $stage, $round, $match);
    }

    /**
     * Return the stable identifier for this slot source.
     *
     * @return ?string The result.
     */
    public function key(): ?string
    {
        return $this->sourceStage === null
            ? null
            : $this->sourceStage.':'.$this->sourceRound.':'.$this->sourceMatch;
    }
}
