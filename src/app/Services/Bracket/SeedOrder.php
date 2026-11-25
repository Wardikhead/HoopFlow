<?php

/** Domain service for Seed order. */

namespace App\Services\Bracket;

final class SeedOrder
{
    /**
     * @return list<int>
     */
    public static function forSize(int $size): array
    {
        if ($size < 2 || ($size & ($size - 1)) !== 0) {
            throw new \InvalidArgumentException('Bracket size must be a power of two.');
        }

        $order = [1, 2];

        while (count($order) < $size) {
            $complement = count($order) * 2 + 1;
            $next = [];

            foreach ($order as $seed) {
                $next[] = $seed;
                $next[] = $complement - $seed;
            }

            $order = $next;
        }

        return $order;
    }
}
