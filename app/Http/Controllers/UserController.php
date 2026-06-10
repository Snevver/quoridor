<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    public function leaderboard(): JsonResponse
    {
        return response()->json([
            'players' => User::orderByDesc('elo')
                ->limit(10)
                ->get(['id', 'name', 'elo', 'games_played', 'games_won']),
        ]);
    }
}
