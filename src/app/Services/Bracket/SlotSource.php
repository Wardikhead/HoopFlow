<?php

namespace App\Services\Bracket;

final class SlotSource
{
    private function __construct(
        public readonly string $type,
        public readonly ?int $teamId = null,
        public readonly ?string $sourceStage = null,
        public readonly ?int $sourceRound = null,
        public readonly ?int $sourceMatch = null,
    ) {}

    public static function seed(?int $teamId): self
    {
        return new self('seed', $teamId);
    }

    public static function winner(string $stage, int $round, int $match): self
    {
        return new self('winner', null, $stage, $round, $match);
    }

    public static function loser(string $stage, int $round, int $match): self
    {
        return new self('loser', null, $stage, $round, $match);
    }

    public function key(): ?string
    {
        return $this->sourceStage === null
            ? null
            : $this->sourceStage.':'.$this->sourceRound.':'.$this->sourceMatch;
    }
}
