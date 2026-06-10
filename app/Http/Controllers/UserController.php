<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    public function leaderboard(): JsonResponse
    {
        return response()->json([
            'players' => User::orderByDesc('elo')
                ->limit(10)
                ->get(['id', 'slug', 'name', 'elo', 'games_played', 'games_won']),
        ]);
    }

    public function show(User $user): JsonResponse
    {
        $recentGames = Game::with(['player1:id,slug,name', 'player2:id,slug,name'])
            ->where('status', 'finished')
            ->where(fn ($q) => $q->where('player1_id', $user->id)->orWhere('player2_id', $user->id))
            ->latest('updated_at')
            ->limit(10)
            ->get()
            ->map(function (Game $game) use ($user) {
                $role = $game->roleOf($user);
                $before = $role === 'p1' ? $game->p1_elo_before : $game->p2_elo_before;
                $after = $role === 'p1' ? $game->p1_elo_after : $game->p2_elo_after;

                return [
                    'id' => $game->id,
                    'opponent' => ($role === 'p1' ? $game->player2 : $game->player1)->only('id', 'slug', 'name'),
                    'won' => $game->winner_id === $user->id,
                    'voided' => $game->winner_id === null,
                    'elo_change' => $after !== null && $before !== null ? $after - $before : 0,
                    'played_at' => $game->updated_at,
                ];
            });

        return response()->json([
            'user' => $user->only('id', 'slug', 'name', 'elo', 'games_played', 'games_won', 'is_admin', 'created_at'),
            'recent_games' => $recentGames,
        ]);
    }
}
