<?php

namespace App\Http\Controllers;

use App\Events\GameStateUpdated;
use App\Http\Requests\MakeMoveRequest;
use App\Models\Game;
use App\Services\GameService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameController extends Controller
{
    public function __construct(private GameService $gameService)
    {
    }

    public function show(Request $request, Game $game): JsonResponse
    {
        abort_unless($game->hasPlayer($request->user()), 403);

        return response()->json($this->serialize($game, $request));
    }

    public function move(MakeMoveRequest $request, Game $game): JsonResponse
    {
        $this->gameService->applyMove($game, $request->user(), $request->validated());

        $game->refresh();
        rescue(fn () => broadcast(new GameStateUpdated($game, $game->moves()->latest('move_number')->first())));

        return response()->json($this->serialize($game, $request));
    }

    public function legalMoves(Request $request, Game $game): JsonResponse
    {
        abort_unless($game->hasPlayer($request->user()), 403);

        return response()->json([
            'moves' => $this->gameService->getLegalMoves($game->board_state, $game->roleOf($request->user())),
        ]);
    }

    public function resign(Request $request, Game $game): JsonResponse
    {
        abort_unless($game->hasPlayer($request->user()), 403);
        abort_unless($game->status === 'active', 422, 'This game is already over.');

        $loser = $game->roleOf($request->user());
        $this->gameService->finishGame($game, $loser === 'p1' ? 'p2' : 'p1');

        $game->refresh();
        rescue(fn () => broadcast(new GameStateUpdated($game)));

        return response()->json($this->serialize($game, $request));
    }

    private function serialize(Game $game, Request $request): array
    {
        return [
            'id' => $game->id,
            'board_state' => $game->board_state,
            'status' => $game->status,
            'my_role' => $game->roleOf($request->user()),
            'players' => [
                'p1' => $game->player1->only('id', 'name', 'elo'),
                'p2' => $game->player2->only('id', 'name', 'elo'),
            ],
            'elo' => [
                'p1_before' => $game->p1_elo_before,
                'p1_after' => $game->p1_elo_after,
                'p2_before' => $game->p2_elo_before,
                'p2_after' => $game->p2_elo_after,
            ],
        ];
    }
}
