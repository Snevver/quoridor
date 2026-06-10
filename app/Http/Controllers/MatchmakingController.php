<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\MatchmakingQueue;
use App\Services\MatchmakingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MatchmakingController extends Controller
{
    public function __construct(private MatchmakingService $matchmaking)
    {
    }

    public function join(Request $request): JsonResponse
    {
        $this->matchmaking->joinQueue($request->user());

        return response()->json(['message' => 'Joined queue.']);
    }

    public function leave(Request $request): JsonResponse
    {
        $this->matchmaking->leaveQueue($request->user());

        return response()->json(['message' => 'Left queue.']);
    }

    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        // Re-run matching on poll so ELO ranges widen for waiting players.
        $this->matchmaking->processQueue();

        $entry = MatchmakingQueue::where('user_id', $user->id)->first();
        $activeGame = Game::where('status', 'active')
            ->where(fn ($q) => $q->where('player1_id', $user->id)->orWhere('player2_id', $user->id))
            ->latest()
            ->first();

        return response()->json([
            'in_queue' => $entry !== null,
            'waiting_seconds' => $entry ? (int) abs(now()->diffInSeconds($entry->created_at)) : 0,
            'active_game_id' => $activeGame?->id,
            'active_game_slug' => $activeGame?->slug,
        ]);
    }
}
