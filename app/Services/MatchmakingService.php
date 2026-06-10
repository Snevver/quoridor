<?php

namespace App\Services;

use App\Events\GameStarted;
use App\Jobs\ProcessMatchmaking;
use App\Models\Game;
use App\Models\MatchmakingQueue;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MatchmakingService
{
    private const BASE_RANGE = 200;
    private const RANGE_STEP = 50;
    private const STEP_SECONDS = 30;

    public function joinQueue(User $user): void
    {
        MatchmakingQueue::where('user_id', $user->id)->delete();
        MatchmakingQueue::create(['user_id' => $user->id, 'elo_at_join' => $user->elo]);

        ProcessMatchmaking::dispatch();
    }

    public function leaveQueue(User $user): void
    {
        MatchmakingQueue::where('user_id', $user->id)->delete();
    }

    /**
     * Pair queued players by closest ELO within their (time-expanded) range.
     * Runs inside a transaction with row locks so concurrent jobs cannot
     * match the same player twice. Events are broadcast after commit.
     */
    public function processQueue(): void
    {
        $games = DB::transaction(function () {
            $entries = MatchmakingQueue::lockForUpdate()->orderBy('created_at')->get();
            $matchedIds = [];
            $games = [];

            foreach ($entries as $entry) {
                if (isset($matchedIds[$entry->id])) {
                    continue;
                }

                $range = $this->rangeFor($entry);
                $best = null;
                $bestDiff = PHP_INT_MAX;

                foreach ($entries as $candidate) {
                    if ($candidate->id === $entry->id || isset($matchedIds[$candidate->id])) {
                        continue;
                    }

                    $diff = abs($candidate->elo_at_join - $entry->elo_at_join);
                    if ($diff <= $range && $diff < $bestDiff) {
                        $best = $candidate;
                        $bestDiff = $diff;
                    }
                }

                if ($best === null) {
                    continue;
                }

                $matchedIds[$entry->id] = true;
                $matchedIds[$best->id] = true;

                $p1 = $entry->user;
                $p2 = $best->user;

                $games[] = Game::create([
                    'player1_id' => $p1->id,
                    'player2_id' => $p2->id,
                    'board_state' => GameService::initialBoardState(),
                    'current_turn' => 'p1',
                    'status' => 'active',
                    'p1_elo_before' => $p1->elo,
                    'p2_elo_before' => $p2->elo,
                ]);

                MatchmakingQueue::whereIn('id', [$entry->id, $best->id])->delete();
            }

            return $games;
        });

        foreach ($games as $game) {
            broadcast(new GameStarted($game));
        }
    }

    private function rangeFor(MatchmakingQueue $entry): int
    {
        $waited = (int) abs(now()->diffInSeconds($entry->created_at));

        return self::BASE_RANGE + self::RANGE_STEP * intdiv($waited, self::STEP_SECONDS);
    }
}
