<?php

namespace App\Services;

class EloService
{
    private const K_FACTOR = 32;

    /**
     * @param string $result 'a_wins' | 'b_wins'
     * @return array{a: int, b: int}
     */
    public function calculate(int $ratingA, int $ratingB, string $result): array
    {
        $expectedA = 1 / (1 + pow(10, ($ratingB - $ratingA) / 400));
        $expectedB = 1 - $expectedA;
        $scoreA = $result === 'a_wins' ? 1 : 0;
        $scoreB = 1 - $scoreA;

        return [
            'a' => (int) round($ratingA + self::K_FACTOR * ($scoreA - $expectedA)),
            'b' => (int) round($ratingB + self::K_FACTOR * ($scoreB - $expectedB)),
        ];
    }
}
