<?php

namespace App\Http\Controllers;

use App\Events\GameStateUpdated;
use App\Models\Game;
use App\Models\GameMove;
use App\Models\MatchmakingQueue;
use App\Models\Rank;
use App\Models\User;
use App\Services\GameService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function stats(): JsonResponse
    {
        return response()->json([
            'users' => User::count(),
            'banned' => User::whereNotNull('banned_at')->count(),
            'games_total' => Game::count(),
            'games_active' => Game::where('status', 'active')->count(),
            'games_today' => Game::whereDate('created_at', today())->count(),
            // Finished games only keep their move total; live moves still sit in game_moves.
            'moves_total' => (int) Game::sum('move_count') + GameMove::count(),
            'in_queue' => MatchmakingQueue::count(),
        ]);
    }

    public function users(Request $request): JsonResponse
    {
        $users = User::query()
            ->when($request->input('search'), function ($query, $search) {
                $query->where(fn ($q) => $q
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"));
            })
            ->orderByDesc('elo')
            ->paginate(15, ['id', 'name', 'email', 'elo', 'games_played', 'games_won', 'is_admin', 'banned_at', 'created_at']);

        return response()->json($users);
    }

    public function setElo(Request $request, User $user): JsonResponse
    {
        $data = $request->validate(['elo' => ['required', 'integer', 'min:0', 'max:4000']]);

        $user->update(['elo' => $data['elo']]);

        return response()->json($user);
    }

    public function toggleBan(Request $request, User $user): JsonResponse
    {
        abort_if($user->id === $request->user()->id, 422, 'You cannot ban yourself.');

        $user->update(['banned_at' => $user->banned_at ? null : now()]);
        MatchmakingQueue::where('user_id', $user->id)->delete();

        return response()->json($user);
    }

    public function toggleAdmin(Request $request, User $user): JsonResponse
    {
        abort_if($user->id === $request->user()->id, 422, 'You cannot change your own admin status.');

        $user->update(['is_admin' => !$user->is_admin]);

        return response()->json($user);
    }

    public function games(Request $request): JsonResponse
    {
        $games = Game::query()
            ->with(['player1:id,name,elo', 'player2:id,name,elo', 'winner:id,name'])
            ->withCount('moves')
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate(12);

        // Finished games no longer have move rows — surface the stored total instead.
        $games->getCollection()->transform(function (Game $game) {
            $game->moves_count = max($game->moves_count, $game->move_count);

            return $game;
        });

        return response()->json($games);
    }

    public function game(Game $game): JsonResponse
    {
        return response()->json($game->load([
            'player1:id,name,elo',
            'player2:id,name,elo',
            'winner:id,name',
            'moves' => fn ($q) => $q->orderBy('move_number')->with('player:id,name'),
        ]));
    }

    /**
     * Force-end a live game: declare a winner (ELO settles normally) or
     * void it (no rating change for either player).
     */
    public function endGame(Request $request, Game $game, GameService $gameService): JsonResponse
    {
        $data = $request->validate(['result' => ['required', 'in:p1,p2,void']]);

        abort_unless($game->status === 'active', 422, 'This game is not active.');

        if ($data['result'] === 'void') {
            $state = $game->board_state;
            $state['status'] = 'finished';
            $state['winner'] = null;

            $game->update([
                'status' => 'finished',
                'board_state' => $state,
                'p1_elo_after' => $game->p1_elo_before,
                'p2_elo_after' => $game->p2_elo_before,
            ]);

            $gameService->archiveMoves($game);
        } else {
            $gameService->finishGame($game, $data['result']);
        }

        rescue(fn () => broadcast(new GameStateUpdated($game->refresh())));

        return response()->json($game);
    }

    public function queue(): JsonResponse
    {
        return response()->json([
            'entries' => MatchmakingQueue::with('user:id,name,elo')->orderBy('created_at')->get(),
        ]);
    }

    public function removeFromQueue(User $user): JsonResponse
    {
        MatchmakingQueue::where('user_id', $user->id)->delete();

        return response()->json(['message' => 'Removed from queue.']);
    }

    public function clearQueue(): JsonResponse
    {
        MatchmakingQueue::query()->delete();

        return response()->json(['message' => 'Queue cleared.']);
    }

    public function storeRank(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:30'],
            'min_elo' => ['required', 'integer', 'min:0', 'max:4000', 'unique:ranks,min_elo'],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        return response()->json(Rank::create($data), 201);
    }

    public function updateRank(Request $request, Rank $rank): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:30'],
            'min_elo' => ['required', 'integer', 'min:0', 'max:4000', "unique:ranks,min_elo,{$rank->id}"],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        $rank->update($data);

        return response()->json($rank);
    }

    public function destroyRank(Rank $rank): JsonResponse
    {
        abort_if(Rank::count() <= 1, 422, 'At least one rank must remain.');

        $rank->delete();

        return response()->json(['message' => 'Rank deleted.']);
    }
}
